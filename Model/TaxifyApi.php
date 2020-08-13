<?php

namespace BeeBots\Taxify\Model;

use Magento\Customer\Model\Data\Address;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Model\Sales\Quote\QuoteDetails;
use Psr\Log\LoggerInterface;
use rk\Taxify\AddressFactory;
use rk\Taxify\Communicator;
use rk\Taxify\Requests\CalculateTaxFactory;
use rk\Taxify\Responses\CalculateTax;
use rk\Taxify\Taxify;
use rk\Taxify\TaxLineFactory;
use Throwable;
use function time;

/**
 * Class TaxifyApi
 *
 * @package BeeBots\Taxify\Model
 */
class TaxifyApi
{
    /** @var CalculateTaxFactory */
    private $calculateTaxFactory;

    /** @var AddressFactory */
    private $addressFactory;

    /** @var LoggerInterface */
    private $logger;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var RegionFactory */
    private $regionFactory;

    /** @var Config */
    private $taxifyConfig;

    /** @var TaxClassRepositoryInterface */
    private $taxClassRepository;

    /** @var ManagerInterface */
    private $messageManager;

    /**
     * TaxifyApi constructor.
     *
     * @param CalculateTaxFactory $calculateTaxFactory
     * @param AddressFactory $addressFactory
     * @param TaxLineFactory $taxLineFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param RegionFactory $regionFactory
     * @param LoggerInterface $logger
     * @param Config $taxifyConfig
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        CalculateTaxFactory $calculateTaxFactory,
        AddressFactory $addressFactory,
        TaxLineFactory $taxLineFactory,
        ScopeConfigInterface $scopeConfig,
        RegionFactory $regionFactory,
        LoggerInterface $logger,
        Config $taxifyConfig,
        TaxClassRepositoryInterface $taxClassRepository,
        ManagerInterface $messageManager
    ) {
        $this->calculateTaxFactory = $calculateTaxFactory;
        $this->addressFactory = $addressFactory;
        $this->taxLineFactory = $taxLineFactory;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->regionFactory = $regionFactory;
        $this->taxifyConfig = $taxifyConfig;
        $this->taxClassRepository = $taxClassRepository;
        $this->messageManager = $messageManager;
    }

    /**
     * Function: getTaxForOrder
     *
     *
     * @param QuoteDetails $quote
     *
     * @return \rk\Taxify\Responses\CalculateTax|null
     */
    public function getTaxForQuote(QuoteDetailsInterface $quote)
    {
        //TODO: Implement a response cache
        $taxResponse = null;

        $shippingAddress = $quote->getShippingAddress();
        if (! $this->shippingAddressIsUsable($shippingAddress)) {
            return $taxResponse;
        }
        $region = $this->getRegionById($shippingAddress->getRegion()->getRegionId());
        $street1 = $shippingAddress->getStreet()[0] ?? '';
        $street2 = $shippingAddress->getStreet()[1] ?? '';
        $request = $this->calculateTaxFactory->create()
            ->setDocumentKey('quote' . $quote->getId())
            ->setTaxDate(time())
            ->setCommitted(false)
            ->setOriginAddress(
                $this->addressFactory->create()
                    ->setCountry($this->scopeConfig->getValue('shipping/origin/country_id'))
                    ->setRegion($this->scopeConfig->getValue('shipping/origin/region_id'))
                    ->setCity($this->scopeConfig->getValue('shipping/origin/city'))
                    ->setPostalCode($this->scopeConfig->getValue('shipping/origin/postcode'))
                    ->setStreet1($this->scopeConfig->getValue('shipping/origin/street_line1'))
                    ->setStreet2($this->scopeConfig->getValue('shipping/origin/street_line2'))
            )->setDestinationAddress(
                $this->addressFactory->create()
                    ->setCountry($shippingAddress->getCountryId())
                    ->setRegion($region->getCode() ?? '')
                    ->setCity($shippingAddress->getCity() ?? '')
                    ->setPostalCode($shippingAddress->getPostcode())
                    ->setStreet1($street1)
                    ->setStreet2($street2)
            );

        foreach ($quote->getItems() as $quoteItem) {
            $extensionAttributes = $quoteItem->getExtensionAttributes();

            $sku = $extensionAttributes->getProductSku();
            $priceForTaxCalculation = $extensionAttributes->getPriceForTaxCalculation() ?? $quoteItem->getUnitPrice();
            $rowTotal = $priceForTaxCalculation * $quoteItem->getQuantity();
            $productName = $extensionAttributes->getProductName();
            $taxifyTaxabilityCode = $this->getTaxifyTaxabilityCode($quoteItem);

            $request->addLine(
                $this->taxLineFactory->create()
                    ->setLineNumber($quoteItem->getCode())
                    ->setItemKey($sku)
                    ->setQuantity($quoteItem->getQuantity())
                    ->setActualExtendedPrice($rowTotal)
                    ->setItemDescription($productName)
                    ->setItemTaxabilityCode($taxifyTaxabilityCode)
            );
        }

        try {
            $taxify = new Taxify($this->taxifyConfig->getApiKey(), Taxify::ENV_PROD, false);
            $communicator = new Communicator($taxify);
            $taxResponse = $request->execute($communicator);
        } catch (Throwable $e) {
            $this->logger->error('Error get rates from Taxify', ['exception' => $e]);
            $this->messageManager->addErrorMessage(
                __('Unable to calculate taxes. This could be caused by an invalid address provided in checkout.')
            );
        }

        return $taxResponse;
    }

    /**
     * @param int $regionId
     *
     * @return Region
     */
    protected function getRegionById($regionId)
    {
        /** @var Region $region */
        $region = $this->regionFactory->create();
        $region->load($regionId);
        return $region;
    }

    private function getTaxifyTaxabilityCode($quoteItem)
    {
        $taxClassId = $quoteItem->getTaxClassKey()
        && $quoteItem->getTaxClassKey()->getType() === TaxClassKeyInterface::TYPE_ID
            ? $quoteItem->getTaxClassKey()->getValue()
            : $quoteItem->getTaxClassId();

        $taxClassName = $this->getMagentoTaxClassNameById($taxClassId);
        return $this->getTaxifyTaxabilityCodeFromMagentoCode($taxClassName);
    }

    private function getMagentoTaxClassNameById($taxClassId)
    {
        if (! $taxClassId) {
            return 'None';
        }

        try {
            return $this->taxClassRepository->get($taxClassId)
                ->getClassName();
        } catch (NoSuchEntityException $exception) {
            $this->logger->critical($exception);
            return 'None';
        }
    }

    private function getTaxifyTaxabilityCodeFromMagentoCode($taxClassName)
    {
        switch ($taxClassName) {
            case $this->taxifyConfig->getMageTaxClassNameForCandy():
                return TaxifyConstants::ITEM_TAX_CODE_CANDY;
            case $this->taxifyConfig->getMageTaxClassNameForClothing():
                return TaxifyConstants::ITEM_TAX_CODE_CLOTHING;
            case $this->taxifyConfig->getMageTaxClassNameForExemptservice():
                return TaxifyConstants::ITEM_TAX_CODE_EXEMPTSERVICE;
            case $this->taxifyConfig->getMageTaxClassNameForFood():
                return TaxifyConstants::ITEM_TAX_CODE_FOOD;
            case $this->taxifyConfig->getMageTaxClassNameForFoodservice():
                return TaxifyConstants::ITEM_TAX_CODE_FOODSERVICE;
            case $this->taxifyConfig->getMageTaxClassNameForFreight():
                return TaxifyConstants::ITEM_TAX_CODE_FREIGHT;
            case $this->taxifyConfig->getMageTaxClassNameForInstallation():
                return TaxifyConstants::ITEM_TAX_CODE_INSTALLATION;
            case $this->taxifyConfig->getMageTaxClassNameForNontax():
                return TaxifyConstants::ITEM_TAX_CODE_NONTAX;
            case $this->taxifyConfig->getMageTaxClassNameForProservice():
                return TaxifyConstants::ITEM_TAX_CODE_PROSERVICE;
            case $this->taxifyConfig->getMageTaxClassNameForSupplements():
                return TaxifyConstants::ITEM_TAX_CODE_SUPPLEMENTS;
            default:
                return TaxifyConstants::ITEM_TAX_CODE_TAXABLE;
        }
    }

    private function shippingAddressIsUsable(Address $shippingAddress)
    {
        return $shippingAddress
            && $shippingAddress->getCountryId()
            && $shippingAddress->getPostcode();
    }
}
