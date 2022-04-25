<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection;

use Andreo\EventSauce\Snapshotting\ConstructingSnapshotStateSerializer;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use EventSauce\Clock\SystemClock;
use EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector;
use EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer;
use EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer;
use EventSauce\MessageRepository\TableSchema\DefaultTableSchema;
use EventSauce\UuidEncoding\BinaryUuidEncoder;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    private const JSON_OPTIONS = [
        JSON_FORCE_OBJECT,
        JSON_HEX_QUOT,
        JSON_HEX_TAG,
        JSON_HEX_AMP,
        JSON_HEX_APOS,
        JSON_INVALID_UTF8_IGNORE,
        JSON_INVALID_UTF8_SUBSTITUTE,
        JSON_NUMERIC_CHECK,
        JSON_PARTIAL_OUTPUT_ON_ERROR,
        JSON_PRESERVE_ZERO_FRACTION,
        JSON_PRETTY_PRINT,
        JSON_UNESCAPED_LINE_TERMINATORS,
        JSON_UNESCAPED_SLASHES,
        JSON_UNESCAPED_UNICODE,
        JSON_THROW_ON_ERROR,
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('andreo_eventsauce');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->getTimeSection())
                ->append($this->getEventStoreSection())
                ->append($this->getSynchronousMessageDispatcherSection())
                ->append($this->getMessengerMessageDispatcherSection())
                ->append($this->getAclSection())
                ->append($this->getMessageDecoratorSection())
                ->append($this->getEventDispatcherSection())
                ->append($this->getOutboxSection())
                ->append($this->getSnapshotSection())
                ->append($this->getUpcasterSection())
                ->append($this->getAggregatesSection())
                ->append($this->getSerializerSection())
                ->append($this->getClassNameInflectorSection())
                ->append($this->getUuidEncoderSection())
                ->append($this->getMigrationGeneratorSection())
            ->end();

        return $treeBuilder;
    }

    private function getTimeSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('time');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('timezone')
                    ->cannotBeEmpty()
                    ->defaultValue('UTC')
                ->end()
                ?->scalarNode('clock')
                    ->defaultNull()
                    ->info(
                        sprintf(
                            'Clock implementation. Default is: %s',
                            SystemClock::class
                        ))
                ->end()
            ?->end();

        return $node;
    }

    private function getEventStoreSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('event_store');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('repository')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('json_encode_options')
                            ->normalizeKeys(false)
                            ->scalarPrototype()
                                ->validate()
                                    ->ifNotInArray(self::JSON_OPTIONS)
                                    ->thenInvalid('Invalid JSON options.')
                                ->end()
                            ->end()
                        ?->end()
                        ->arrayNode('doctrine')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('connection')
                                    ->cannotBeEmpty()
                                    ->defaultValue('doctrine.dbal.default_connection')
                                ->end()
                                ?->scalarNode('table_schema')
                                    ->defaultNull()
                                    ->info(
                                        sprintf(
                                            'TableSchema implementation. Default is: %s',
                                            DefaultTableSchema::class
                                        )
                                    )
                                ->end()
                                ?->scalarNode('table_name')
                                    ->info('Table name suffix.')
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

    private function getMigrationGeneratorSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('migration_generator');

        $node
            ->canBeEnabled()
            ->children()
                ->scalarNode('dependency_factory')
                    ->defaultValue(class_exists(DoctrineMigrationsBundle::class) ? 'doctrine.migrations.dependency_factory' : null)
                    ->cannotBeEmpty()
                ->end()
            ?->end();

        return $node;
    }

    private function getAclSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('acl');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('outbound')
                    ->canBeDisabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('filter_chain')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('before_translate')
                                    ->values(['match_all', 'match_any'])
                                    ->cannotBeEmpty()
                                    ->defaultValue('match_all')
                                ->end()
                                ?->enumNode('after_translate')
                                    ->values(['match_all', 'match_any'])
                                    ->cannotBeEmpty()
                                    ->defaultValue('match_all')
                                ->end()
                            ?->end()
                        ->end()
                    ?->end()
                ->end()
                ?->arrayNode('inbound')
                    ->canBeDisabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('filter_chain')
                            ->children()
                                ->enumNode('before_translate')
                                    ->values(['match_all', 'match_any'])
                                    ->cannotBeEmpty()
                                    ->defaultValue('match_all')
                                ->end()
                                ?->enumNode('after_translate')
                                    ->values(['match_all', 'match_any'])
                                    ->cannotBeEmpty()
                                    ->defaultValue('match_all')
                                ->end()
                            ?->end()
                        ->end()
                    ?->end()
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

    private function getSynchronousMessageDispatcherSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('synchronous_message_dispatcher');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('chain')
                    ->normalizeKeys(false)
                    ->validate()
                        ->ifTrue(static function (array $config) {
                            foreach (array_keys($config) as $alias) {
                                if (is_numeric($alias)) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Dispatcher alias must be string')
                    ->end()
                    ->beforeNormalization()
                        ->always(static function (array $config) {
                            $normalizedConfig = [];
                            foreach ($config as $aliasOrIndex => $aliasOrConfig) {
                                if (is_string($aliasOrConfig)) {
                                    $normalizedConfig[$aliasOrConfig] = [];
                                } else {
                                    $normalizedConfig[$aliasOrIndex] = $aliasOrConfig;
                                }
                            }

                            return $normalizedConfig;
                        })
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ?->arrayNode('acl')->canBeEnabled()->end()
                        ?->end()
                    ->end()
                ?->end()
            ->end();

        return $node;
    }

    private function getMessengerMessageDispatcherSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('messenger_message_dispatcher');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('chain')
                    ->normalizeKeys(false)
                    ->validate()
                        ->ifTrue(static function (array $config) {
                            foreach (array_keys($config) as $key) {
                                if (is_numeric($key)) {
                                    return true;
                                }
                            }

                            return false;
                        })
                        ->thenInvalid('Dispatcher alias must be string')
                    ->end()
                    ->arrayPrototype()
                        ->children()
                            ?->scalarNode('bus')->cannotBeEmpty()->isRequired()->end()
                            ?->arrayNode('acl')->canBeEnabled()->end()
                        ?->end()
                    ->end()
                ?->end()
            ->end();

        return $node;
    }

    private function getEventDispatcherSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('event_dispatcher');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('outbox')
                    ->canBeEnabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('repository')
                            ->info('Repository type.')
                            ->addDefaultsIfNotSet()
                            ->validate()
                                ->ifTrue(static fn (array $config) => $config['memory']['enabled'] && $config['doctrine']['enabled'])
                                ->thenInvalid('Only one type of event outbox repository can be set: memory or doctrine')
                            ->end()
                            ->children()
                                ->arrayNode('memory')
                                    ->canBeEnabled()
                                ->end()
                                ?->arrayNode('doctrine')
                                    ->canBeEnabled()
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('table_name')
                                            ->info('Table name suffix.')
                                            ->cannotBeEmpty()
                                            ->defaultValue('_outbox_message')
                                        ->end()
                                    ?->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ?->end();

        return $node;
    }

    private function getOutboxSection(): NodeDefinition
    {
        $backOfStrategies = [
            'exponential',
            'fibonacci',
            'linear',
            'no_waiting',
            'immediately',
            'custom',
        ];

        $node = new ArrayNodeDefinition('outbox');
        $node
            ->canBeEnabled()
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('logger')->defaultNull()->end()
                ?->arrayNode('back_off')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static function (array $strategies) {
                            $count = 0;
                            foreach ($strategies as $strategy) {
                                if ($strategy['enabled']) {
                                    ++$count;
                                }
                            }

                            return $count > 1;
                        })
                        ->thenInvalid(
                            sprintf(
                                'Only one strategy of outbox back off can be set: %s.',
                                $this->implode($backOfStrategies)
                            )
                        )
                    ->end()
                    ->children()
                        ->arrayNode('exponential')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('initial_delay_ms')->defaultNull()->end()
                                ?->integerNode('max_tries')->defaultNull()->end()
                            ?->end()
                        ->end()
                        ->arrayNode('fibonacci')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('initial_delay_ms')->defaultNull()->end()
                                ?->integerNode('max_tries')->defaultNull()->end()
                            ?->end()
                        ->end()
                        ->arrayNode('linear')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->integerNode('initial_delay_ms')->defaultNull()->end()
                                ?->integerNode('max_tries')->defaultNull()->end()
                            ?->end()
                        ->end()
                        ->arrayNode('no_waiting')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ?->integerNode('max_tries')->defaultNull()->end()
                            ?->end()
                        ->end()
                        ->arrayNode('immediately')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                        ->end()
                        ?->arrayNode('custom')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('id')->isRequired()->end()
                            ?->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('relay_commit')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $config) => $config['mark_consumed']['enabled'] && $config['delete']['enabled'])
                        ->thenInvalid('Only one strategy of outbox relay commit can be set: mark_consumed or delete')
                    ->end()
                    ->children()
                        ->arrayNode('mark_consumed')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                        ->end()
                        ?->arrayNode('delete')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                        ->end()
                    ?->end()
                ->end()
                ->arrayNode('repository')
                    ->info('Repository type.')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $config) => $config['memory']['enabled'] && $config['doctrine']['enabled'])
                        ->thenInvalid('Only one type of message outbox repository can be set: memory or doctrine')
                    ->end()
                    ->children()
                        ->arrayNode('memory')
                            ->canBeEnabled()
                        ->end()
                        ?->arrayNode('doctrine')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('table_name')
                                    ->info('Table name suffix.')
                                    ->cannotBeEmpty()
                                    ->defaultValue('outbox_message')
                                ->end()
                            ?->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

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
                    ->info('Repository type.')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $config) => $config['memory']['enabled'] && $config['doctrine']['enabled'])
                        ->thenInvalid('Only one type of snapshot repository can be set: memory or doctrine')
                    ->end()
                    ->children()
                        ->arrayNode('memory')
                            ->canBeEnabled()
                        ->end()
                        ?->arrayNode('doctrine')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('table_name')
                                    ->info('Table name suffix.')
                                    ->cannotBeEmpty()
                                    ->defaultValue('snapshot')
                                ->end()
                            ?->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('versioned')->defaultFalse()->end()
                ?->arrayNode('store_strategy')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $config) => $config['every_n_event']['enabled'] && $config['custom']['enabled'])
                        ->thenInvalid('Only one strategy of snapshot store can be set: every_n_event or custom')
                    ->end()
                    ->children()
                        ->arrayNode('every_n_event')
                            ->canBeEnabled()
                            ->children()
                                ->integerNode('number')
                                    ->isRequired()
                                    ->min(10)
                                ?->end()
                            ?->end()
                        ->end()
                        ->arrayNode('custom')
                            ->canBeEnabled()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('id')->isRequired()
                                ?->end()
                            ?->end()
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
            ->addDefaultsIfNotSet()
            ->children()
                ->enumNode('argument')->values(['payload', 'message'])->defaultValue('payload')->end()
            ?->end();

        return $node;
    }

    public function getSerializerSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('serializer');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('payload')
                    ->info(
                        sprintf(
                            'PayloadSerializer implementation. Default is: %s',
                            ConstructingPayloadSerializer::class
                        ))
                    ->defaultNull()
                ->end()
                ?->scalarNode('message')
                    ->info(
                        sprintf(
                            'MessageSerializer implementation. Default is: %s',
                            ConstructingMessageSerializer::class
                        ))
                    ->defaultNull()
                ->end()
                ?->scalarNode('snapshot')
                    ->defaultNull()
                    ->info(
                        sprintf(
                            'SnapshotStateSerializer implementation. Default is: %s',
                            ConstructingSnapshotStateSerializer::class
                        )
                    )
                ->end()
            ?->end();

        return $node;
    }

    public function getClassNameInflectorSection(): NodeDefinition
    {
        $node = new ScalarNodeDefinition('class_name_inflector');
        $node
            ->defaultNull()
            ->info(
                sprintf(
                    'ClassNameInflector implementation. Default is: %s',
                    DotSeparatedSnakeCaseInflector::class
                ))
            ->end();

        return $node;
    }

    public function getUuidEncoderSection(): NodeDefinition
    {
        $node = new ScalarNodeDefinition('uuid_encoder');
        $node
            ->defaultNull()
            ->info(
                sprintf(
                    'UuidEncoder implementation. Default is: %s',
                    BinaryUuidEncoder::class
                ))
            ->end()
        ?->end();

        return $node;
    }

    private function getAggregatesSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('aggregates');

        $node
            ->normalizeKeys(false)
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
                        ->info('Aggregate root class')
                        ->cannotBeEmpty()
                        ->isRequired()
                    ->end()
                    ?->scalarNode('repository_alias')
                        ->info('Default "${aggregateName}Repository"')
                        ->defaultNull()
                        ->cannotBeEmpty()
                    ->end()
                    ?->arrayNode('outbox')->canBeEnabled()->end()
                    ?->arrayNode('dispatchers')
                        ->normalizeKeys(false)
                        ->scalarPrototype()->end()
                    ?->end()
                    ?->arrayNode('upcaster')->canBeEnabled()->end()
                    ?->arrayNode('snapshot')->canBeEnabled()->end()
                ?->end()
            ->end();

        return $node;
    }

    private function implode(array $classes): string
    {
        return implode(', ', $classes);
    }
}
