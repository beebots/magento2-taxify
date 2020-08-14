<?php
namespace BeeBots\Taxify\Plugin;

use BeeBots\Taxify\Helper\TaxClassHelper;
use BeeBots\Taxify\Model\Calculator;
use BeeBots\Taxify\Model\Config;
use BeeBots\Taxify\Model\TaxifyApi;
use Magento\Tax\Api\Data\QuoteDetailsInterface;
use Magento\Tax\Api\Data\TaxClassKeyInterface;
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

    /** @var TaxClassHelper */
    private $taxClassHelper;

    /**
     * TaxCalculationPlugin constructor.
     *
     * @param TaxifyApi $taxifyApi
     * @param Config $config
     * @param Calculator $calculator
     * @param TaxClassHelper $taxClassHelper
     */
    public function __construct(
        TaxifyApi $taxifyApi,
        Config $config,
        Calculator $calculator,
        TaxClassHelper $taxClassHelper
    ) {
        $this->taxifyApi = $taxifyApi;
        $this->config = $config;
        $this->calculator = $calculator;
        $this->taxClassHelper = $taxClassHelper;
    }

    /**
     * Function: aroundCalculateTax
     *
     * @param TaxCalculation $taxCalculation
     * @param callable $super
     * @param mixed ...$arguments
     *
     * @return TaxDetailsInterface
     */
    public function aroundCalculateTax(
        TaxCalculation $taxCalculation,
        callable $super,
        ...$arguments
    ) {
        $quoteDetails = $arguments[0];
        $round = $arguments[2] ?? true;

        if (! $this->config->isEnabled()
            || !$this->quoteIsUsableForTaxifyCall($quoteDetails)
            || $this->customerTaxClassIsExempt($quoteDetails)) {
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

    /**
     * Function: quoteIsUsableForTaxifyCall
     *
     * @param $quoteDetails
     *
     * @return bool
     */
    private function quoteIsUsableForTaxifyCall($quoteDetails)
    {
        $items = $quoteDetails->getItems();
        return ! empty($items)
            && ! ($quoteDetails->getBillingAddress() === null && $quoteDetails->getShippingAddress() === null);
    }

    /**
     * Function: customerTaxClassIsExempt
     *
     * @param $customerTaxClassId
     *
     * @return bool
     */
    public function customerTaxClassIsExempt(QuoteDetailsInterface $quoteDetails)
    {
        $customerTaxClassId = $quoteDetails->getCustomerTaxClassId();
        $taxClassKey = $quoteDetails->getCustomerTaxClassKey();
        if ($taxClassKey && $taxClassKey->getType() === TaxClassKeyInterface::TYPE_ID) {
            $customerTaxClassId = $taxClassKey->getValue();
        }

        if (! $customerTaxClassId) {
            return false;
        }

        $mageTaxClassName = $this->taxClassHelper->getMagentoTaxClassNameById($customerTaxClassId);
        $isExempt = $this->config->getMageTaxClassNameForExemptCustomer() === $mageTaxClassName;
        return $isExempt;
    }
}
