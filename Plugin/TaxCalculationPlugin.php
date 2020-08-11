<?php
namespace BeeBots\Taxify\Plugin;

use BeeBots\Taxify\Model\Config;
use BeeBots\Taxify\Model\TaxifyApi;
use Magento\Tax\Api\Data\TaxDetailsInterface;
use Magento\Tax\Model\TaxCalculation;

class TaxCalculationPlugin
{
    /** @var TaxifyApi */
    private $taxifyApi;

    /** @var Config */
    private $config;

    /**
     * TaxCalculationPlugin constructor.
     *
     * @param TaxifyApi $taxifyApi
     * @param Config $config
     */
    public function __construct(
        TaxifyApi $taxifyApi,
        Config $config
    ) {
        $this->taxifyApi = $taxifyApi;
        $this->config = $config;
    }

    public function aroundCalculateTax(
        TaxCalculation $taxCalculation,
        callable $super,
        ...$arguments
    ) {
        if (! $this->config->isEnabled()) {
            return $super(...$arguments);
        }
        //TODO: Validate request
        $quoteDetails = $arguments[0];
        $taxifyRateRequest = $this->taxifyApi->getTaxForQuote($quoteDetails);

        /** @var TaxDetailsInterface $taxDetailsDataObject */
        $taxDetailsDataObject = $super(...$arguments);
        return $taxDetailsDataObject;
    }

    protected function aroundProcessItem(
        TaxCalculation $taxCalculation,
        callable $proceed,
        ...$arguments
    ) {
        //TODO: check enabled
        //TODO: Validate request
        //TODO: Fallback to parent method
        return $proceed($arguments);
    }
}
