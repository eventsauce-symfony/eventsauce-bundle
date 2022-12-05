<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauce\Messenger\DependencyInjection\RegisterEventSauceMessageHandlerAttribute;
use Andreo\EventSauce\Messenger\Dispatcher\MessengerMessageDispatcher;
use Andreo\EventSauce\Messenger\Middleware\HandleEventSauceMessageMiddleware;
use Andreo\EventSauceBundle\Attribute\AsSyncMessageConsumer;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Factory\MessageDispatcherChainFactory;
use Andreo\EventSauceBundle\Factory\SynchronousMessageDispatcherFactory;
use EventSauce\EventSourcing\MessageDispatcher;
use EventSauce\EventSourcing\MessageDispatcherChain;
use EventSauce\EventSourcing\SynchronousMessageDispatcher;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

final readonly class MessageDispatcherLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        array $config
    ): void {
        $anySyncRegistered = false;
        $anyMessengerRegistered = false;
        $messageDispatcherConfigs = $config['message_dispatcher'];
        foreach ($messageDispatcherConfigs as $messageDispatcherAlias => $messageDispatcherConfig) {
            $messageDispatcherTypeConfigList = [];
            foreach ($messageDispatcherConfig['type'] as $messageDispatcherType => $messageDispatcherTypeConfig) {
                if ($extension->isConfigEnabled($container, $messageDispatcherTypeConfig)) {
                    $messageDispatcherTypeConfigList = [$messageDispatcherType, $messageDispatcherTypeConfig];
                    break;
                }
            }

            assert(!empty($messageDispatcherTypeConfigList));
            [$messageDispatcherType, $messageDispatcherTypeConfig] = $messageDispatcherTypeConfigList;

            if ('sync' === $messageDispatcherType) {
                $messageDispatcherDef = $container
                    ->register("andreo.eventsauce.message_dispatcher.$messageDispatcherAlias", SynchronousMessageDispatcher::class)
                    ->addArgument([])
                    ->setFactory([SynchronousMessageDispatcherFactory::class, 'create'])
                    ->setPublic(false)
                    ->addTag('andreo.eventsauce.message_dispatcher')
                ;
                $anySyncRegistered = true;
            } elseif ('messenger' === $messageDispatcherType) {
                if (!class_exists(MessengerMessageDispatcher::class)) {
                    throw new LogicException('Messenger message dispatcher is not available. Try running "composer require andreo/eventsauce-messenger".');
                }

                $busAlias = $messageDispatcherTypeConfig['bus'];
                $container->setParameter('andreo.eventsauce.messenger_enabled', true);

                $messageDispatcherDef = $container
                    ->register("andreo.eventsauce.message_dispatcher.$messageDispatcherAlias", MessengerMessageDispatcher::class)
                    ->addArgument(new Reference($busAlias))
                    ->setPublic(false)
                    ->addTag('andreo.eventsauce.message_dispatcher')
                ;

                $container
                    ->register("$busAlias.middleware.handle_event_sauce_message", HandleEventSauceMessageMiddleware::class)
                    ->addArgument(new Reference("$busAlias.messenger.handlers_locator"))
                    ->addTag('monolog.logger', ['channel' => 'messenger'])
                ;
                $container->setAlias("$busAlias.handle_eventsauce_message", "$busAlias.middleware.handle_event_sauce_message");

                $anyMessengerRegistered = true;
            } else {
                throw new LogicException('Invalid message dispatcher type.');
            }

            $messageDispatcherAclConfig = $messageDispatcherConfig['acl'];
            if ($extension->isConfigEnabled($container, $messageDispatcherAclConfig)) {
                if (!$extension->isConfigEnabled($container, $config['acl'])) {
                    throw new LogicException('Acl config is disabled.');
                }

                $messageFilterStrategyConfig = $messageDispatcherAclConfig['message_filter_strategy'];

                $messageDispatcherDef->addTag('andreo.eventsauce.acl', [
                    'message_filter_strategy_before_translate' => $messageFilterStrategyConfig['before_translate'],
                    'message_filter_strategy_after_translate' => $messageFilterStrategyConfig['after_translate'],
                ]);
            }

            $container->setAlias($messageDispatcherAlias, "andreo.eventsauce.message_dispatcher.$messageDispatcherAlias");
            $container->registerAliasForArgument($messageDispatcherAlias, MessageDispatcher::class);
        }

        if ($anySyncRegistered) {
            $container->registerAttributeForAutoconfiguration(
                AsSyncMessageConsumer::class,
                static function (ChildDefinition $definition, AsSyncMessageConsumer $attribute): void {
                    $dispatcherAlias = $attribute->dispatcher;
                    $definition->addTag('andreo.eventsauce.sync_message_consumer', [
                        'dispatcher' => $dispatcherAlias,
                    ]);
                }
            );
        }

        if ($anyMessengerRegistered) {
            RegisterEventSauceMessageHandlerAttribute::register($container);
        }

        $container
            ->register(
                'andreo.eventsauce.message_dispatcher_chain',
                MessageDispatcherChain::class
            )
            ->setFactory([MessageDispatcherChainFactory::class, 'create'])
            ->addArgument(new TaggedIteratorArgument('andreo.eventsauce.message_dispatcher'))
            ->setPublic(false)
        ;
    }
}
