<?php
namespace BeeBots\Taxify\Model;

class TaxifyConstants
{
    const CUST_TAX_CODE_RETAIL = "RETAIL";
    const CUST_TAX_CODE_RESALE = "RESALE";
    const CUST_TAX_CODE_NONPROFIT = "NONPROFIT";
    const CUST_TAX_CODE_MANUFACTURING = "MANUFACTURING";
    const CUST_TAX_CODE_GOVERNMENT = "GOVERNMENT";

    const ITEM_TAX_CODE_CANDY = "CANDY";
    const ITEM_TAX_CODE_CLOTHING = "CLOTHING";
    const ITEM_TAX_CODE_EXEMPTSERVICE = "EXEMPTSERVICE";
    const ITEM_TAX_CODE_FOOD = "FOOD";
    const ITEM_TAX_CODE_FOODSERVICE = "FOODSERVICE";
    const ITEM_TAX_CODE_FREIGHT = "FREIGHT";
    const ITEM_TAX_CODE_INSTALLATION = "INSTALLATION";
    const ITEM_TAX_CODE_NONTAX = "NONTAX";
    const ITEM_TAX_CODE_PROSERVICE = "PROSERVICE";
    const ITEM_TAX_CODE_SUPPLEMENTS = "SUPPLEMENTS";
    const ITEM_TAX_CODE_TAXABLE = "TAXABLE";

    const ITEM_SHIPPING_SKU = "SHIPPING";
    const ITEM_SHIPPING_NAME = "Shipping";

    const TAX_DETAIL_TYPE_SHIPPING = "shipping";
    const TAX_DETAIL_TYPE_PRODUCT_AND_SHIPPING = "product_and_shipping";

    const MESSAGE_KEY = 'taxify-messages';
}
