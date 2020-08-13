<?php
namespace BeeBots\Taxify\Plugin;

use BeeBots\Taxify\Model\Calculator;
use BeeBots\Taxify\Model\Config;
use BeeBots\Taxify\Model\TaxifyApi;
use Magento\Tax\Api\Data\QuoteDetailsInterface;
use Magento\Tax\Api\Data\TaxDetailsInterface;
use Magento\Tax\Model\TaxCalculation;

class TaxCalculationPlugin
{
    /** @var TaxifyApi */
    private $taxifyApi;

    /** @var Config */
    private $config;

    /** @var Calculator */
    private $calculator;

    /**
     * TaxCalculationPlugin constructor.
     *
     * @param TaxifyApi $taxifyApi
     * @param Config $config
     * @param Calculator $calculator
     */
    public function __construct(
        TaxifyApi $taxifyApi,
        Config $config,
        Calculator $calculator
    ) {
        $this->taxifyApi = $taxifyApi;
        $this->config = $config;
        $this->calculator = $calculator;
    }

    public function aroundCalculateTax(
        TaxCalculation $taxCalculation,
        callable $super,
        ...$arguments
    ) {
        $quoteDetails = $arguments[0];
        $round = $arguments[2] ?? true;

        if (! $this->config->isEnabled()
            || !$this->quoteIsUsableForTaxifyCall($quoteDetails)) {
            return $super(...$arguments);
        }

        // Get the rates from taxify
        $response = $this->taxifyApi->getTaxForQuote($quoteDetails);

        // Fallback to default tax behavior if the request is invalid
        if (! $response) {
            return $super(...$arguments);
        }

        $taxDetailsDataObject = $this->calculator->calculateTax($quoteDetails, $response, $round);

        return $taxDetailsDataObject;
    }

    /**
     * Retrieve current Store ID
     *
     * @param string|null $storeId
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getStoreId($storeId)
    {
        return $storeId ?: $this->storeManager->getStore()->getStoreId();
    }

    private function quoteIsUsableForTaxifyCall($quoteDetails)
    {
        $items = $quoteDetails->getItems();
        return ! empty($items)
            && ! ($quoteDetails->getBillingAddress() === null && $quoteDetails->getShippingAddress() === null);
    }
}
