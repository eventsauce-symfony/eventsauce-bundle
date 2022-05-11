<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\Attribute\AsMessageFilterAfter;
use Andreo\EventSauceBundle\Attribute\AsMessageFilterBefore;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use Andreo\EventSauceBundle\Attribute\ForInboundAcl;
use Andreo\EventSauceBundle\Attribute\ForOutboundAcl;
use Andreo\EventSauceBundle\Attribute\InboundAcl;
use Andreo\EventSauceBundle\Attribute\OutboundAcl;
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
                OutboundAcl::class,
                static function (ChildDefinition $definition, OutboundAcl $attribute, Reflector $reflector) use ($outboundConfig): void {
                    assert($reflector instanceof ReflectionClass);
                    $definition->addTag('andreo.eventsauce.acl_outbound');

                    $filterStrategy = $outboundConfig['filter_strategy'];

                    $definition->addTag('andreo.eventsauce.acl.filter_strategy', [
                        'before' => $attribute->filterBeforeStrategy ?? $filterStrategy['before'],
                        'after' => $attribute->filterAfterStrategy ?? $filterStrategy['after'],
                    ]);
                }
            );
        }

        if ($inboundEnabled) {
            $this->container->registerAttributeForAutoconfiguration(
                InboundAcl::class,
                static function (ChildDefinition $definition, InboundAcl $attribute, Reflector $reflector) use ($inboundConfig): void {
                    assert($reflector instanceof ReflectionClass);
                    $definition->addTag('andreo.eventsauce.acl_inbound');

                    $filterStrategy = $inboundConfig['filter_strategy'];

                    $definition->addTag('andreo.eventsauce.acl.filter_strategy', [
                        'before' => $attribute->filterBeforeStrategy ?? $filterStrategy['before'],
                        'after' => $attribute->filterAfterStrategy ?? $filterStrategy['after'],
                    ]);
                }
            );
        }

        $this->container->registerAttributeForAutoconfiguration(
            AsMessageFilterBefore::class,
            static function (ChildDefinition $definition, AsMessageFilterBefore $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled): void {
                assert($reflector instanceof ReflectionClass);
                $inboundTargetReflections = $reflector->getAttributes(ForInboundAcl::class);
                $outboundTargetReflections = $reflector->getAttributes(ForOutboundAcl::class);

                if (empty($inboundTargetReflections) && empty($outboundTargetReflections)) {
                    if ($outboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_before', [
                            'priority' => $attribute->priority,
                        ]);
                    }
                    if ($inboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_before', [
                            'priority' => $attribute->priority,
                        ]);
                    }

                    return;
                }

                if ($outboundEnabled) {
                    foreach ($outboundTargetReflections as $outboundTargetReflection) {
                        /** @var ForOutboundAcl $outboundTargetAttr */
                        $outboundTargetAttr = $outboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_before', [
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
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_before', [
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
            AsMessageFilterAfter::class,
            static function (ChildDefinition $definition, AsMessageFilterAfter $attribute, Reflector $reflector) use ($outboundEnabled, $inboundEnabled): void {
                assert($reflector instanceof ReflectionClass);
                $inboundTargetReflections = $reflector->getAttributes(ForInboundAcl::class);
                $outboundTargetReflections = $reflector->getAttributes(ForOutboundAcl::class);

                if (empty($inboundTargetReflections) && empty($outboundTargetReflections)) {
                    if ($outboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_after', [
                            'priority' => $attribute->priority,
                        ]);
                    }
                    if ($inboundEnabled) {
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_after', [
                            'priority' => $attribute->priority,
                        ]);
                    }

                    return;
                }

                if ($outboundEnabled) {
                    foreach ($outboundTargetReflections as $outboundTargetReflection) {
                        /** @var ForOutboundAcl $outboundTargetAttr */
                        $outboundTargetAttr = $outboundTargetReflection->newInstance();
                        $definition->addTag('andreo.eventsauce.acl_outbound.filter_after', [
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
                        $definition->addTag('andreo.eventsauce.acl_inbound.filter_after', [
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
