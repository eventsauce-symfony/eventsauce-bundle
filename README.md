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

```yaml
andreo_event_sauce:
    time:
        timezone: UTC #default
```

### Message Storage

About [message storage](https://eventsauce.io/docs/message-storage/)

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

```yaml
andreo_event_sauce:
    message_storage:
        repository:
            doctrine:
                connection:  doctrine.dbal.default_connection #default
```

### Synchronous message dispatcher

Defaults EventSauce to dispatch events use [SynchronousMessageDispatcher](https://eventsauce.io/docs/reacting-to-events/setup-consumers/#synchronous-message-dispatcher).

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

### Messenger message dispatcher

Install the [package](https://github.com/andrew-pakula/eventsauce-messenger). I recommend reading the documentation


```bash
composer require andreo/eventsauce-messenger
```

If you don't have the messenger, try install

```bash
composer require symfony/messenger
```

Example configuration

```yaml
andreo_event_sauce:
    messenger_message_dispatcher:
        chain:
            foo_dispatcher:
                bus: fooBus
            bar_dispatcher:
                bus: barBus

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

#### Message dispatching

By default, messages are dispatch by all dispatchers,
but you can specify them per aggregate.

```yaml
andreo_event_sauce:
    message:
        dispatcher:
            chain:
                - fooBus
                - barBus
    aggregates:
        foo:
            class: App\Domain\Foo
            dispatchers:
                - barBus # dispatch only by barBas
```

### Outbox

[About the Outbox](https://eventsauce.io/docs/message-outbox/)

You need install the [package](https://github.com/andrew-pakula/eventsauce-outbox) (recommend reading doc).

```bash
composer require andreo/eventsauce-outbox
```

```yaml
andreo_event_sauce:
    outbox: true # enable outbox and register its services
    aggregates:
        foo:
            class: App\Domain\Foo
            outbox: true # register doctrine transactional repository and outbox relay per aggregate
```

#### Outbox Back-off Strategy

About the [Back-off Strategy](https://github.com/EventSaucePHP/BackOff)

By default, this bundle uses [ExponentialBackOffStrategy](https://github.com/EventSaucePHP/BackOff#exponential-back-off).
You can change it. An example configuration is as follows

```yaml
andreo_event_sauce:
    outbox:
        back_off:
            fibonacci: # default is exponential. More data in config reference
                initial_delay_ms: 100000
                max_tries: 10
```

#### Outbox Commit Strategy

By default, this bundle uses **MarkMessagesConsumedOnCommit** strategy
You can change it. An example configuration is as follows

```yaml
andreo_event_sauce:
    outbox:
        relay_commit:
            delete: true # default is mark_consumed
```

#### Outbox repository

By default, outbox messages are stored in a database.
If you want to store them in a memory, add the following configuration

```yaml
andreo_event_sauce:
    outbox:
        repository:
            memory: true # default is doctrine
```

Outbox messages dispatching command

```bash
php bin/console andreo:event-sauce:outbox-process-messages
```

### Snapshotting

[About Snapshotting](https://eventsauce.io/docs/snapshotting/)

```yaml
andreo_event_sauce:
    snapshot: true # enable snapshot and register its services
    aggregates:
        bar:
            class: App\Domain\Bar
            snapshot: true # register snapshot repository per aggregate
```

Then you can inject the repository based on the alias and dedicated interface

```php
use Symfony\Component\DependencyInjection\Attribute\Target;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;

final class SomeHandler {

   public function __construct(
        #[Target('barRepository')] private AggregateRootRepositoryWithSnapshotting $barRepository
    ){}
}
```


### Snapshotting extended components

You need install the [package](https://github.com/andrew-pakula/eventsauce-snapshotting)
(recommend reading doc).

```bash
composer require andreo/eventsauce-snapshotting
```

#### Snapshot Doctrine repository

By default, snapshots are stored in a memory.
If you want to store them in a database with doctrine,
add the following configuration

```yaml
andreo_event_sauce:
    snapshot:
        repository:
            doctrine: true # default is memory
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

Defining the upcaster is as follows

```php
use Andreo\EventSauceBundle\Attribute\AsUpcaster;
use EventSauce\EventSourcing\Upcasting\Upcaster;

#[AsUpcaster(aggregate: 'foo', version: 2)]
final class SomeEventV2Upcaster implements Upcaster {

    public function upcast(array $message): array
    {
        // do something
    }
}
```

### Upcaster argument

By default, this library uses the payload context according to the EventSauce implementation.
If you want to upcasting on the message object context,
you need install the [package](https://github.com/andrew-pakula/eventsauce-upcasting) (recommend reading doc).

```bash
composer require andreo/eventsauce-upcasting
```

and use the following configuration.

```yaml
andreo_event_sauce:
    upcast:
        argument: message # default is payload
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

Defining the message decorator is as follows

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

By default, message decoration is enabled. You can it disabled.

```yaml
andreo_event_sauce:
    message:
        decorator: false
```

#### Message decorating context

In this bundle, messages can be decorated at the aggregate level,
or at a completely different level of the event dispatcher.
You can specify the context in which the decorator is to be used


```php
use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use EventSauce\EventSourcing\Message;
use EventSauce\EventSourcing\MessageDecorator;
use Andreo\EventSauceBundle\Attribute\MessageContext;

#[AsMessageDecorator(context: MessageContext::EVENT_DISPATCHER)]
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

To simplify the creation of migrations,
I have created a [package](https://github.com/andrew-pakula/eventsauce-generate-migration) that allows you to automatically
generate migrations based on the name of the aggregate.
More information in the documentation

```bash
composer require andreo/eventsauce-generate-migration
```

For example, to generate migrations for the following configuration

```yaml
andreo_event_sauce:
    outbox: true
    aggregates:
        foo:
            class: App\Domain\Foo
            outbox: true
```

Execute the following command

```bash
php bin/console andreo:event-sauce:doctrine:migration:generate foo --schema=event --schema=outbox 
```

and default doctrine migration command

```bash
php bin/console d:m:m
```

### Serialization

#### Message serializer for MySQL8

```yaml
andreo_event_sauce:
    message:
        serializer: EventSauce\EventSourcing\Serialization\MySQL8DateFormatting # or your custom serializer
```
