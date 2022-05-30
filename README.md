<p align="center">
    <a href="https://eventsauce.io">
        <img src="https://eventsauce.io/static/logo.svg" height="150px" width="150px">
    </a>
</p>

# EventSauceBundle

This bundle injects the [EventSauce](https://eventsauce.io/) components to the symfony container. \
Before using it, I strongly recommend that you read the official eventsauce [documentation](https://eventsauce.io/docs/).

### Supports

- Doctrine message storage
- Message Outbox
- Anti-Corruption Layer
- Message dispatching with symfony messenger
- Snapshot doctrine repository, versioning
- All events in table per aggregate type
- Generating migrations per aggregate

and more...

### Example application

[eventsaucebundle-example](https://github.com/andrew-pakula/eventsaucebundle-example)

### Requirements

- PHP ^8.1
- Symfony ^6.0

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

### Timezone

Perhaps, you want to set your time zone.

```yaml
andreo_event_sauce:
    time:
        timezone: UTC # default
```

### Message Storage

[About Message Storage](https://eventsauce.io/docs/message-storage/)

#### Doctrine

Perhaps, you want to set doctrine dbal connection.

```yaml
andreo_event_sauce:
    message_storage:
        repository:
            doctrine:
                connection:  doctrine.dbal.default_connection # default
```

If you don't have doctrine, try install

```bash
composer require doctrine/doctrine-bundle
```

or, if you will be using migration and orm.

```bash
composer require symfony/orm-pack
```

### Message dispatcher

#### Synchronous

By default EventSauce dispatches events using
[SynchronousMessageDispatcher](https://eventsauce.io/docs/reacting-to-events/setup-consumers/#synchronous-message-dispatcher).

Example configuration

```yaml
andreo_event_sauce:
    synchronous_message_dispatcher:
        chain:
            foo_dispatcher: ~
            bar_dispatcher: ~
```

Message consumer example

```php
use EventSauce\EventSourcing\MessageConsumer;
use Andreo\EventSauceBundle\Attribute\AsSynchronousMessageConsumer;

#[AsSynchronousMessageConsumer(dispatcher: 'foo_dispatcher')]
final class FooConsumer implements MessageConsumer {

    public function handle(Message $message): void {
        // do something
    }
}
```

#### Symfony Messenger

Install the [package](https://github.com/andrew-pakula/eventsauce-messenger). \
I recommend reading the documentation

```bash
composer require andreo/eventsauce-messenger
```

Example configuration

```yaml
andreo_event_sauce:
    messenger_message_dispatcher:
        chain:
            foo_dispatcher:
                bus: fooBus # bus alias from messenger config
            bar_dispatcher:
                bus: barBus

```

If you don't have the messenger, try install.

```bash
composer require symfony/messenger
```

### Anti-Corruption Layer

[About ACL](https://eventsauce.io/docs/advanced/anti-corruption-layer/)

#### Outbound ACL

```yaml
andreo_event_sauce:
    acl: 
        outbound: true
    messenger_message_dispatcher: # or synchronous_message_dispatcher
        chain:
            foo_dispatcher:
                bus: fooBus
                acl: true # enable acl for this dispatcher
``` 

#### Inbound ACL

```yaml
andreo_event_sauce:
    acl:
        inbound: true
```

Consumer example

```php

use Andreo\EventSauceBundle\Attribute\WithInboundAcl;
use EventSauce\EventSourcing\MessageConsumer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[WithInboundAcl] // enable acl for this consumer
final class FooConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
        // do something
    }
}
```

#### Message translator

```php
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslator;
use EventSauce\EventSourcing\Message;

#[AsMessageTranslator] 
final class FooMessageTranslator implements MessageTranslator
{
    public function translateMessage(Message $message): Message
    {
        // do something
    }
}
```

#### Message filter

```php
use Andreo\EventSauceBundle\Attribute\AsMessageFilter;
use Andreo\EventSauceBundle\Enum\FilterPosition;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageFilter;
use EventSauce\EventSourcing\Message;

#[AsMessageFilter(FilterPosition::BEFORE)] // filter position: before translate, or after translate
final class FooMessageFilter implements MessageFilter
{
    public function allows(Message $message): bool
    {
        // do something
    }
}
```

_There are two strategies, that saying how filters will be resolved_

* match_all - all filters are met a condition (default)
* match_any - any filter are met a condition

Example configuration to change strategy

```yaml
andreo_event_sauce:
    acl:
        outbound: # or inbound
            filter_strategy:
                before: match_any # for before translate filters
                after: match_any # for after translate filters
```

There is a way to change strategy for one service using acl

```php

use Andreo\EventSauceBundle\Attribute\WithInboundAcl;
use Andreo\EventSauceBundle\Enum\FilterStrategy;

#[WithInboundAcl(beforeStrategy: FilterStrategy::MATCH_ANY)]
final class FooConsumer implements MessageConsumer
{
    public function handle(Message $message): void
    {
        // do something
    }
}
```

#### Limiting the scope of work

```php

use Andreo\EventSauceBundle\Attribute\ForInboundAcl;
use Andreo\EventSauceBundle\Attribute\ForOutboundAcl;

#[ForInboundAcl] // this will apply the translator for inbound acl
#[ForOutboundAcl] // this will apply the translator for outbound acl
#[AsMessageTranslator] // if you not defined any above attribute translator will be for all acl types
final class FooMessageTranslator implements MessageTranslator
{
    // ...
}
```

Optionally, You can define target to which will been applied translator or filter

```php
#[AsMessageFilter]
#[ForInboundAcl(target: FooConsumer::class)] // this will apply the filter only for FooConsumer::class
final class FooMessageFilter implements MessageFilter
{
    // ...
}
```

### Aggregates

[About Aggregates](https://eventsauce.io/docs/event-sourcing/create-an-aggregate-root/)

Example configuration

```yaml

andreo_event_sauce:
    aggregates:
        foo:
            class: App\Domain\Foo
        bar:
            class: App\Domain\Bar
```

Inject repository by convention "${name}Repository"

```php
use EventSauce\EventSourcing\AggregateRootRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;

final class FooHandler {

   public function __construct(
        #[Target('fooRepository')] private AggregateRootRepository $fooRepository
    ){}
}
```

### Message Outbox

[About Outbox](https://eventsauce.io/docs/message-outbox/)

Install the [package](https://github.com/andrew-pakula/eventsauce-outbox). \
I recommend reading the documentation.

```bash
composer require andreo/eventsauce-outbox
```

Example configuration

```yaml
andreo_event_sauce:
    outbox: true
    aggregates:
        foo:
            class: App\Domain\Foo
            outbox: true
```

Outbox process messages

```bash
bin/console andreo:event-sauce:outbox-process-messages
```

### Snapshotting

[About Snapshotting](https://eventsauce.io/docs/snapshotting/)

Example configuration

```yaml
andreo_event_sauce:
    snapshot: true
    aggregates:
        bar:
            class: App\Domain\Bar
            snapshot: true
```

Inject repository by convention "${name}Repository"

```php
use Symfony\Component\DependencyInjection\Attribute\Target;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;

final class BarHandler {

   public function __construct(
        #[Target('barRepository')] private AggregateRootRepositoryWithSnapshotting $barRepository
    ){}
}
```

#### Snapshotting extended components

Install the [package](https://github.com/andrew-pakula/eventsauce-snapshotting). \
I recommend reading the documentation.


```bash
composer require andreo/eventsauce-snapshotting
```

Example configuration

```yaml
andreo_event_sauce:
    snapshot:
        repository:
            doctrine: true # enable the doctrine repository
        versioned: true # enable versioning
        store_strategy:
            every_n_event: # store a snapshot every n event
                number: 200
            custom: # custom implementation Andreo\EventSauce\Snapshotting\CanStoreSnapshotStrategy
```

### Upcaster

[About Upcasting](https://eventsauce.io/docs/advanced/upcasting/#main-article)

Example configuration

```yaml
andreo_event_sauce:
    upcaster: true
    aggregates:
        foo:
            class: App\Domain\Foo
            upcaster: true
```

Upcaster example

```php
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use EventSauce\EventSourcing\Upcasting\Upcaster;

#[AsUpcaster(aggregate: 'foo', version: 2)]
final class FooEventV2Upcaster implements Upcaster {

    public function upcast(array $message): array
    {
        // do something
    }
}
```

#### Upcast with message argument

Install the [package](https://github.com/andrew-pakula/eventsauce-upcasting). \
I recommend reading the documentation.

```bash
composer require andreo/eventsauce-upcasting
```

Example configuration

```yaml
andreo_event_sauce:
    upcaster:
        argument: message
```

Upcaster example

```php
use Andreo\EventSauce\Upcasting\Event;
use Andreo\EventSauce\Upcasting\MessageUpcaster;
use EventSauce\EventSourcing\Message;

#[AsUpcaster(aggregate: 'foo', version: 2)]
final class SomeEventV2Upcaster implements MessageUpcaster {

    #[Event(event: SomeEvent::class)] // guess event
    public function upcast(Message $message): Message
    {
        // do something
    }
}
```

### Message decorator

About [Message Decorator](https://eventsauce.io/docs/advanced/message-decoration/)

Message decorator example

```php
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;

#[AsMessageDecorator]
final class SomeDecorator implements MessageDecorator
{
    public function decorate(Message $message): Message
    {
        // do something
    }
}
```

### Event Dispatcher

[About Event Dispatcher](https://eventsauce.io/docs/utilities/event-dispatcher/)

```yaml
andreo_event_sauce:
    event_dispatcher: true
```

Example usage the Event Dispatcher

```php
use EventSauce\EventSourcing\EventDispatcher;

final class FooHandler
{
    public function __construct(
        private readonly EventDispatcher $eventDispatcher
    ) {
    }

    public function __invoke(PublishBaz $command): void
    {
        $this->eventDispatcher->dispatch(
            new FooEvent()
        );
    }
}
```

#### Event dispatcher with message outbox

Example configuration

```yaml
andreo_event_sauce:
    outbox:
        repository:
            doctrine:
                table_name: outbox # default
    event_dispatcher:
        outbox: true
```

Then, you must create a table named _**outbox**_ according to [outbox schema](https://eventsauce.io/docs/message-outbox/table-schema/) \
If you want, you can use **migration generator** command

```bash
bin/console andreo:event-sauce:doctrine:migration:generate --schema=outbox
```

### Migration generator

[About Database Structure](https://eventsauce.io/docs/advanced/database-structure/)

This bundle uses the **all events in table per aggregate** approach. \
If you want have automatically generating migration, install [package](https://github.com/andrew-pakula/eventsauce-generate-migration). I recommend reading the documentation.

```bash
composer require andreo/eventsauce-generate-migration
```

```yaml
andreo_event_sauce:
    migration_generator:
        dependency_factory: doctrine.migrations.dependency_factory # default if migration bundle has been installed
```

Example command usage 

```bash
bin/console andreo:event-sauce:doctrine:migration:generate foo 
```

It generate migrations for an aggregate named _**foo**_

### Overwriting

All the event sauce components have been provided with simple interfaces and it promotes an composition above an extending. \
This package follows this way, so you can easily overwrite any component via its aliases interface.

You can also decorate an original repositories.

Example

```php
<?php
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\AggregateRootRepository;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'fooRepository')] // decorates original repository
final class FooRepository implements AggregateRootRepository
{
    public function __construct(private readonly AggregateRootRepository $fooOriginalRepository)
    {
    }

    public function retrieve(AggregateRootId $aggregateRootId): object
    {
        // do something
    }

    public function persist(object $aggregateRoot): void
    {
        // do something
    }
    public function persistEvents(AggregateRootId $aggregateRootId, int $aggregateRootVersion, object ...$events): void
    {
        // do something
    }
}
```

### Config reference

```yaml
andreo_event_sauce:
    time:
        timezone: UTC # your timezone
        clock: EventSauce\Clock\Clock # or your custom implementation

    message_storage:
        repository:
            memory: false
            doctrine:
                json_encode_options: # way to json format
                    - !php/const JSON_PRETTY_PRINT
                    - !php/const JSON_PRESERVE_ZERO_FRACTION
                connection: doctrine.dbal.default_connection
                table_schema: EventSauce\MessageRepository\TableSchema\TableSchema # or your custom implementation
                table_name: message_storage

    acl: # enable acl globally
        outbound:
            filter_strategy:
                before: match_all
                after: match_all
        inbound:
            filter_strategy:
                before: match_all
                after: match_all

    # one of: synchronous_message_dispatcher, messenger_message_dispatcher
    synchronous_message_dispatcher:
        chain:
            foo_dispatcher:
                acl: false
            bar_dispatcher:
                acl: false
    messenger_message_dispatcher:
        chain:
            foo_dispatcher:
                bus: fooBus # bas alias from messenger config
                acl: false

    event_dispatcher:
        outbox: false # enable outbox for event dispatcher
        
    upcaster:
        argument: payload # or message

    message_decorator: true

    outbox:
        back_off:
            # one of:
            exponential: # default
                initial_delay_ms: 100000
                max_tries: 10
            fibonacci:
                initial_delay_ms: 100000
                max_tries: 10
            linear:
                initial_delay_ms: 100000
                max_tries: 10
            no_waiting:
                max_tries: 10
            immediately: true
            custom: # or your custom back off strategy
                id: App/Outbox/CustomBackOfStrategy
        relay_commit:
            # one of:
            delete:
                enabled: true
            mark_consumed: # default
                enabled: true
        repository:
            # one of:
            memory: false
            doctrine: # default
                table_name: outbox
        logger: outbox_logger # logger for outbox

    snapshot:
        versioned: true # snapshots with versioning
        store_strategy:
            every_n_event:
                number: 500 # store snapshot every n event
        repository:
            # one of:
            memory: true # default
            doctrine:
                table_name: snapshot

    serializer:
        payload: EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer # or your custom implementation
        message: EventSauce\EventSourcing\Serialization\ConstructingMessageSerializer # or your custom implementation
        snapshot: Andreo\EventSauce\Snapshotting\SnapshotStateSerializer # or your custom implementation

    migration_generator:
        dependency_factory: doctrine.migrations.dependency_factory # default if migration bundle has been installed
    uuid_encoder: EventSauce\UuidEncoding\UuidEncoder # or your custom implementation
    class_name_inflector: EventSauce\EventSourcing\ClassNameInflector # or your custom implementation

    aggregates:
        foo:
            class: App\Domain\Foo
            repository_alias: fooRepository
            outbox: false # enable outbox for this aggregate
            dispatchers:
                - foo_dispatcher # dispatchers used by this aggregate. Default send to all defined
            upcaster: false # enable upcaster for this aggregate
            snapshot: false # enable snapshot for this aggregate
```