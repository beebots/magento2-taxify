<?php
namespace BeeBots\Taxify\Helper;

use Magento\Tax\Api\Data\QuoteDetailsItemInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Class PriceForTax
 *
 * @package BeeBots\Taxify\Helper
 */
class PriceForTax
{
    /** @var PriceCurrencyInterface */
    private $calculationTool;

    /**
     * PriceForTax constructor.
     *
     * @param PriceCurrencyInterface $calculationTool
     */
    public function __construct(
        PriceCurrencyInterface $calculationTool
    ) {
        $this->calculationTool = $calculationTool;
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
}
