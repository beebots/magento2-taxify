<?php

namespace BeeBots\Taxify\Plugin;

use BeeBots\Taxify\Model\Config;
use BeeBots\Taxify\Model\TaxifyConstants;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionFactory;
use Magento\Tax\Api\Data\QuoteDetailsItemExtensionInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Model\Sales\Total\Quote\CommonTaxCollector;

class CommonTaxCollectorPlugin
{
    /** @var Config */
    private $config;

    /** @var QuoteDetailsItemExtensionFactory */
    private $extensionFactory;

    /**
     * CommonTaxCollectorPlugin constructor.
     *
     * @param Config $config
     * @param QuoteDetailsItemExtensionFactory $extensionFactory
     */
    public function __construct(
        Config $config,
        QuoteDetailsItemExtensionFactory $extensionFactory
    ) {
        $this->config = $config;
        $this->extensionFactory = $extensionFactory;
    }

    /**
     * Function: aroundMapItem
     *
     * @param CommonTaxCollector $subject
     * @param callable $proceed
     * @param mixed ...$arguments
     *
     * @return QuoteDetailsItemInterface
     */
    public function aroundMapItem(
        CommonTaxCollector $subject,
        callable $proceed,
        ...$arguments
    ) {
        /** @var QuoteDetailsItemInterface $quoteDetailsItem */
        $quoteDetailsItem = $proceed(...$arguments);
        if (! $this->config->isEnabled()) {
            return $quoteDetailsItem;
        }

        /** @var AbstractItem $item */
        $item = $arguments[1];

        //$attribute = $item->getProduct()->getCustomAttribute('tax_class_id');
        //$taxClassId = $attribute ? $attribute->getValue() : null;

        $extensionData = $this->getExtensionAttributes($quoteDetailsItem);
        $extensionData->setProductSku($item->getProduct()->getSku());
        $extensionData->setProductName($item->getProduct()->getName());
        $extensionData->setProductId($item->getProduct()->getId());
        $extensionData->setQuoteItemId($item->getId());
        $extensionData->setCustomerId($item->getQuote()->getCustomerId());
//        if ($taxClassId) {
//            $extensionData->setTaxClassId($taxClassId);
//        }

        if ($quote = $item->getQuote()) {
            $extensionData->setQuoteId($quote->getId());
            $extensionData->setCustomerId($quote->getCustomerId());
        }

        return $quoteDetailsItem;
    }

    public function aroundGetShippingDataObject(
        CommonTaxCollector $subject,
        callable $proceed,
        ...$arguments
    ) {
        $shippingAssignment = $arguments[0];
        $total = $arguments[1];

        /** @var QuoteDetailsItemInterface $quoteDetailsItem */
        $quoteDetailsItem = $proceed(...$arguments);

        $shipping = $shippingAssignment->getShipping();
        if ($shipping === null) {
            return $quoteDetailsItem;
        }

        if ($shipping->getMethod() === null
            && $total->getShippingTaxCalculationAmount() == 0) {
            // If there's no method and a $0 price then there's no need for an empty shipping tax item
            return null;
        }

        $extensionAttributes = $this->getExtensionAttributes($quoteDetailsItem);
        $extensionAttributes->setProductSku(TaxifyConstants::ITEM_SHIPPING_SKU);
        $extensionAttributes->setProductName(TaxifyConstants::ITEM_SHIPPING_NAME);

        return $quoteDetailsItem;
    }

    /**
     * Retrieve an extension attribute object for the QuoteDetailsItem
     *
     * @param QuoteDetailsItemInterface $quoteDetailsItem
     *
     * @return QuoteDetailsItemExtensionInterface
     */
    private function getExtensionAttributes(QuoteDetailsItemInterface $quoteDetailsItem)
    {
        $extensionAttributes = $quoteDetailsItem->getExtensionAttributes();
        if ($extensionAttributes instanceof QuoteDetailsItemExtensionInterface) {
            return $extensionAttributes;
        }

        $extensionAttributes = $this->extensionFactory->create();
        $quoteDetailsItem->setExtensionAttributes($extensionAttributes);

        return $extensionAttributes;
    }
}
