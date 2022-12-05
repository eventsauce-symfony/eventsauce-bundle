<p align="center">
    <a href="https://eventsauce.io">
        <img src="https://eventsauce.io/static/logo.svg" height="150px" width="150px">
    </a>
</p>

# EventSauceBundle 3.0

Official  [documentation](https://eventsauce.io/docs/) of eventsauce

### Supports

- Doctrine3 event store
- Symfony messenger message dispatcher
- Anti-Corruption Layer
- Event dispatcher
- Message Outbox
- Snapshot doctrine repository, versioning, conditional persist
- All events in table per aggregate type
- Generating migrations per aggregate


### Previous versions

- [2.0](https://github.com/eventsauce-symfony/eventsauce-bundle/tree/2.0.1)

### Requirements

- PHP >=8.2
- Symfony ^6.2

### Installation

```bash
composer require andreo/eventsauce-bundle
```

```php
// config/bundles.php

return [
    Andreo\EventSauceBundle\AndreoEventSauceBundle::class => ['all' => true],
];
```

### Introduction

Below configs presents default values and some example values. \
Note that most of default config values do not need to configure.

### [Clock](https://eventsauce.io/docs/utilities/clock/)

```yaml
andreo_event_sauce:
  clock:
    timezone: UTC
```

***Useful aliases***
```php
EventSauce\Clock\Clock: EventSauce\Clock\SystemClock
```

### [Message Storage](https://eventsauce.io/docs/message-storage/)

### Doctrine 3

```yaml
andreo_event_sauce:
  #...
  message_storage:
    repository:
      doctrine_3:
        enabled: true
        json_encode_flags: []
        connection: doctrine.dbal.default_connection
        table_name: event_store
```

Require

- doctrine/dbal

### Message dispatcher

[SynchronousMessageDispatcher](https://eventsauce.io/docs/reacting-to-events/setup-consumers/#synchronous-message-dispatcher)

```yaml
andreo_event_sauce:
  #...
  message_dispatcher: # chain of message dispatchers
    foo_dispatcher:
      type:
        sync: true
    bar_dispatcher:
      type:
        sync: true
```

[EventConsumer](https://eventsauce.io/docs/reacting-to-events/projections-and-read-models/)

```php
use EventSauce\EventSourcing\MessageConsumer;
use Andreo\EventSauceBundle\Attribute\AsSyncMessageConsumer;
use EventSauce\EventSourcing\EventConsumption\EventConsumer;
use Andreo\EventSauce\Messenger\EventConsumer\InjectedHandleMethodInflector;
use EventSauce\EventSourcing\Message;

#[AsSyncMessageConsumer(dispatcher: 'foo_dispatcher')]
final class FooBarEventConsumer extends EventConsumer 
{
    // copy-paste trait for inject HandleMethodInflector of EventSauce
    use InjectedHandleMethodInflector;
    
    public function __construct(
        private HandleMethodInflector $handleMethodInflector
    ){}
    
    public function onFooCreated(FooCreated $fooCreated, Message $message): void {
    }
    
    public function onBarCreated(BarCreated $barCreated, Message $message): void {
    }
}
```

Example of manually registration sync consumer \
(without attribute and autoconfiguration)

```yaml
services:
  #...
  App\Consumer\FooBarEventConsumer:
    tags:
      -
        name: andreo.eventsauce.sync_message_consumer
```

[MessengerMessageDispatcher](https://github.com/andrew-pakula/eventsauce-messenger)

Dispatching with [Symfony messenger](https://symfony.com/doc/current/messenger.html)

Install [andreo/eventsauce-messenger](https://github.com/eventsauce-symfony/eventsauce-messenger)

```bash
composer require andreo/eventsauce-messenger
```

```yaml
andreo_event_sauce:
 #...
  message_dispatcher: # chain of message dispatchers
    foo_dispatcher:
      type:
        messenger:
          bus: event_bus # bus alias from messenger config
```

It registers alias of handle event sauce message middleware:

```php
$busAlias.handle_eventsauce_message: Andreo\EventSauce\Messenger\Middleware\HandleEventSauceMessageMiddleware
```

Update messenger config. According to above config

```yaml
framework:
  messenger:
    #...
    buses:
      event_bus:
        default_middleware: false # disable default middleware order
        middleware:
          - 'add_bus_name_stamp_middleware': ['event_bus']
          - 'dispatch_after_current_bus'
          - 'failed_message_processing_middleware'
          - 'send_message'
          - 'event_bus.handle_eventsauce_message' # our middleware should be placed after send_message and before default handle massage middleware (if you use)
          - 'handle_message'
```

[EventConsumer](https://eventsauce.io/docs/reacting-to-events/projections-and-read-models/)

```php
use Andreo\EventSauce\Messenger\EventConsumer\InjectedHandleMethodInflector;
use EventSauce\EventSourcing\EventConsumption\EventConsumer;
use EventSauce\EventSourcing\EventConsumption\HandleMethodInflector;
use Andreo\EventSauce\Messenger\Attribute\AsEventSauceMessageHandler;
use EventSauce\EventSourcing\Message;

final class FooBarEventConsumer extends EventConsumer
{
    use InjectedHandleMethodInflector;

    public function __construct(
        private HandleMethodInflector $handleMethodInflector
    )
    {}

    #[AsEventSauceMessageHandler(bus: 'fooBus')]
    public function onFooCreated(FooCreated $fooCreated, Message $message): void
    {
    }

    #[AsEventSauceMessageHandler(bus: 'barBus')]
    public function onBarCreated(BarCreated $barCreated, Message $message): void
    {
    }
}
```

***Useful aliases***
```php
EventSauce\EventSourcing\EventConsumption\HandleMethodInflector: EventSauce\EventSourcing\EventConsumption\InflectHandlerMethodsFromType
```

**Message dispatcher tag** (for manually registration of dispatchers, if you will want)
```php
andreo.eventsauce.message_dispatcher
```

### [Anti-Corruption Layer](https://eventsauce.io/docs/advanced/anti-corruption-layer/)

```yaml
andreo_event_sauce:
  #...
  acl: true
``` 

Enable for **Message dispatcher** (by config)

```yaml
andreo_event_sauce:
 #...
  message_dispatcher:
    fooDispatcher:
      type:
        messenger:
          bus: fooBus
      acl:
        enabled: true
        message_filter_strategy:
          before_translate: match_all # or match_any
          after_translate: match_all

``` 

Enable for **Message consumer**

```php

use Andreo\EventSauceBundle\Attribute\EnableAcl;
use Andreo\EventSauceBundle\Enum\MessageFilterStrategy;
use EventSauce\EventSourcing\EventConsumption\EventConsumer;

#[EnableAcl]
final class FooHandler extends EventConsumer
{
    #[AsEventSauceMessageHandler(
        handles: FooEvent::class // If you will use translator, for messenger handles must be defined manually
    )]
    public function onFooCreated(BarEvent $barEvent): void
    {
        // ...
    }
}
```

Example of manually registration acl consumer (or dispatcher) \
(without attribute and autoconfiguration)

```yaml
services:
  #...
  App\Consumer\FooConsumer:
    tags:
      -
        name: andreo.eventsauce.acl
        message_filter_strategy_before_translate: match_all # or match_any
        message_filter_strategy_after_translate: match_all # or match_any
```

#### Message translator

```php

use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslator;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use EventSauce\EventSourcing\Message;

#[AsMessageTranslator] 
final readonly class FooMessageTranslator implements MessageTranslator
{
    public function translateMessage(Message $message): Message
    {
        assert($message->payload() instanceof FooEvent);
        // ...
           
        return new Message(new BarEvent());
    }
}
```

Example of manually registration message filter \
(without attribute and autoconfiguration)

```yaml
services:
  #...
  App\Acl\FooMessageTranslator:
    tags:
      -
        name: andreo.eventsauce.acl.message_translator
        priority: 0
        owners: []
```

#### Message filter

Message filter strategies:

`match_all` - all filters passed a condition \
`match_any` - any filter passed a condition

Message Filter

```php
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Enum\MessageFilterTrigger;

#[AsMessageFilter(MessageFilterTrigger::BEFORE_TRANSLATE)] // or after AFTER_TRANSLATE
final readonly class FooMessageFilter implements MessageFilter
{
    public function allows(Message $message): bool
    {
    }
}
```

Example of manually registration message filter \
(without attribute and autoconfiguration)

```yaml
services:
  #...
  App\Acl\FooMessageFilter:
    tags:
      -
        name: andreo.eventsauce.acl.message_filter
        trigger: before_translate # or after_translate
        priority: 0
        owners: []
```

#### Owners of message translator or filters

For example, we use Translator, but Filter works the same

```php
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslator;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use EventSauce\EventSourcing\MessageConsumer;
use EventSauce\EventSourcing\MessageDispatcher;

// Translator will be applied for all dispatchers, and to the FooConsumer
// By analogy we can use MessageConsumer::class for all consumers
#[AsMessageTranslator(owners: [MessageDispatcher::class, FooConsumer::class])]
final readonly class FooMessageTranslator implements MessageTranslator
{
    public function translateMessage(Message $message): Message
    {
    }
}
```

### [Event Dispatcher](https://eventsauce.io/docs/utilities/event-dispatcher/)

```yaml
andreo_event_sauce:
  # ...
  event_dispatcher:
    enabled: false
    message_outbox:
      enabled: false
      table_name: event_message_outbox # value will be used if the main outbox config is set to doctrine in the repository
      relay_id: event_dispatcher_relay # relay-id for run consume outbox messages command
```

Example of Event Dispatcher

```php
use EventSauce\EventSourcing\EventDispatcher;

final readonly class FooHandler
{
    public function __construct(
        private EventDispatcher $eventDispatcher
    ) {
    }

    public function handle(): void
    {
        $this->eventDispatcher->dispatch(
            new FooEvent()
        );
    }
}
```

### [Upcaster](https://eventsauce.io/docs/advanced/upcasting/#main-article)

```yaml
andreo_event_sauce:
    #...
  upcaster:
    enabled: false
    trigger: before_unserialize # or after_unserialize (on payload or on object of message)
```

Before unserialize

```php
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use EventSauce\EventSourcing\Upcasting\Upcaster;

#[AsUpcaster(aggregateClass: FooAggregate::class, version: 2)]
final class FooEventV2Upcaster implements Upcaster {

    public function upcast(array $message): array
    {
    }
}
```

[After unserialize](https://github.com/andrew-pakula/eventsauce-upcasting)

Install [andreo/eventsauce-upcasting](https://github.com/eventsauce-symfony/eventsauce-upcasting)

```bash
composer require andreo/eventsauce-upcasting
```

```php
use EventSauce\EventSourcing\Message;
use Andreo\EventSauce\Upcasting\MessageUpcaster\MessageUpcaster;
use Andreo\EventSauce\Upcasting\MessageUpcaster\Event;
use Andreo\EventSauceBundle\Attribute\AsUpcaster;

#[AsUpcaster(aggregateClass: FooAggregate::class, version: 2)]
final class SomeEventV2Upcaster implements MessageUpcaster {

    #[Event(event: FooEvent::class)]
    public function upcast(Message $message): Message
    {
    }
}
```

Example of manually registration upcaster (without attribute and autoconfiguration)

```yaml
services:
  #...
  App\Upcaster\FooUpcaster:
    tags:
      -
        name: andreo.eventsauce.upcaster
        class: App\Domain\FooAggregate
        version: 2
```

### [Message decorator](https://eventsauce.io/docs/advanced/message-decoration/)


```yaml
andreo_event_sauce:
    #...
    message_decorator: true
```

```php
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;

#[AsMessageDecorator]
final class FooDecorator implements MessageDecorator
{
    public function decorate(Message $message): Message
    {
    }
}
```

Example of manually registration decorator (without attribute and autoconfiguration)

```yaml
services:
  #...
  App\Decorator\FooDecorator:
    tags:
      -
        name: andreo.eventsauce.message_decorator
```

### [Message Outbox](https://eventsauce.io/docs/message-outbox/)

Install [andreo/eventsauce-outbox](https://github.com/eventsauce-symfony/eventsauce-outbox)

```bash
composer require andreo/eventsauce-outbox
```

Main configuration loading common services

```yaml
andreo_event_sauce:
  #...
  message_outbox:
    enabled: false
    repository:
      doctrine:
        enabled: true
        table_name: message_outbox
    logger: Psr\Log\LoggerInterface # default if monolog bundle has been installed
```

Consume outbox messages

```bash
bin/console andreo:eventsauce:message-outbox:consume relay_id
```

***Useful aliases***
```php
EventSauce\BackOff\BackOffStrategy: EventSauce\BackOff\ExponentialBackOffStrategy
```
```php
EventSauce\MessageOutbox\RelayCommitStrategy: EventSauce\MessageOutbox\MarkMessagesConsumedOnCommit
```

### [Snapshotting](https://eventsauce.io/docs/snapshotting/)


**To use:**

- doctrine snapshot repository
- versioned snapshots
- conditional persist

package [andreo/eventsauce-snapshotting](https://github.com/eventsauce-symfony/eventsauce-snapshotting) is required

```yaml
andreo_event_sauce:
  #...
  snapshot: 
    enabled: false
    repository:
      enabled: false
      doctrine:
        enabled: true
        table_name: snapshot_store
    versioned: false # enable versioned repository for all aggregates with snapshots enabled
    conditional: false
```

***Useful aliases***

```php
Andreo\EventSauce\Snapshotting\Repository\Versioned\SnapshotVersionInflector: Andreo\EventSauce\Snapshotting\Repository\Versioned\InflectVersionFromReturnedTypeOfSnapshotStateCreationMethod
```
```php
Andreo\EventSauce\Snapshotting\Repository\Versioned\SnapshotVersionComparator: Andreo\EventSauce\Snapshotting\Repository\Versioned\EqSnapshotVersionComparator
```

### [Migration generator](https://github.com/eventsauce-symfony/eventsauce-migration-generator)

Install [andreo/eventsauce-migration-generator](https://github.com/eventsauce-symfony/eventsauce-migration-generator)

```yaml
andreo_event_sauce:
  #...
  migration_generator:
    dependency_factory: doctrine.migrations.dependency_factory # default if doctrine migrations bundle has been installed
```

Generate migration for foo prefix

```bash
bin/console andreo:eventsauce:doctrine-migrations:generate foo 
```

***Useful aliases***
```php
EventSauce\MessageRepository\TableSchema\TableSchema: EventSauce\MessageRepository\TableSchema\DefaultTableSchema
```

### [Aggregates](https://eventsauce.io/docs/event-sourcing/create-an-aggregate-root)

```yaml
andreo_event_sauce:
  #...
  aggregates:
    foo: # aggregate name
      class: ~ # aggregate FQCN
      repository_alias: fooRepository # according to convention: $name . "Repository"
      message_outbox:
        enabled: false # enable message outbox for this aggregate
        relay_id: foo_aggregate_relay # relay-id for run consume outbox messages command, according to convention: $name . "aggregate_relay"
      dispatchers: [] # dispatcher service aliases (from config, or manually registered), if empty, messages will be sent to all dispatchers
      upcaster: false # enable upcaster for this aggregate
      snapshot:
        conditional: # enable conditional snapshot repository for this aggregate.
          enabled: false
          every_n_event: # you can use this strategy, or make your own implementation
            enabled: false
            number: 100
```

Repository injection

```php
use Symfony\Component\DependencyInjection\Attribute\Target;
use EventSauce\EventSourcing\AggregateRootRepository;

final class FooHandler {

   public function __construct(
        #[Target('fooRepository')] private AggregateRootRepository $fooRepository
    ){}
}
```

Snapshotting repository injection (if aggregate snapshot is enabled)

```php
use Symfony\Component\DependencyInjection\Attribute\Target;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;

final class FooHandler {

   public function __construct(
        #[Target('fooRepository')] private AggregateRootRepositoryWithSnapshotting $fooRepository
    ){}
}
```

***Useful aliases***

```php
andreo.eventsauce.snapshot.conditional_strategy.$aggregateName: Andreo\EventSauce\Snapshotting\Repository\Conditional\ConditionalSnapshotStrategy
```
```php
EventSauce\EventSourcing\Serialization\PayloadSerializer: EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer
```
```php
EventSauce\EventSourcing\Serialization\MessageSerializer: EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer
```
```php
EventSauce\UuidEncoding\UuidEncoder: EventSauce\UuidEncoding\BinaryUuidEncoder
```
```php
EventSauce\EventSourcing\ClassNameInflector: EventSauce\EventSourcing\DotSeparatedSnakeCaseInflector
```

### Other tips

#### Decorating aggregate root repository

```php
<?php
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootRepository;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'fooRepository')]
final readonly class FooRepository implements AggregateRootRepository
{
    public function __construct(private AggregateRootRepository $regularRepository)
    {
    }

    public function retrieve(AggregateRootId $aggregateRootId): object
    {
        return $this->regularRepository->retrieve($aggregateRootId);
    }

    public function persist(object $aggregateRoot): void
    {
        // ...
    }
    public function persistEvents(AggregateRootId $aggregateRootId, int $aggregateRootVersion, object ...$events): void
    {
        // ...
    }
}
```

Example api using this bundle

### [Repository](https://github.com/eventsauce-symfony/eventsaucebundle-example)
