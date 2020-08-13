<?php

namespace BeeBots\Taxify\Model;

use BeeBots\Taxify\Helper\PriceForTax;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Api\Data\AppliedTaxInterface;
use Magento\Tax\Api\Data\AppliedTaxInterfaceFactory;
use Magento\Tax\Api\Data\AppliedTaxRateInterface;
use Magento\Tax\Api\Data\AppliedTaxRateInterfaceFactory;
use Magento\Tax\Api\Data\QuoteDetailsInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Tax\Api\Data\TaxDetailsInterface;
use Magento\Tax\Api\Data\TaxDetailsInterfaceFactory;
use Magento\Tax\Api\Data\TaxDetailsItemInterface;
use Magento\Tax\Api\Data\TaxDetailsItemInterfaceFactory;
use Psr\Log\LoggerInterface;
use rk\Taxify\Responses\CalculateTax;
use rk\Taxify\TaxLine;

class Calculator
{
    /** @var TaxDetailsInterfaceFactory */
    private $taxDetailsFactory;

    /** @var TaxDetailsItemInterfaceFactory */
    private $taxDetailsItemFactory;

    /** @var AppliedTaxInterfaceFactory */
    private $appliedTaxFactory;

    /** @var AppliedTaxRateInterfaceFactory */
    private $appliedTaxRateFactory;

    /** @var PriceCurrencyInterface */
    private $priceCurrency;

    /** @var LoggerInterface */
    private $logger;

    /** @var Config */
    private $config;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var TaxifyApi */
    private $taxifyApi;

    /** @var PriceForTax */
    private $priceForTax;

    /**
     * Calculator constructor.
     *
     * @param TaxDetailsInterfaceFactory $taxDetailsFactory
     * @param TaxDetailsItemInterfaceFactory $taxDetailsItemFactory
     * @param AppliedTaxInterfaceFactory $appliedTaxFactory
     * @param AppliedTaxRateInterfaceFactory $appliedTaxRateFactory
     * @param PriceCurrencyInterface $priceCurrency
     * @param LoggerInterface $logger
     * @param Config $config
     * @param ManagerInterface $messageManager
     * @param TaxifyApi $taxifyApi
     * @param PriceForTax $priceForTax
     */
    public function __construct(
        TaxDetailsInterfaceFactory $taxDetailsFactory,
        TaxDetailsItemInterfaceFactory $taxDetailsItemFactory,
        AppliedTaxInterfaceFactory $appliedTaxFactory,
        AppliedTaxRateInterfaceFactory $appliedTaxRateFactory,
        PriceCurrencyInterface $priceCurrency,
        LoggerInterface $logger,
        Config $config,
        ManagerInterface $messageManager,
        TaxifyApi $taxifyApi,
        PriceForTax $priceForTax
    ) {
        $this->taxDetailsFactory = $taxDetailsFactory;
        $this->taxDetailsItemFactory = $taxDetailsItemFactory;
        $this->appliedTaxFactory = $appliedTaxFactory;
        $this->appliedTaxRateFactory = $appliedTaxRateFactory;
        $this->priceCurrency = $priceCurrency;
        $this->logger = $logger;
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->taxifyApi = $taxifyApi;
        $this->priceForTax = $priceForTax;
    }

    /**
     * Calculate Taxes
     *
     * @param QuoteDetailsInterface $quoteDetails
     * @param CalculateTax $result
     * @param bool $round
     *
     * @return TaxDetailsInterface
     */
    public function calculateTax(QuoteDetailsInterface $quoteDetails, CalculateTax $result, $round = true)
    {
        /** @var TaxLine[] $resultItems */
        $resultItems = [];
        foreach ($result->getLines() as $lineItem) {
            //TODO: Make sure line number is the right thing to use here
            $resultItems[$lineItem->getLineNumber()] = $lineItem;
        }

        /** @var TaxDetailsInterface $taxDetails */
        $taxDetails = $this->taxDetailsFactory->create();
        $taxDetails->setSubtotal(0)
            ->setTaxAmount(0)
            ->setAppliedTaxes([]);

        /** @var QuoteDetailsItemInterface[] $processItems Line items we need to process taxes for */
        $processItems = [];
        /** @var QuoteDetailsItemInterface[] $childrenByParent Child line items indexed by parent code */
        $childrenByParent = [];
        /** @var TaxDetailsItemInterface[] $processedItems Processed Line items */
        $processedItems = [];

        /*
         * Here we separate items into top-level and child items.  The children will be processed separately and then
         * added together for the parent item
         */
        foreach ($quoteDetails->getItems() as $item) {
            if ($item->getParentCode()) {
                $childrenByParent[$item->getParentCode()][] = $item;
            } else {
                $processItems[$item->getCode()] = $item;
            }
        }

        foreach ($processItems as $item) {
            if (isset($childrenByParent[$item->getCode()])) { // If this top-level item has child products
                /** @var TaxDetailsItemInterface[] $processedChildren To be used to figure out our top-level details */
                $processedChildren = [];

                // Process the children first, our top-level product will be the combination of them
                foreach ($childrenByParent[$item->getCode()] as $child) {
                    /** @var QuoteDetailsItemInterface $child */

                    $resultItem = $resultItems[$child->getCode()];
                    $processedItem = $resultItem
                        ? $this->createTaxDetailsItem($child, $resultItem, $round)
                        : $this->createEmptyDetailsTaxItem($child);

                    // Add this item's tax information to the quote aggregate
                    $this->aggregateTaxData($taxDetails, $processedItem);

                    $processedItems[$processedItem->getCode()] = $processedItem;
                    $processedChildren[] = $processedItem;
                }
                /** @var TaxDetailsItemInterface $processedItem */
                $processedItem = $this->taxDetailsItemFactory->create();
                $processedItem->setCode($item->getCode())
                    ->setType($item->getType());

                $rowTotal = 0.0;
                $rowTotalInclTax = 0.0;
                $rowTax = 0.0;
                // Combine the totals from the children
                foreach ($processedChildren as $child) {
                    $rowTotal += $child->getRowTotal();
                    $rowTotalInclTax += $child->getRowTotalInclTax();
                    $rowTax += $child->getRowTax();
                }

                $price = $rowTotal / $item->getQuantity();
                $priceInclTax = $rowTotalInclTax / $item->getQuantity();

                $processedItem->setPrice($this->optionalRound($price, $round))
                    ->setPriceInclTax($this->optionalRound($priceInclTax, $round))
                    ->setRowTotal($this->optionalRound($rowTotal, $round))
                    ->setRowTotalInclTax($this->optionalRound($rowTotalInclTax, $round))
                    ->setRowTax($this->optionalRound($rowTax, $round));
            // Aggregation to $taxDetails takes place on the child level
            } else {
                $resultItem = $resultItems[$item->getCode()];
                $processedItem = $resultItem
                    ? $this->createTaxDetailsItem($item, $resultItem, $round)
                    : $this->createEmptyDetailsTaxItem($item);

                $this->aggregateTaxData($taxDetails, $processedItem);
            }

            $processedItems[$item->getCode()] = $processedItem;
        }
        $taxDetails->setItems($processedItems);

        return $taxDetails;
    }

    /**
     * Add tax details from an item to the overall tax details
     *
     * @param TaxDetailsInterface $taxDetails
     * @param TaxDetailsItemInterface $taxItemDetails
     * @return void
     */
    private function aggregateTaxData(TaxDetailsInterface $taxDetails, TaxDetailsItemInterface $taxItemDetails)
    {
        $taxDetails->setSubtotal($taxDetails->getSubtotal() + $taxItemDetails->getRowTotal());
        $taxDetails->setTaxAmount($taxDetails->getTaxAmount() + $taxItemDetails->getRowTax());

        $itemAppliedTaxes = $taxItemDetails->getAppliedTaxes();
        if (empty($itemAppliedTaxes)) {
            return;
        }

        $appliedTaxes = $taxDetails->getAppliedTaxes();
        foreach ($itemAppliedTaxes as $taxId => $itemAppliedTax) {
            if (!isset($appliedTaxes[$taxId])) {
                $rates = [];
                $itemRates = $itemAppliedTax->getRates();
                foreach ($itemRates as $rate) {
                    /** @var AppliedTaxRateInterface $newRate */
                    $newRate = $this->appliedTaxRateFactory->create();
                    $newRate->setPercent($rate->getPercent())
                        ->setTitle($rate->getTitle())
                        ->setCode($rate->getCode());
                    $rates[] = $newRate;
                }

                /** @var AppliedTaxInterface $appliedTax */
                $appliedTax = $this->appliedTaxFactory->create();
                $appliedTax->setPercent($itemAppliedTax->getPercent())
                    ->setAmount($itemAppliedTax->getAmount())
                    ->setTaxRateKey($itemAppliedTax->getTaxRateKey())
                    ->setRates($rates);
            } else {
                $appliedTaxes[$taxId]->setAmount($appliedTaxes[$taxId]->getAmount() + $itemAppliedTax->getAmount());
            }
        }
        $taxDetails->setAppliedTaxes($appliedTaxes);
    }

    /**
     * Format a {@see TaxLine} into applied taxes
     *
     * @param TaxLine $taxifyLineItem
     * @return AppliedTaxInterface[]
     */
    private function createAppliedTaxes(TaxLine $taxifyLineItem)
    {
        $taxDetailType = $taxifyLineItem->getItemTaxabilityCode() === TaxifyConstants::ITEM_TAX_CODE_FREIGHT
            ? TaxifyConstants::TAX_DETAIL_TYPE_SHIPPING
            : TaxifyConstants::TAX_DETAIL_TYPE_PRODUCT_AND_SHIPPING;

        $appliedTax = $this->appliedTaxFactory->create();
        $appliedTax->setAmount($taxifyLineItem->getAmount());
        $appliedTax->setPercent($taxifyLineItem->getTaxRate() * 100);
        $appliedTax->setTaxRateKey($taxDetailType);
        $rate = $this->appliedTaxRateFactory->create()
            ->setPercent($taxifyLineItem->getTaxRate() * 100)
            ->setCode($taxDetailType)
            ->setTitle($this->getTaxLabel($taxDetailType));

        $appliedTax->setRates([$rate]);

        return [ $taxDetailType => $appliedTax];
    }

    /**
     * Create an empty {@see TaxDetailsInterface}
     *
     * This method is used to provide Magento the information it expects while
     * avoiding a costly tax calculation when we don't want one (or think it
     * will provide no value)
     *
     * @param QuoteDetailsInterface $quoteDetails
     * @return TaxDetailsInterface
     */
    private function createEmptyDetails(QuoteDetailsInterface $quoteDetails)
    {
        /** @var TaxDetailsInterface $details */
        $details = $this->taxDetailsFactory->create();

        $subtotal = 0;
        $items = [];

        foreach ($quoteDetails->getItems() as $quoteItem) {
            $taxItem = $this->createEmptyDetailsTaxItem($quoteItem);
            $subtotal += $taxItem->getRowTotal();
            // Magento has an undocumented assumption that tax detail items are indexed by code
            $items[$taxItem->getCode()] = $taxItem;
        }

        $details->setSubtotal($subtotal)
            ->setTaxAmount(0)
            ->setDiscountTaxCompensationAmount(0)
            ->setAppliedTaxes([])
            ->setItems($items);

        return $details;
    }

    /**
     * Create an empty {@see TaxDetailsItemInterface}
     *
     * This is used by {@see self::createEmptyDetails()}
     *
     * @param QuoteDetailsItemInterface $quoteDetailsItem
     * @return TaxDetailsItemInterface
     */
    private function createEmptyDetailsTaxItem(QuoteDetailsItemInterface $quoteDetailsItem)
    {
        /** @var TaxDetailsItemInterface $taxDetailsItem */
        $taxDetailsItem = $this->taxDetailsItemFactory->create();

        $rowTotal = ($quoteDetailsItem->getUnitPrice() * $quoteDetailsItem->getQuantity()) -
            $quoteDetailsItem->getDiscountAmount();

        $taxDetailsItem->setCode($quoteDetailsItem->getCode())
            ->setType($quoteDetailsItem->getType())
            ->setRowTax(0)
            ->setPrice($quoteDetailsItem->getUnitPrice())
            ->setPriceInclTax($quoteDetailsItem->getUnitPrice())
            ->setRowTotal($rowTotal)
            ->setRowTotalInclTax($rowTotal)
            ->setDiscountTaxCompensationAmount(0)
            ->setDiscountAmount($quoteDetailsItem->getDiscountAmount())
            ->setAssociatedItemCode($quoteDetailsItem->getAssociatedItemCode())
            ->setTaxPercent(0)
            ->setAppliedTaxes([]);

        return $taxDetailsItem;
    }

    /**
     * Function: createTaxDetailsItem
     *
     * @param QuoteDetailsItemInterface $quoteDetailsItem
     * @param TaxLine $taxifyLineItem
     * @param bool $round
     *
     * @return TaxDetailsItemInterface
     */
    private function createTaxDetailsItem(
        QuoteDetailsItemInterface $quoteDetailsItem,
        TaxLine $taxifyLineItem,
        $round = true
    ) {
        // Combine the rates of all taxes applicable to the Line Item
        $effectiveRate = $taxifyLineItem->getTaxRate() ?? 0;

        $perItemTax = $taxifyLineItem->getSalesTaxAmount() / $taxifyLineItem->getQuantity();
        $unitPrice = $quoteDetailsItem->getUnitPrice();
        $extendedPrice = $this->priceForTax->getOriginalItemPriceOnQuote($quoteDetailsItem);

        /** @var TaxDetailsItemInterface $taxDetailsItem */
        $taxDetailsItem = $this->taxDetailsItemFactory->create();

        $taxDetailsItem->setCode($taxifyLineItem->getLineNumber())
            ->setType($quoteDetailsItem->getType())
            ->setRowTax($this->optionalRound($taxifyLineItem->getSalesTaxAmount(), $round))
            ->setPrice($this->optionalRound($unitPrice, $round))
            ->setPriceInclTax($this->optionalRound($unitPrice + $perItemTax, $round))
            ->setRowTotal($this->optionalRound($extendedPrice, $round))
            ->setRowTotalInclTax($this->optionalRound($extendedPrice + $taxifyLineItem->getSalesTaxAmount(), $round))
            ->setDiscountTaxCompensationAmount(0)
            ->setAssociatedItemCode($quoteDetailsItem->getAssociatedItemCode())
            ->setTaxPercent($effectiveRate * 100)
            ->setAppliedTaxes($this->createAppliedTaxes($taxifyLineItem));

        return $taxDetailsItem;
    }

    /**
     * Determine if an array of QuoteDetailsItemInterface contains only shipping entries
     *
     * @param QuoteDetailsItemInterface[] $items
     * @return bool
     */
    private function onlyShipping(array $items)
    {
        foreach ($items as $item) {
            if ($item->getCode() !== 'shipping') {
                return false;
            }
        }

        return true;
    }

    /**
     * Round a number
     *
     * @param number $number
     * @param bool $round
     * @return float
     */
    private function optionalRound($number, $round = true)
    {
        return $round ? $this->priceCurrency->round($number) : $number;
    }

    /**
     * Retrieve tax label
     *
     * @param $code
     * @return string
     */
    private function getTaxLabel($code)
    {
        if ($code === TaxifyConstants::TAX_DETAIL_TYPE_PRODUCT_AND_SHIPPING) {
            return __('Sales and Use')->render();
        }

        if ($code === TaxifyConstants::TAX_DETAIL_TYPE_SHIPPING) {
            return __('Shipping')->render();
        }

        return $code;
    }
}
