<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Factory\MessageDecoratorChainFactory;
use EventSauce\EventSourcing\MessageDecorator;
use EventSauce\EventSourcing\MessageDecoratorChain;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class MessageDecoratorLoader
{
    public function __construct(private AndreoEventSauceExtension $extension, private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $messageDecoratorConfig = $config['message_decorator'];

        if ($this->extension->isConfigEnabled($this->container, $messageDecoratorConfig)) {
            $this->container->registerAttributeForAutoconfiguration(
                AsMessageDecorator::class,
                static function (ChildDefinition $definition, AsMessageDecorator $attribute): void {
                    $definition->addTag('andreo.eventsauce.aggregate_message_decorator', ['priority' => $attribute->priority]);
                }
            );

            $this->container
                ->findDefinition(MessageDecorator::class)
                ->addTag('andreo.eventsauce.aggregate_message_decorator', ['priority' => 0]);

            $this->container
                ->register('andreo.eventsauce.aggregate_message_decorator_chain', MessageDecoratorChain::class)
                ->addArgument(new TaggedIteratorArgument('andreo.eventsauce.aggregate_message_decorator'))
                ->setFactory([MessageDecoratorChainFactory::class, 'create'])
                ->setPublic(false)
            ;
        } else {
            $this->container
                ->register('andreo.eventsauce.aggregate_message_decorator_chain', MessageDecoratorChain::class)
                ->addArgument([])
                ->setFactory([MessageDecoratorChainFactory::class, 'create'])
                ->setPublic(false)
            ;
        }
    }
}
