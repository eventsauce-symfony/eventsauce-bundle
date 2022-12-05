<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection;

use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final readonly class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('andreo_event_sauce');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->getClockSection())
                ->append($this->getMessageStorageSection())
                ->append($this->getAclSection())
                ->append($this->getMessageDispatcherSection())
                ->append($this->getEventDispatcherSection())
                ->append($this->getUpcasterSection())
                ->append($this->getMessageDecoratorSection())
                ->append($this->getMessageOutboxSection())
                ->append($this->getSnapshotSection())
                ->append($this->getMigrationGeneratorSection())
                ->append($this->getAggregatesSection())
            ->end();

        return $treeBuilder;
    }

    private function getClockSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('clock');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('timezone')
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
            ?->end();

        return $node;
    }

    private function getMessageStorageSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('message_storage');
        $node
            ->canBeDisabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('repository')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('doctrine_3')
                            ->canBeDisabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->arrayNode('json_encode_flags')
                                    ->normalizeKeys(false)
                                    ->scalarPrototype()
                                    ->end()
                                ?->end()
                                ->scalarNode('connection')
                                    ->cannotBeEmpty()
                                    ->defaultValue('doctrine.dbal.default_connection')
                                ->end()
                                ?->scalarNode('table_name')
                                    ->cannotBeEmpty()
                                    ->defaultValue('event_store')
                                ->end()
                            ?->end()
                        ->end()
                    ->end()
                ->end()
            ?->end();

        return $node;
    }

    private function getAclSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('acl');
        $node
            ->canBeEnabled()
            ->end();

        return $node;
    }

    private function getMessageDispatcherSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('message_dispatcher');
        $node
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->validate()
                ->ifTrue(static function (array $config) {
                    foreach (array_keys($config) as $alias) {
                        if (is_numeric($alias)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->thenInvalid('Message dispatcher alias must be a string.')
            ->end()
            ->arrayPrototype()
                ->children()
                    ->arrayNode('type')
                        ->validate()
                            ->ifTrue(static function (array $typeConfigs) {
                                $enabledTypes = [];
                                foreach ($typeConfigs as $type => $typeConfig) {
                                    if ($typeConfig['enabled'] ?? false) {
                                        $enabledTypes[] = $type;
                                    }
                                }

                                return 1 !== count($enabledTypes);
                            })
                            ->thenInvalid('Only one of types: (sync or messenger) can be enabled.')
                        ->end()
                        ->children()
                            ->arrayNode('sync')
                                ->canBeEnabled()
                            ->end()
                            ?->arrayNode('messenger')
                                ->canBeEnabled()
                                ->children()
                                    ->scalarNode('bus')->cannotBeEmpty()->isRequired()->end()
                                ?->end()
                            ->end()
                        ?->end()
                    ->end()
                    ?->append($this->getMessageDispatcherAclNode())
                ->end();

        return $node;
    }

    private function getMessageDispatcherAclNode(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('acl');

        $strategyEnum = ['match_all', 'match_any'];

        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('message_filter_strategy')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('before_translate')
                            ->values($strategyEnum)
                            ->defaultValue('match_all')
                        ->end()
                        ?->enumNode('after_translate')
                            ->values($strategyEnum)
                            ->defaultValue('match_all')
                        ->end()
                    ?->end()
                ->end()
            ?->end();

        return $node;
    }

    private function getEventDispatcherSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('event_dispatcher');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('message_outbox')
                    ->canBeEnabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('table_name')
                            ->cannotBeEmpty()
                            ->defaultValue('event_message_outbox')
                        ->end()
                        ?->scalarNode('relay_id')
                            ->defaultValue('event_dispatcher_relay')
                        ->end()
                    ?->end()
                ->end()
            ?->end();

        return $node;
    }

    private function getUpcasterSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('upcaster');
        $node
            ->canBeEnabled()
            ->children()
                ->enumNode('trigger')
                    ->values(['before_unserialize', 'after_unserialize'])
                    ->defaultValue('before_unserialize')
                ->end()
            ?->end();

        return $node;
    }

    private function getMessageDecoratorSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('message_decorator');
        $node
            ->canBeDisabled()
            ->end();

        return $node;
    }

    private function getMessageOutboxSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('message_outbox');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('repository')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('doctrine')
                            ->canBeDisabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('table_name')
                                    ->cannotBeEmpty()
                                    ->defaultValue('message_outbox')
                                ->end()
                            ?->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('logger')->defaultValue(class_exists(MonologBundle::class) ? LoggerInterface::class : null)->end()
            ?->end();

        return $node;
    }

    private function getSnapshotSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('snapshot');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('repository')
                    ->addDefaultsIfNotSet()
                    ->canBeEnabled()
                    ->children()
                        ->arrayNode('doctrine')
                            ->canBeDisabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('table_name')
                                    ->cannotBeEmpty()
                                    ->defaultValue('snapshot_store')
                                ->end()
                            ?->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('versioned')
                    ->canBeEnabled()
                ->end()
                ?->arrayNode('conditional')
                    ->canBeEnabled()
                ->end()
            ?->end();

        return $node;
    }

    private function getMigrationGeneratorSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('migration_generator');

        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('dependency_factory')
                    ->defaultValue(class_exists(DoctrineMigrationsBundle::class) ? 'doctrine.migrations.dependency_factory' : null)
                    ->cannotBeEmpty()
                ->end()
            ?->end();

        return $node;
    }

    private function getAggregatesSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('aggregates');

        $node
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->validate()
                ->ifTrue(static function (array $config) {
                    foreach (array_keys($config) as $key) {
                        if (is_numeric($key)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->thenInvalid('Aggregate name must be string')
            ->end()
            ->arrayPrototype()
                ->children()
                    ->scalarNode('class')
                        ->cannotBeEmpty()
                        ->isRequired()
                    ->end()
                    ?->scalarNode('repository_alias')
                        ->defaultNull()
                        ->cannotBeEmpty()
                    ->end()
                    ?->arrayNode('message_outbox')
                        ->canBeEnabled()
                        ->children()
                            ->scalarNode('relay_id')
                                ->defaultNull()
                            ->end()
                        ?->end()
                    ->end()
                    ?->arrayNode('dispatchers')
                        ->normalizeKeys(false)
                        ->scalarPrototype()->end()
                    ?->end()
                    ?->arrayNode('upcaster')->canBeEnabled()->end()
                    ?->arrayNode('snapshot')
                        ->canBeEnabled()
                        ->children()
                            ?->arrayNode('conditional')
                                ->canBeEnabled()
                                ->children()
                                    ->arrayNode('every_n_event')
                                        ->canBeEnabled()
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->integerNode('number')
                                                ->defaultValue(100)
                                                ->min(10)
                                            ->end()
                                        ?->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $node;
    }
}
