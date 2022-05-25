<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection;

use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
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

    private const BACK_OF_STRATEGIES = [
        'exponential',
        'fibonacci',
        'linear',
        'no_waiting',
        'immediately',
        'custom',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('andreo_event_sauce');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->append($this->getTimeSection())
                ->append($this->getMessageStorageSection())
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
                ->end()
            ?->end();

        return $node;
    }

    private function getMessageStorageSection(): NodeDefinition
    {
        $node = new ArrayNodeDefinition('message_storage');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('repository')
                    ->addDefaultsIfNotSet()
                    ->validate()
                        ->ifTrue(static fn (array $config) => $config['memory']['enabled'] && $config['doctrine']['enabled'])
                        ->thenInvalid('Only one type of message repository can be set: memory or doctrine')
                    ->end()
                    ->children()
                        ->arrayNode('memory')
                            ->canBeEnabled()
                        ->end()
                        ?->arrayNode('doctrine')
                            ->canBeEnabled()
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
                                ->scalarNode('connection')
                                    ->cannotBeEmpty()
                                    ->defaultValue('doctrine.dbal.default_connection')
                                ->end()
                                ?->scalarNode('table_schema')
                                    ->defaultNull()
                                ->end()
                                ?->scalarNode('table_name')
                                    ->cannotBeEmpty()
                                    ->defaultValue('message_storage')
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
            ->validate()
                ->ifArray()
                ->then(static function (array $config) {
                    $rootEnabled = $config['enabled'] ?? false;
                    $outboundConfig = $config['outbound'] ?? false;
                    $inboundConfig = $config['inbound'] ?? false;
                    if (!$rootEnabled || !$outboundConfig || !$inboundConfig) {
                        return $config;
                    }

                    $outboundEnabledOrigin = $outboundConfig['enabled'] ?? false;
                    $inboundEnabledOrigin = $inboundConfig['enabled'] ?? false;

                    $config['outbound']['enabled'] = $outboundEnabledOrigin || !$inboundEnabledOrigin;
                    $config['inbound']['enabled'] = $inboundEnabledOrigin || !$outboundEnabledOrigin;

                    return $config;
                })
            ->end()
            ->children()
                ->arrayNode('outbound')
                    ->canBeEnabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('filter_strategy')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('before')
                                    ->values(['match_all', 'match_any'])
                                    ->cannotBeEmpty()
                                    ->defaultValue('match_all')
                                ->end()
                                ?->enumNode('after')
                                    ->values(['match_all', 'match_any'])
                                    ->cannotBeEmpty()
                                    ->defaultValue('match_all')
                                ->end()
                            ?->end()
                        ->end()
                    ?->end()
                ->end()
                ?->arrayNode('inbound')
                    ->canBeEnabled()
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('filter_strategy')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('before')
                                    ->values(['match_all', 'match_any'])
                                    ->cannotBeEmpty()
                                    ->defaultValue('match_all')
                                ->end()
                                ?->enumNode('after')
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
                ->arrayNode('outbox')->canBeEnabled()->end()
            ?->end();

        return $node;
    }

    private function getOutboxSection(): NodeDefinition
    {
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
                                'Only one of backoff strategies can be set: %s.',
                                $this->implode(self::BACK_OF_STRATEGIES)
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
                                    ->cannotBeEmpty()
                                    ->defaultValue('outbox')
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
                    ->defaultNull()
                ->end()
                ?->scalarNode('message')
                    ->defaultNull()
                ->end()
                ?->scalarNode('snapshot')
                    ->defaultNull()
                ->end()
            ?->end();

        return $node;
    }

    public function getClassNameInflectorSection(): NodeDefinition
    {
        $node = new ScalarNodeDefinition('class_name_inflector');
        $node
            ->defaultNull()
            ->end();

        return $node;
    }

    public function getUuidEncoderSection(): NodeDefinition
    {
        $node = new ScalarNodeDefinition('uuid_encoder');
        $node
            ->defaultNull()
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
                        ->cannotBeEmpty()
                        ->isRequired()
                    ->end()
                    ?->scalarNode('repository_alias')
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
