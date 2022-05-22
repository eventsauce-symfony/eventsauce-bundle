<p align="center">
    <a href="https://eventsauce.io">
        <img src="https://eventsauce.io/static/logo.svg" height="150px" width="150px">
    </a>
</p>

# EventSauceBundle

This bundle provides the basic and extended container configuration of
symfony for the [EventSauce](https://eventsauce.io/) library.
Before using it, I strongly recommend that you read the official [documentation](https://eventsauce.io/docs/).

### Supports

- Doctrine event message repository
- All events in table per aggregate type
- Message Outbox
- Symfony messenger
- Snapshot doctrine repository, versioning, store strategies
- Generating migrations per aggregate
- ACL

and more...

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

You probably want to set your time zone.

Configuration

```yaml
andreo_event_sauce:
    time:
        timezone: UTC #default
```

### Message Storage

[About Message Storage](https://eventsauce.io/docs/message-storage/)

#### Doctrine

Perhaps you want to set doctrine dbal connection. \
If you don't have doctrine, try install

```bash
composer require doctrine/doctrine-bundle
```

or, if you will be using migration and orm

```bash
composer require symfony/orm-pack
```

Configuration

```yaml
andreo_event_sauce:
    message_storage:
        repository:
            doctrine:
                connection:  doctrine.dbal.default_connection #default
```

### Synchronous message dispatcher

By default EventSauce dispatches events using [SynchronousMessageDispatcher](https://eventsauce.io/docs/reacting-to-events/setup-consumers/#synchronous-message-dispatcher).

Configuration

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

### Messenger message dispatcher

Install the [package](https://github.com/andrew-pakula/eventsauce-messenger). I recommend reading the documentation


```bash
composer require andreo/eventsauce-messenger
```

If you don't have the messenger, try install

```bash
composer require symfony/messenger
```

Configuration

```yaml
andreo_event_sauce:
    messenger_message_dispatcher:
        chain:
            foo_dispatcher:
                bus: fooBus
            bar_dispatcher:
                bus: barBus

```

### Anti-Corruption Layer

[About ACL](https://eventsauce.io/docs/advanced/anti-corruption-layer/)

// to do

### Aggregates

[About Aggregates](https://eventsauce.io/docs/event-sourcing/create-an-aggregate-root/)

Configuration

```yaml

andreo_event_sauce:
    aggregates:
        foo:
            class: App\Domain\Foo
        bar:
            class: App\Domain\Bar
```

Then you can inject the repository based on the alias and dedicated
interface. By default, alias is created automatically
by convention "${name}Repository"

```php
use EventSauce\EventSourcing\AggregateRootRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;

final class SomeHandler {

   public function __construct(
        #[Target('fooRepository')] private AggregateRootRepository $fooRepository
    ){}
}
```

### Outbox

[About Outbox](https://eventsauce.io/docs/message-outbox/)

Install the [package](https://github.com/andrew-pakula/eventsauce-outbox). I recommend reading the documentation.

```bash
composer require andreo/eventsauce-outbox
```

Configuration

```yaml
andreo_event_sauce:
    outbox: true # enable outbox and register its services
    aggregates:
        foo:
            class: App\Domain\Foo
            outbox: true # register doctrine transactional repository and outbox relay per aggregate
```

Outbox process messages

```bash
php bin/console andreo:event-sauce:outbox-process-messages
```

### Snapshotting

[About Snapshotting](https://eventsauce.io/docs/snapshotting/)

Configuration

```yaml
andreo_event_sauce:
    snapshot: true # enable snapshot and register its services
    aggregates:
        bar:
            class: App\Domain\Bar
            snapshot: true # register snapshot repository per aggregate
```

Inject repository

```php
use Symfony\Component\DependencyInjection\Attribute\Target;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;

final class BarHandler {

   public function __construct(
        #[Target('barRepository')] private AggregateRootRepositoryWithSnapshotting $barRepository
    ){}
}
```

### Snapshotting extended components

Install the [package](https://github.com/andrew-pakula/eventsauce-snapshotting). I recommend reading the documentation.


```bash
composer require andreo/eventsauce-snapshotting
```

#### Snapshot Doctrine repository

```yaml
andreo_event_sauce:
    snapshot:
        repository:
            doctrine: true
    aggregates:
        foo:
            class: App\Domain\Foo
            snapshot: true
```

#### Snapshot versioning

```yaml
andreo_event_sauce:
    snapshot:
        versioned: true # default is false
    aggregates:
        foo:
            class: App\Domain\Foo
            snapshot: true
```

#### Snapshot store strategy

```yaml
andreo_event_sauce:
    snapshot:
        store_strategy:
            every_n_event: # Store snapshot every n event
                number: 200
            custom: # Or you can set your own strategy
    aggregates:
        foo:
            class: App\Domain\Foo
            snapshot: true
```

### Upcasting

[About Upcasting](https://eventsauce.io/docs/advanced/upcasting/#main-article)

```yaml
andreo_event_sauce:
    upcast: true # enable upcast and register its services
    aggregates:
        foo:
            class: App\Domain\Foo
            upcast: true # register  upcaster chain per aggregate
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

### Upcaster with message argument

Install the [package](https://github.com/andrew-pakula/eventsauce-upcasting). I recommend reading the documentation.

```bash
composer require andreo/eventsauce-upcasting
```

Configuration

```yaml
andreo_event_sauce:
    upcast:
        argument: message
    aggregates:
        foo:
            class: App\Domain\Foo
            upcast: true
```

Defining the upcaster is as follows

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

### Message decorating

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

### Database Structure

[About Database Structure](https://eventsauce.io/docs/advanced/database-structure/)

This bundle uses the **all events in table per aggregate** approach.
Event messages, outbox messages, and snapshots are stored in a separate table per aggregate type

#### Generating migrations per aggregate

Install [package](https://github.com/andrew-pakula/eventsauce-generate-migration). I recommend reading the documentation.

```bash
composer require andreo/eventsauce-generate-migration
```

```yaml
andreo_event_sauce:
    migration_generator:
        dependency_factory: doctrine.migrations.dependency_factory #default
```

### Serialization

```yaml
andreo_event_sauce:
    serializer:
        message: EventSauce\EventSourcing\Serialization\MySQL8DateFormatting #custom for mysql8
        payload: EventSauce\EventSourcing\Serialization\ConstructingPayloadSerializer #default
        snapshot: Andreo\EventSauce\Snapshotting\SnapshotStateSerializer #default
```
