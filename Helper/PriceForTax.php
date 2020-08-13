<?php
namespace BeeBots\Taxify\Helper;

use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Tax\Helper\Data as TaxHelper;

/**
 * Class PriceForTax
 *
 * @package BeeBots\Taxify\Helper
 */
class PriceForTax
{
    /** @var PriceCurrencyInterface */
    private $calculationTool;

    /** @var TaxHelper */
    private $taxHelper;

    /**
     * PriceForTax constructor.
     *
     * @param PriceCurrencyInterface $calculationTool
     * @param TaxHelper $taxHelper
     */
    public function __construct(
        PriceCurrencyInterface $calculationTool,
        TaxHelper $taxHelper
    ) {
        $this->calculationTool = $calculationTool;
        $this->taxHelper = $taxHelper;
    }

    /**
     * Function: getPriceForTaxCalculationFromQuoteItem
     *
     * @param QuoteDetailsItemInterface $item
     * @param float $price
     *
     * @return float
     */
    public function getPriceForTaxCalculationFromQuoteItem(QuoteDetailsItemInterface $item, float $price): float
    {
        if ($item->getExtensionAttributes() && $item->getExtensionAttributes()->getPriceForTaxCalculation()) {
            $priceForTaxCalculation = (float) $this->calculationTool->round(
                $item->getExtensionAttributes()->getPriceForTaxCalculation()
            );
        } else {
            $priceForTaxCalculation = $price;
        }

        return $priceForTaxCalculation;
    }

    /**
     * Function: getOriginalItemPriceOnQuote
     *
     * @param QuoteDetailsItemInterface $item
     *
     * @return float
     */
    public function getOriginalItemPriceOnQuote(QuoteDetailsItemInterface $item): float
    {
        return (float) $this->calculationTool->round($item->getUnitPrice() * $item->getQuantity());
    }

    /**
     * Function: getPriceForTaxCalculationFromOrderItem
     *
     * @param OrderItemInterface $orderItem
     * @param float $price
     *
     * @return float
     */
    public function getPriceForTaxCalculationFromOrderItem(OrderItemInterface $orderItem, float $price): float
    {
        $originalPrice = $orderItem->getOriginalPrice();
        $storeId = $orderItem->getStoreId();
        if ($originalPrice > $price && $this->taxHelper->applyTaxOnOriginalPrice($storeId)) {
            return (float) $originalPrice;
        }

        return $price;
    }
}
