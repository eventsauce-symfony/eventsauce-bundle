<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\CompilerPass;

use Andreo\EventSauceBundle\DependencyInjection\Utils\ReflectionTool;
use Andreo\EventSauceBundle\DependencyInjection\Utils\TaggedServicesSortTool;
use RuntimeException;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class UpcasterPass implements CompilerPassInterface
{
    public function __construct(private string $upcasterTag = 'andreo.eventsauce.upcaster')
    {
    }

    public function process(ContainerBuilder $container): void
    {
        $aggregateUpcasterReferences = [];
        foreach (TaggedServicesSortTool::findAndSort($container, $this->upcasterTag, 'version', true) as $upcasterReference) {
            $upcasterDefinition = $container->getDefinition($upcasterReference->__toString());
            [$upcasterTagAttributes] = $upcasterDefinition->getTag($this->upcasterTag);
            $class = $upcasterTagAttributes['class'] ?? null;
            if (null === $class) {
                throw new RuntimeException(sprintf('Upcaster tag of service %s require class attribute.', $upcasterReference));
            }
            $aggregateClassShortName = ReflectionTool::getLowerStringOfClassShortName($class);
            $aggregateUpcasterReferences[$aggregateClassShortName][] = $upcasterReference;
        }

        foreach ($aggregateUpcasterReferences as $aggregateClassShortName => $upcasterReferences) {
            if (!$container->hasDefinition("andreo.eventsauce.upcaster_chain.$aggregateClassShortName")) {
                continue;
            }
            $upcasterChainDef = $container->getDefinition("andreo.eventsauce.upcaster_chain.$aggregateClassShortName");
            $upcasterChainDef->addArgument(new IteratorArgument($upcasterReferences));
        }
    }
}
