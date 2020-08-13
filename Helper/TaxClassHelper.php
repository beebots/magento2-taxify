<?php
namespace BeeBots\Taxify\Helper;

use BeeBots\Taxify\Model\Config;
use BeeBots\Taxify\Model\TaxifyConstants;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Class TaxClassHelper
 *
 * @package BeeBots\Taxify\Helper
 */
class TaxClassHelper
{
    /** @var TaxClassRepositoryInterface */
    private $taxClassRepository;

    /** @var Config */
    private $taxifyConfig;

    /** @var LoggerInterface */
    private $logger;

    /**
     * TaxClassHelper constructor.
     *
     * @param TaxClassRepositoryInterface $taxClassRepository
     * @param Config $taxifyConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        TaxClassRepositoryInterface $taxClassRepository,
        Config $taxifyConfig,
        LoggerInterface $logger
    ) {
        $this->taxClassRepository = $taxClassRepository;
        $this->taxifyConfig = $taxifyConfig;
        $this->logger = $logger;
    }

    /**
     * Function: getMagentoTaxClassNameById
     *
     * @param int $taxClassId
     *
     * @return string
     */
    public function getMagentoTaxClassNameById(int $taxClassId)
    {
        if (! $taxClassId) {
            return 'None';
        }

        try {
            return $this->taxClassRepository->get($taxClassId)
                ->getClassName();
        } catch (NoSuchEntityException $exception) {
            $this->logger->critical($exception);
            return 'None';
        }
    }

    /**
     * Function: getTaxifyTaxabilityCodeFromMagentoTaxClassName
     *
     * @param string $taxClassName
     *
     * @return string
     */
    public function getTaxifyTaxabilityCodeFromMagentoTaxClassName(string $taxClassName)
    {
        switch ($taxClassName) {
            case $this->taxifyConfig->getMageTaxClassNameForCandy():
                return TaxifyConstants::ITEM_TAX_CODE_CANDY;
            case $this->taxifyConfig->getMageTaxClassNameForClothing():
                return TaxifyConstants::ITEM_TAX_CODE_CLOTHING;
            case $this->taxifyConfig->getMageTaxClassNameForExemptservice():
                return TaxifyConstants::ITEM_TAX_CODE_EXEMPTSERVICE;
            case $this->taxifyConfig->getMageTaxClassNameForFood():
                return TaxifyConstants::ITEM_TAX_CODE_FOOD;
            case $this->taxifyConfig->getMageTaxClassNameForFoodservice():
                return TaxifyConstants::ITEM_TAX_CODE_FOODSERVICE;
            case $this->taxifyConfig->getMageTaxClassNameForFreight():
                return TaxifyConstants::ITEM_TAX_CODE_FREIGHT;
            case $this->taxifyConfig->getMageTaxClassNameForInstallation():
                return TaxifyConstants::ITEM_TAX_CODE_INSTALLATION;
            case $this->taxifyConfig->getMageTaxClassNameForNontax():
                return TaxifyConstants::ITEM_TAX_CODE_NONTAX;
            case $this->taxifyConfig->getMageTaxClassNameForProservice():
                return TaxifyConstants::ITEM_TAX_CODE_PROSERVICE;
            case $this->taxifyConfig->getMageTaxClassNameForSupplements():
                return TaxifyConstants::ITEM_TAX_CODE_SUPPLEMENTS;
            default:
                return TaxifyConstants::ITEM_TAX_CODE_TAXABLE;
        }
    }
}
