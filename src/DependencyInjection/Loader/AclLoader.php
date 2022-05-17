<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use Andreo\EventSauceBundle\Attribute\ForInboundAcl;
use Andreo\EventSauceBundle\Attribute\ForOutboundAcl;
use Andreo\EventSauceBundle\Attribute\WithInboundAcl;
use Andreo\EventSauceBundle\Attribute\WithOutboundAcl;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use ReflectionClass;
use Reflector;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AclLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $aclConfig = $config['acl'];
        $aclEnabled = $this->extension->isConfigEnabled($this->container, $aclConfig);
        if (!$aclEnabled) {
            return;
        }
        $outboundConfig = $aclConfig['outbound'];
        $inboundConfig = $aclConfig['inbound'];
        if ($outboundEnabled = $this->extension->isConfigEnabled($this->container, $outboundConfig)) {
            $this->container->setParameter('andreo.eventsauce.acl_outbound_enabled', true);
        }
        if ($inboundEnabled = $this->extension->isConfigEnabled($this->container, $inboundConfig)) {
            $this->container->setParameter('andreo.eventsauce.acl_inbound_enabled', true);
        }

        if (!$outboundEnabled && !$inboundEnabled) {
            return;
        }

        if ($outboundEnabled) {
            $this->container->registerAttributeForAutoconfiguration(
                WithOutboundAcl::class,
                static function (ChildDefinition $definition, WithOutboundAcl $attribute, Reflector $reflector) use ($outboundConfig): void {
                    assert($reflector instanceof ReflectionClass);
                    $definition->addTag('andreo.eventsauce.acl_outbound');

                    $filterStrategy = $outboundConfig['filter_strategy'];

                    $definition->addTag('andreo.eventsauce.acl.filter_strategy', [
                        'before' => $attribute->beforeStrategy?->value ?? $filterStrategy['before'],
                        'after' => $attribute->afterStrategy?->value ?? $filterStrategy['after'],
                    ]);
                }
            );
        }

        if ($inboundEnabled) {
            $this->container->registerAttributeForAutoconfiguration(
                WithInboundAcl::class,
                static function (ChildDefinition $definition, WithInboundAcl $attribute, Reflector $reflector) use ($inboundConfig): void {
                    assert($reflector instanceof ReflectionClass);
                    $definition->addTag('andreo.eventsauce.acl_inbound');

                    $filterStrategy = $inboundConfig['filter_strategy'];

                    $definition->addTag('andreo.eventsauce.acl.filter_strategy', [
                        'before' => $attribute->beforeStrategy?->value ?? $filterStrategy['before'],
                        'after' => $attribute->afterStrategy?->value ?? $filterStrategy['after'],
                    ]);
                }
            );
        }

        $this->container->registerAttributeForAutoconfiguration(
            AsMessageFilter::class,
            static function (ChildDefinition $definition, AsMessageFilter $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled): void {
                assert($reflector instanceof ReflectionClass);
                $inboundTargetReflections = $reflector->getAttributes(ForInboundAcl::class);
                $outboundTargetReflections = $reflector->getAttributes(ForOutboundAcl::class);

                $position = $attribute->position->value;

                if (empty($inboundTargetReflections) && empty($outboundTargetReflections)) {
                    if ($outboundEnabled) {
                        $definition->addTag("andreo.eventsauce.acl_outbound.filter_$position", [
                            'priority' => $attribute->priority,
                        ]);
                    }
                    if ($inboundEnabled) {
                        $definition->addTag("andreo.eventsauce.acl_inbound.filter_$position", [
                            'priority' => $attribute->priority,
                        ]);
                    }

                    return;
                }

                if ($outboundEnabled) {
                    foreach ($outboundTargetReflections as $outboundTargetReflection) {
                        /** @var ForOutboundAcl $outboundTargetAttr */
                        $outboundTargetAttr = $outboundTargetReflection->newInstance();
                        $definition->addTag("andreo.eventsauce.acl_outbound.filter_$position", [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $outboundTargetAttr->target) {
                            $definition->addTag('andreo.eventsauce.acl_outbound_target', [
                                'id' => $outboundTargetAttr->target,
                            ]);
                        }
                    }
                }

                if ($inboundEnabled) {
                    foreach ($inboundTargetReflections as $inboundTargetReflection) {
                        /** @var ForInboundAcl $inboundTargetAttr */
                        $inboundTargetAttr = $inboundTargetReflection->newInstance();
                        $definition->addTag("andreo.eventsauce.acl_inbound.filter_$position", [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $inboundTargetAttr->target) {
                            $definition->addTag('andreo.eventsauce.acl_inbound_target', [
                                'id' => $inboundTargetAttr->target,
                            ]);
                        }
                    }
                }
            }
        );

        $this->container->registerAttributeForAutoconfiguration(
            AsMessageTranslator::class,
            static function (ChildDefinition $definition, AsMessageTranslator $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled): void {
                assert($reflector instanceof ReflectionClass);
                $inboundTargetReflections = $reflector->getAttributes(ForInboundAcl::class);
                $outboundTargetReflections = $reflector->getAttributes(ForOutboundAcl::class);

                if (empty($inboundTargetReflections) && empty($outboundTargetReflections)) {
                    if ($outboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_outbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                    }
                    if ($inboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_inbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                    }

                    return;
                }

                if ($outboundEnabled) {
                    foreach ($outboundTargetReflections as $outboundTargetReflection) {
                        /** @var ForOutboundAcl $outboundTargetAttr */
                        $outboundTargetAttr = $outboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_outbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $outboundTargetAttr->target) {
                            $definition->addTag('andreo.eventsauce.acl_outbound_target', [
                                'id' => $outboundTargetAttr->target,
                            ]);
                        }
                    }
                }

                if ($inboundEnabled) {
                    foreach ($inboundTargetReflections as $inboundTargetReflection) {
                        /** @var ForInboundAcl $inboundTargetAttr */
                        $inboundTargetAttr = $inboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_inbound.translator', [
                            'priority' => $attribute->priority,
                        ]);
                        if (null !== $inboundTargetAttr->target) {
                            $definition->addTag('andreo.eventsauce.acl_inbound_target', [
                                'id' => $inboundTargetAttr->target,
                            ]);
                        }
                    }
                }
            }
        );
    }
}
