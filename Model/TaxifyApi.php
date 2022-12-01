<?php

namespace BeeBots\Taxify\Model;

use BeeBots\Taxify\Helper\TaxClassHelper;
use Magento\Checkout\Model\Session;
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
use function implode;
use function sha1;
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

    /** @var Session */
    private $checkoutSession;

    /** @var TaxClasshelper */
    private $taxClassHelper;

    /** @var TaxLineFactory */
    private $taxLineFactory;

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
     * @param Session $checkoutSession
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
        ManagerInterface $messageManager,
        Session $checkoutSession
    ) {
        $this->calculateTaxFactory = $calculateTaxFactory;
        $this->addressFactory = $addressFactory;
        $this->taxLineFactory = $taxLineFactory;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->regionFactory = $regionFactory;
        $this->taxifyConfig = $taxifyConfig;
        $this->taxClassHelper = $taxClassHelper;
        $this->messageManager = $messageManager;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * Function: getTaxForOrder
     *
     * @param QuoteDetails $quote
     *
     * @return CalculateTax|null
     */
    public function getTaxForQuote(QuoteDetailsInterface $quote)
    {
        $taxResponse = null;

        $shippingAddress = $quote->getShippingAddress();
        if (! $this->shippingAddressIsUsable($shippingAddress)) {
            return $taxResponse;
        }

        $taxResponse = $this->loadFromCache($quote);
        if ($taxResponse) {
            return $taxResponse;
        }

        $regionCode = $this->getRegionCodeById($shippingAddress->getRegion()->getRegionId());

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
                    ->setRegion($regionCode)
                    ->setCity($shippingAddress->getCity() ?? '')
                    ->setPostalCode($shippingAddress->getPostcode())
                    ->setStreet1($street1)
                    ->setStreet2($street2)
            )->setCustomerTaxabilityCode($this->getTaxifyCustomerTaxabilityCode($quote));

        if ($quote->getCustomerId()) {
            $request->setCustomerKey($quote->getCustomerId());
        }

        foreach ($quote->getItems() as $quoteItem) {
            $extensionAttributes = $quoteItem->getExtensionAttributes();

            $sku = $extensionAttributes->getProductSku();
            $priceForTaxCalculation = $extensionAttributes->getPriceForTaxCalculation() ?? $quoteItem->getUnitPrice();
            $rowTotal = $priceForTaxCalculation * $quoteItem->getQuantity() - $quoteItem->getDiscountAmount();
            $productName = $extensionAttributes->getProductName();
            $taxifyTaxabilityCode = $this->getTaxifyItemTaxabilityCode($quoteItem);

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
            $this->cacheTaxResponse($taxResponse, $quote);
        } catch (Throwable $e) {
            $this->logger->error('Error getting rates from Taxify', ['exception' => $e]);
            return null;
        }

        return $taxResponse;
    }

    /**
     * Function: getRegionById
     *
     * @param int $regionId
     *
     * @return Region
     */
    protected function getRegionById(int $regionId)
    {
        $region = $this->regionFactory->create();
        $region->load($regionId);
        return $region;
    }

    /**
     * Function: getRegionCodeById
     *
     * @param mixed $regionId
     *
     * @return string
     */
    protected function getRegionCodeById($regionId)
    {
        if (! $regionId) {
            return '';
        }

        $region = $this->getRegionById($regionId);
        return $region->getCode() ?? '';
    }

    /**
     * Function: getTaxifyItemTaxabilityCode
     *
     * @param $quoteItem
     *
     * @return string
     */
    private function getTaxifyItemTaxabilityCode($quoteItem)
    {
        $taxClassId = $quoteItem->getTaxClassKey()
            && $quoteItem->getTaxClassKey()->getType() === TaxClassKeyInterface::TYPE_ID
                ? $quoteItem->getTaxClassKey()->getValue()
                : $quoteItem->getTaxClassId();

        $taxClassName = $this->taxClassHelper->getMagentoTaxClassNameById($taxClassId);
        return $this->taxClassHelper->getTaxifyItemTaxabilityCodeFromMagentoTaxClassName($taxClassName);
    }

    /**
     * Function: getTaxifyCustomerTaxabilityCode
     *
     * @param $quote
     *
     * @return string
     */
    private function getTaxifyCustomerTaxabilityCode($quote)
    {
        $taxClassId = $this->getCustomerTaxClassId($quote);
        $taxClassName = $this->taxClassHelper->getMagentoTaxClassNameById($taxClassId);
        return $this->taxClassHelper->getTaxifyCustomerTaxabilityCodeFromMagentoTaxClassName($taxClassName);
    }

    /**
     * Function: getTaxifyItemTaxabilityCode
     *
     * @param $quote
     *
     * @return string
     */
    private function getCustomerTaxClassId($quote)
    {
        return $quote->getCustomerTaxClassKey()
            && $quote->getCustomerTaxClassKey()->getType() === TaxClassKeyInterface::TYPE_ID
                ? $quote->getCustomerTaxClassKey()->getValue()
                : $quote->getCustomerTaxClassId();
    }

    /**
     * Function: shippingAddressIsUsable
     *
     * @param Address $shippingAddress
     *
     * @return bool
     */
    private function shippingAddressIsUsable(Address $shippingAddress)
    {
        return $shippingAddress
            && $shippingAddress->getCountryId()
            && $shippingAddress->getPostcode();
    }

    /**
     * Function: loadFromCache
     *
     * @param QuoteDetails $quote
     *
     * @return bool|mixed|null
     */
    private function loadFromCache(QuoteDetails $quote)
    {
        $cachePrefix = $this->getCachePrefix($quote);
        $key = $this->getCacheKey($quote);
        $cachedKey = $this->checkoutSession->getData($cachePrefix . 'key');
        if ($key === $cachedKey) {
            return $this->checkoutSession->getData($cachePrefix . 'value');
        }
        return false;
    }

    /**
     * Function: cacheTaxResponse
     *
     * @param $taxResponse
     * @param QuoteDetails $quote
     */
    private function cacheTaxResponse($taxResponse, QuoteDetails $quote)
    {
        $cachePrefix = $this->getCachePrefix($quote);
        $key = $this->getCacheKey($quote);
        $this->checkoutSession->setData($cachePrefix . 'key', $key);
        $this->checkoutSession->setData($cachePrefix . 'value', $taxResponse);
    }

    /**
     * Function: getCacheKey
     *
     * @param QuoteDetails $quote
     *
     * @return string
     */
    private function getCacheKey(QuoteDetails $quote)
    {
        $keys = [];
        $shippingAddress = $quote->getShippingAddress();
        $keys[] = $shippingAddress->getStreet()[0] ?? '';
        $keys[] = $shippingAddress->getStreet()[1] ?? '';
        $keys[] = $shippingAddress->getCity();
        $keys[] = $shippingAddress->getRegionId();
        $keys[] = $shippingAddress->getPostcode();
        $keys[] = $shippingAddress->getCountryId();
        $keys[] = $quote->getId();
        $keys[] = $this->getCustomerTaxClassId($quote);

        foreach ($quote->getItems() as $quoteItem) {
            $extensionAttributes = $quoteItem->getExtensionAttributes();
            $keys[] = $extensionAttributes->getProductSku();
            $keys[] = $quoteItem->getQuantity();
            $keys[] = $quoteItem->getCode();
            // The shipping quote item's unit price changes when the shipping rate changes.
            // Round to 2 decimals to avoid extra zeros changing the cache key
            $keys[] = round($quoteItem->getUnitPrice() ?? 0.00, 2);
            $keys[] = round($quoteItem->getDiscountAmount() ?? 0.00, 2);
        }
        $key = sha1(implode('|', $keys));
        return $key;
    }

    /**
     * Function: isOnlyShipping
     *
     * @param QuoteDetails $quote
     *
     * @return bool
     */
    private function isOnlyShipping(QuoteDetails $quote)
    {
        $items = $quote->getItems();
        foreach ($items as $item) {
            if ($item->getCode() !== 'shipping') {
                return false;
            }
        }

        return true;
    }

    /**
     * Function: isOnlyShipping
     *
     * @param QuoteDetails $quote
     *
     * @return bool
     */
    private function isMissingShipping(QuoteDetails $quote)
    {
        $items = $quote->getItems();
        foreach ($items as $item) {
            if ($item->getCode() === 'shipping') {
                return false;
            }
        }
        return true;
    }

    /**
     * Function: getCachePrefix
     *
     * @param QuoteDetails $quote
     *
     * @return string
     */
    private function getCachePrefix(QuoteDetails $quote)
    {
        $cachePrefix = 'tax_cache_';
        if ($this->isOnlyShipping($quote)) {
            $cachePrefix = 'shipping_' . $cachePrefix;
        }
        if ($this->isMissingShipping($quote)) {
            $cachePrefix = 'item_tax_cache_';
        }
        return $cachePrefix;
    }
}
