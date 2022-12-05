<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use Andreo\EventSauceBundle\Attribute\EnableAcl;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class AclLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        array $config
    ): void {
        $aclEnabled = $extension->isConfigEnabled($container, $config['acl']);
        $container->setParameter('andreo.eventsauce.acl_enabled', $aclEnabled);
        if (!$aclEnabled) {
            return;
        }

        $container->registerAttributeForAutoconfiguration(
            EnableAcl::class,
            static function (ChildDefinition $definition, EnableAcl $attribute): void {
                $definition->addTag('andreo.eventsauce.acl', [
                    'message_filter_strategy_before_translate' => $attribute->beforeTranslate->value,
                    'message_filter_strategy_after_translate' => $attribute->afterTranslate->value,
                ]);
            }
        );

        $container->registerAttributeForAutoconfiguration(
            AsMessageFilter::class,
            static function (ChildDefinition $definition, AsMessageFilter $attribute): void {
                $definition->addTag('andreo.eventsauce.acl.message_filter', [
                    'priority' => $attribute->priority,
                    'trigger' => $attribute->trigger->value,
                    'owners' => $attribute->owners,
                ]);
            }
        );

        $container->registerAttributeForAutoconfiguration(
            AsMessageTranslator::class,
            static function (ChildDefinition $definition, AsMessageTranslator $attribute): void {
                $definition->addTag('andreo.eventsauce.acl.message_translator', [
                    'priority' => $attribute->priority,
                    'owners' => $attribute->owners,
                ]);
            }
        );
    }
}
