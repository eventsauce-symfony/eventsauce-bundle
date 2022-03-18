<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\Attribute\AsSynchronousMessageConsumer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Factory\MessageDispatcherChainFactory;
use Andreo\EventSauceBundle\Factory\SynchronousMessageDispatcherFactory;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use LogicException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class SynchronousMessageDispatcherLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $messageDispatcherConfig = $config['synchronous_message_dispatcher'];
        if (!$this->extension->isConfigEnabled($this->container, $messageDispatcherConfig)) {
            return;
        }

        $dispatcherChainConfig = $messageDispatcherConfig['chain'];
        $aclConfig = $config['acl'];
        $outboundConfig = $aclConfig['outbound'];
        $outboundAclEnabled = $this->extension->isConfigEnabled($this->container, $outboundConfig);

        $this->container->registerAttributeForAutoconfiguration(
            AsSynchronousMessageConsumer::class,
            static function (ChildDefinition $definition, AsSynchronousMessageConsumer $attribute): void {
                $dispatcherAlias = $attribute->dispatcher;
                $definition->addTag("andreo.eventsauce.message_consumer.$dispatcherAlias");
            }
        );

        $dispatcherChainReferences = [];
        foreach ($dispatcherChainConfig as $dispatcherAlias => $dispatcherConfig) {
            $dispatcherDef = $this->container
                ->register("andreo.eventsauce.message_dispatcher.$dispatcherAlias", SynchronousMessageDispatcher::class)
                ->setFactory([SynchronousMessageDispatcherFactory::class, 'create'])
                ->addArgument(new TaggedIteratorArgument("andreo.eventsauce.message_consumer.$dispatcherAlias"))
                ->setPublic(false)
            ;

            if ($this->extension->isConfigEnabled($this->container, $dispatcherConfig['acl'])) {
                if (!$outboundAclEnabled) {
                    throw new LogicException('Default acl outbound config is disabled. If you want to use it, enable and configure it.');
                }

                $dispatcherDef->addTag('andreo.eventsauce.acl_outbound');

                $outboundFilterChainConfig = $aclConfig['filter_chain'];
                $dispatcherDef->addTag('andreo.eventsauce.acl.filter_chain', [
                    'before' => $outboundFilterChainConfig['before'],
                    'after' => $outboundFilterChainConfig['after'],
                ]);
            }

            $this->container->setAlias($dispatcherAlias, "andreo.eventsauce.message_dispatcher.$dispatcherAlias");
            $this->container->registerAliasForArgument($dispatcherAlias, MessageDispatcher::class);
            $dispatcherChainReferences[] = new Reference($dispatcherAlias);
        }

        $this->container
            ->register(
                'andreo.eventsauce.message_dispatcher_chain',
                MessageDispatcherChain::class
            )
            ->setFactory([MessageDispatcherChainFactory::class, 'create'])
            ->addArgument(new IteratorArgument($dispatcherChainReferences))
            ->setPublic(false)
        ;
    }
}
