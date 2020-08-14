<?php

namespace BeeBots\Taxify\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class BeeBotsConfig
 *
 * @package BeeBots\AddressAutocomplete\Model
 */
class Config
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /**
     * LayoutProcessor constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Function: isAutocompleteEnabled
     *
     * @return mixed
     */
    public function isEnabled()
    {
        return $this->scopeConfig->isSetFlag('beebots/taxify/enabled');
    }

    /**
     * Function: getApiKey
     */
    public function getApiKey()
    {
        return $this->scopeConfig->getValue('beebots/taxify/taxify_api_key');
    }

    /**
     * Function: getMageTaxClassNameForCandy
     *
     * @return mixed
     */
    public function getMageTaxClassNameForCandy()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_candy');
    }

    /**
     * Function: getMageTaxClassNameForClothing
     *
     * @return mixed
     */
    public function getMageTaxClassNameForClothing()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_clothing');
    }

    /**
     * Function: getMageTaxClassNameForExemptservice
     *
     * @return mixed
     */
    public function getMageTaxClassNameForExemptservice()
    {
            return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_exemptservice');
    }

    /**
     * Function: getMageTaxClassNameForFood
     *
     * @return mixed
     */
    public function getMageTaxClassNameForFood()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_food');
    }

    /**
     * Function: getMageTaxClassNameForFoodservice
     *
     * @return mixed
     */
    public function getMageTaxClassNameForFoodservice()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_foodservice');
    }

    /**
     * Function: getMageTaxClassNameForFreight
     *
     * @return mixed
     */
    public function getMageTaxClassNameForFreight()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_freight');
    }

    /**
     * Function: getMageTaxClassNameForInstallation
     *
     * @return mixed
     */
    public function getMageTaxClassNameForInstallation()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_installation');
    }

    /**
     * Function: getMageTaxClassNameForNontax
     *
     * @return mixed
     */
    public function getMageTaxClassNameForNontax()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_nontax');
    }

    /**
     * Function: getMageTaxClassNameForProservice
     *
     * @return mixed
     */
    public function getMageTaxClassNameForProservice()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_proservice');
    }

    /**
     * Function: getMageTaxClassNameForSupplements
     *
     * @return mixed
     */
    public function getMageTaxClassNameForSupplements()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_supplements');
    }

    /**
     * Function: getMageTaxClassNameForTaxable
     *
     * @return mixed
     */
    public function getMageTaxClassNameForTaxable()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_taxable');
    }

    /**
     * Function: getMageTaxClassNameForExemptCustomer
     *
     * @return mixed
     */
    public function getMageTaxClassNameForExemptCustomer()
    {
        return $this->scopeConfig->getValue('beebots/taxify/mage_tax_class_name_for_exempt_customer');
    }
}
