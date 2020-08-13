<?php

namespace BeeBots\Taxify\Model;

use BeeBots\Taxify\Helper\TaxClassHelper;
use Magento\Customer\Model\Data\Address;
use Magento\Directory\Model\Region;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Tax\Api\Data\QuoteDetailsInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
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

    /** @var ManagerInterface */
    private $messageManager;

    /** @var TaxClasshelper */
    private $taxClassHelper;

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
     * @param TaxClasshelper $taxClassHelper
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
        TaxClasshelper $taxClassHelper,
        ManagerInterface $messageManager
    ) {
        $this->calculateTaxFactory = $calculateTaxFactory;
        $this->addressFactory = $addressFactory;
        $this->taxLineFactory = $taxLineFactory;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->regionFactory = $regionFactory;
        $this->taxifyConfig = $taxifyConfig;
        $this->messageManager = $messageManager;
        $this->taxClassHelper = $taxClassHelper;
    }

    /**
     * Function: getTaxForOrder
     *
     *
     * @param QuoteDetails $quote
     *
     * @return CalculateTax|null
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
            $this->logger->error('Error getting rates from Taxify', ['exception' => $e]);
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

        $taxClassName = $this->taxClassHelper->getMagentoTaxClassNameById($taxClassId);
        return $this->taxClassHelper->getTaxifyTaxabilityCodeFromMagentoTaxClassName($taxClassName);
    }

    private function shippingAddressIsUsable(Address $shippingAddress)
    {
        return $shippingAddress
            && $shippingAddress->getCountryId()
            && $shippingAddress->getPostcode();
    }
}
