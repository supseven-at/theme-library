<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Psr\Http\Message\ServerRequestInterface;
use Supseven\ThemeLibrary\Attributes\AsContentObject;
use Supseven\ThemeLibrary\Attributes\AsDataProcessor;
use Supseven\ThemeLibrary\Service\DependencyValuesService;
use Supseven\ThemeLibrary\Service\FlashMessager;
use Supseven\ThemeLibrary\Service\PageCacheService;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Frontend\ContentObject\AbstractContentObject;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder): void {
    $services = $container->services();
    $services->defaults()->public()->autowire()->autoconfigure();

    $containerBuilder->registerAttributeForAutoconfiguration(
        AsContentObject::class,
        static function (ChildDefinition $definition, AsContentObject $attribute, \ReflectionClass $class): void {
            $parents = [];
            $parent = $class;

            while ($parent) {
                $parents[] = $parent->name;
                $parent = $parent->getParentClass();
            }

            if (!in_array(AbstractContentObject::class, $parents)) {
                throw new AutoconfigureFailedException(sprintf(
                    'Class "%s" must extend "%s" to be configured as a content object',
                    $class->name,
                    AbstractContentObject::class,
                ));
            }

            $definition->setPublic(true);
            $definition->setShared(false);
            $definition->addTag(AsContentObject::TAG_NAME, ['identifier' => $attribute->name]);
        }
    );

    $containerBuilder->registerAttributeForAutoconfiguration(
        AsDataProcessor::class,
        static function (ChildDefinition $definition, AsDataProcessor $attribute, \ReflectionClass $class): void {
            if (!$class->implementsInterface(DataProcessorInterface::class)) {
                throw new AutoconfigureFailedException(sprintf(
                    'Class "%s" must implement "%s" to be configured as a data processor',
                    $class->name,
                    DataProcessorInterface::class,
                ));
            }

            $definition->setPublic(true);
            $definition->setShared($attribute->shared);
            $definition->addTag(AsDataProcessor::TAG_NAME, ['identifier' => $attribute->shortName]);
        }
    );

    $services->set(DependencyValuesService::class)->public()->share();

    $services->set('typo3.request', ServerRequestInterface::class)
        ->factory([service(DependencyValuesService::class), 'getRequest'])
        ->share(false)
        ->autowire(false)
        ->autoconfigure(false)
        ->lazy()
        ->alias(ServerRequestInterface::class, 'typo3.request');

    $services->set('typo3.app.context', ApplicationContext::class)
        ->factory([service(DependencyValuesService::class), 'getApplicationContext'])
        ->share(false)
        ->autowire(false)
        ->autoconfigure(false)
        ->lazy()
        ->alias(ApplicationContext::class, 'typo3.app.context');

    $services->set('typo3.app.type', ApplicationType::class)
        ->factory([service(DependencyValuesService::class), 'getApplicationType'])
        ->share(false)
        ->autowire(false)
        ->autoconfigure(false)
        ->alias(ApplicationType::class, 'typo3.app.type');

    $services->set('typo3.lang', LanguageService::class)
        ->factory([service(DependencyValuesService::class), 'getLanguageService'])
        ->autowire(false)
        ->autoconfigure(false);

    $services->set('typo3.slugs.pages', SlugHelper::class)
        ->share(false)
        ->class(SlugHelper::class)
        ->factory([service(DependencyValuesService::class), 'createSlugHelper'])
        ->args(['pages', 'slug']);

    $services->set('typo3.slugs.content', SlugHelper::class)
        ->share(false)
        ->class(SlugHelper::class)
        ->factory([service(DependencyValuesService::class), 'createSlugHelper'])
        ->args(['tt_content', 'title']);

    $services->set(PageCacheService::class)->share()->lazy();

    $services->set(FlashMessager::class)->share();
};
