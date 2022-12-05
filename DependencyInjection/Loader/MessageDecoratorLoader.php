<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Factory\MessageDecoratorChainFactory;
use EventSauce\EventSourcing\DefaultHeadersDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class MessageDecoratorLoader
{
    public static function load(
        AndreoEventSauceExtension $extension,
        ContainerBuilder $container,
        array $config
    ): void {
        $messageDecoratorConfig = $config['message_decorator'];

        if ($extension->isConfigEnabled($container, $messageDecoratorConfig)) {
            $container->registerAttributeForAutoconfiguration(
                AsMessageDecorator::class,
                static function (ChildDefinition $definition, AsMessageDecorator $attribute): void {
                    $definition->addTag('andreo.eventsauce.message_decorator', ['priority' => $attribute->priority]);
                }
            );

            $container
                ->findDefinition(DefaultHeadersDecorator::class)
                ->addTag('andreo.eventsauce.message_decorator', ['priority' => 0]);

            $container
                ->register('andreo.eventsauce.message_decorator_chain', MessageDecoratorChain::class)
                ->addArgument(new TaggedIteratorArgument('andreo.eventsauce.message_decorator'))
                ->setFactory([MessageDecoratorChainFactory::class, 'create'])
                ->setPublic(false)
            ;
        } else {
            $container
                ->register('andreo.eventsauce.message_decorator_chain', MessageDecoratorChain::class)
                ->addArgument([])
                ->setFactory([MessageDecoratorChainFactory::class, 'create'])
                ->setPublic(false)
            ;
        }
    }
}
