<p align="center">
    <a href="https://eventsauce.io">
        <img src="https://eventsauce.io/static/logo.svg" height="150px" width="150px">
    </a>
</p>

# Symfony EventSauce [WIP]

This bundle provides the basic and extended container configuration of 
symfony for the [EventSauce](https://eventsauce.io/) library.
Before using it, I strongly recommend that you read the official [documentation](https://eventsauce.io/docs/).

### Supports

- Doctrine event message repository
- All events in table per aggregate type
- Outbox pattern
- Symfony messenger
- Symfony serializer
- Snapshot doctrine repository
- Snapshot versioning
- Automatic generate migration for every aggregate
- Message upcasting

### Requirements

- PHP ^8.1
- Symfony ^6.0

### Installation

```bash
composer require andreo/event-sauce-bundle
```

After completing the installation process, verify that 
the bundle has been added the `config/bundles.php` file

```php
return [
    Andreo\EventSauceBundle\AndreoEventSauceBundle::class => ['all' => true],
];
```

### Timezone

You probably want to set your time zone. Default value is UTC

```yaml

andreo_event_sauce:
    time:
        recording_timezone: Europe/Warsaw 
```

### Message dispatching
Defaults EventSauce to dispatch events use [SynchronousMessageDispatcher](https://eventsauce.io/docs/reacting-to-events/setup-consumers/#synchronous-message-dispatcher).
An example configuration for this case is as follows

```yaml

andreo_event_sauce:
    message:
        dispatcher:
            chain:
                - fooBus
```

Defining the consumer is as follows

```php

use EventSauce\EventSourcing\MessageConsumer;
use Andreo\EventSauceBundle\Attribute\AsMessageConsumer;

#[AsMessageConsumer(dispatcher: fooBus)]
final class FooConsumer implements MessageConsumer {

}
```

### Message dispatching with symfony messenger

If you want to use the symfony messenger component for dispatch messages, 
you need to install the package

```bash
composer require andreo/eventsauce-messenger
```

An example configuration for this case is as follows

```yaml

andreo_event_sauce:
    message:
        dispatcher:
            messenger:
                mode: event
            chain:
                fooBus: barBus # message bus alias from messenger config

```

The mode option is a way of dispatch messages. Available values:

`event`

- Event is only send to the handler that supports the  event type 
- Doesn't send headers

`event_with_headers`

- Event is only send to the handler that supports the  event type
- Receive of message headers in the second handler argument

`message`

- Message is send to the any handler that supports the Message type. You have to manually check event type
- Message object includes the event and headers

### Aggregates

An example configuration for two aggregates is as follows

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
            repository_alias: # default is created automatically by convention "${name}Repository"
            dispatchers:
                - fooBus
                - barBus
        bar:
            class: App\Domain\Bar
            dispatchers:
                - barBus
```

Then you can inject the repository based on the alias and dedicated interface

```php
use EventSauce\EventSourcing\AggregateRootRepository;
use Symfony\Component\DependencyInjection\Attribute\Target;

final class FooHandler {

   public function __construct(
        #[Target('fooRepository')] private AggregateRootRepository $fooRepository
    ){}
}
```

### Outbox

[About Outbox](https://eventsauce.io/docs/message-outbox/)

```yaml

andreo_event_sauce:
    message:
        dispatcher:
            chain:
                - fooBus
    outbox: # enable outbox and register its services
        enabled: true
    aggregates:
        foo:
            class: App\Domain\Foo
            dispatchers:
                - fooBus
            outbox: true # register doctrine transactional repository and outbox relay per aggregate
```

Process outbox messages

```bash
php bin/console andreo:event-sauce:outbox-process-messages
```

### Snapshotting

[About Snapshotting](https://eventsauce.io/docs/snapshotting/)

```yaml

andreo_event_sauce:
    message:
        dispatcher:
            chain:
                - fooBus
    snapshot: # enable snapshot and register its services
        enabled: true
    aggregates:
        bar:
            class: App\Domain\Foo
            dispatchers:
                - fooBus
            snapshot: true # register snapshot repository per aggregate
```

Then you can inject the repository based on the alias and dedicated interface

```php
use Symfony\Component\DependencyInjection\Attribute\Target;
use EventSauce\EventSourcing\Snapshotting\AggregateRootRepositoryWithSnapshotting;

final class FooHandler {

   public function __construct(
        #[Target('barRepository')] private AggregateRootRepositoryWithSnapshotting $barRepository
    ){}
}
```