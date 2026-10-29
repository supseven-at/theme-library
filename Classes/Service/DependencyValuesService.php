<?php

declare(strict_types=1);

namespace Supseven\ThemeLibrary\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Factory for "value-services"
 *
 * Not for scalar values, but value objects or services
 * initialized by context specific values
 *
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class DependencyValuesService
{
    public function __construct(
        protected LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    /**
     * Get the current request
     *
     * @return ServerRequestInterface
     */
    public function getRequest(): ServerRequestInterface
    {
        if (empty($GLOBALS['TYPO3_REQUEST'])) {
            throw new \UnexpectedValueException('TYPO3 request not available');
        }

        return $GLOBALS['TYPO3_REQUEST'];
    }

    /**
     * Get the current application context
     *
     * @return ApplicationContext
     */
    public function getApplicationContext(): ApplicationContext
    {
        return Environment::getContext();
    }

    /**
     * Get the current application type
     *
     * @return ApplicationType
     */
    public function getApplicationType(): ApplicationType
    {
        return ApplicationType::fromRequest($this->getRequest());
    }

    /**
     * Get the logged in backend user if available
     *
     * @return BackendUserAuthentication|null
     */
    public function getBackendUser(): ?BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'] ?? null;
    }

    /**
     * Get a language service based on the current environment
     *
     * @return LanguageService
     */
    public function getLanguageService(): LanguageService
    {
        if (!empty($GLOBALS['LANG'])) {
            return $GLOBALS['LANG'];
        }

        if (!empty($GLOBALS['TYPO3_REQUEST'])) {
            $language = $this->getRequest()->getAttribute('language');

            if ($language instanceof SiteLanguage) {
                return $this->languageServiceFactory->createFromSiteLanguage($this->getRequest()->getAttribute('language'));
            }
        }

        if ($this->getBackendUser()) {
            return $this->languageServiceFactory->createFromUserPreferences($this->getBackendUser());
        }

        return $this->languageServiceFactory->create('en');
    }

    public function createSlugHelper(string $table, string $field): SlugHelper
    {
        $settings = $GLOBALS['TCA'][$table]['columns'][$field]['config']['generatorOptions'] ?? [];

        return GeneralUtility::makeInstance(
            SlugHelper::class,
            $table,
            $field,
            $settings
        );
    }
}
