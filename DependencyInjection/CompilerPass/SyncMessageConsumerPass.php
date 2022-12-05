<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\CompilerPass;

use Andreo\EventSauceBundle\DependencyInjection\Utils\TaggedServicesSortTool;
use RuntimeException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class SyncMessageConsumerPass implements CompilerPassInterface
{
    public function __construct(private string $consumerTag = 'andreo.eventsauce.sync_message_consumer')
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $consumerDispatcherReferences = [];

        foreach (TaggedServicesSortTool::findAndSort($container, $this->consumerTag) as $consumerReference) {
            $consumerDefinition = $container->getDefinition($consumerReference->__toString());
            [$consumerTagAttributes] = $consumerDefinition->getTag($this->consumerTag);
            /** @var string|null $dispatcherId */
            $dispatcherId = $consumerTagAttributes['dispatcher'] ?? null;
            if (null === $dispatcherId) {
                throw new RuntimeException(sprintf('Consumer tag of service %s require dispatcher attribute.', $consumerReference));
            }
            $consumerDispatcherReferences[$dispatcherId][] = $consumerReference;
        }

        foreach ($consumerDispatcherReferences as $dispatcherId => $consumerReferences) {
            if (!$container->hasDefinition($dispatcherId)) {
                continue;
            }
            $dispatcherDef = $container->findDefinition($dispatcherId);
            $dispatcherDef->setArgument(0, new IteratorArgument($consumerReferences));
        }
    }
}
