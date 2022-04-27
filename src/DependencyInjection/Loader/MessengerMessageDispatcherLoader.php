<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Messenger\MessengerMessageDispatcher;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Factory\MessageDispatcherChainFactory;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageDispatcherChain;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

final class MessengerMessageDispatcherLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $messageDispatcherConfig = $config['messenger_message_dispatcher'];
        if (!$this->extension->isConfigEnabled($this->container, $messageDispatcherConfig)) {
            return;
        }
        $synchronousDispatcherConfig = $config['synchronous_message_dispatcher'];
        if ($this->extension->isConfigEnabled($this->container, $synchronousDispatcherConfig)) {
            throw new LogicException('Can not enable synchronous_message_dispatcher and messenger_message_dispatcher in one configuration. Disable one of configs.');
        }

        $this->container->setParameter('andreo.eventsauce.messenger_enabled', true);

        $dispatcherChainConfig = $messageDispatcherConfig['chain'];

        $dispatcherChainReferences = [];
        foreach ($dispatcherChainConfig as $dispatcherAlias => $dispatcherConfig) {
            $busAlias = $dispatcherConfig['bus'];

            $dispatcherDef = $this->container
                ->register("andreo.eventsauce.message_dispatcher.$dispatcherAlias", MessengerMessageDispatcher::class)
                ->addArgument(new Reference($busAlias))
                ->setPublic(false)
                ->addTag('andreo.eventsauce.messenger.message_dispatcher', [
                    'bus' => $busAlias,
                ]);

            if ($this->extension->isConfigEnabled($this->container, $dispatcherConfig['acl'])) {
                $aclConfig = $config['acl'];
                $outboundConfig = $aclConfig['outbound'];

                if (!$this->extension->isConfigEnabled($this->container, $aclConfig) ||
                    !$this->extension->isConfigEnabled($this->container, $outboundConfig)) {
                    throw new LogicException('Default acl outbound config is disabled. If you want to use it, enable and configure it.');
                }

                $dispatcherDef->addTag('andreo.eventsauce.acl_outbound');

                $outboundFilterChainConfig = $outboundConfig['filter_chain'];
                $dispatcherDef->addTag('andreo.eventsauce.acl.filter_chain', [
                    'before' => $outboundFilterChainConfig['before_translate'],
                    'after' => $outboundFilterChainConfig['after_translate'],
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
