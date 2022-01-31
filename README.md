<p align="center">
    <a href="https://eventsauce.io">
        <img src="https://eventsauce.io/static/logo.svg" height="150px" width="150px">
    </a>
</p>

<p align="center">
    <a href="https://github.com/EventSaucePHP/EventSauce/actions">
        <img src="https://github.com/EventSaucePHP/EventSauce/workflows/Tests/badge.svg" alt="Build Status">
    </a>
    <a href="https://scrutinizer-ci.com/g/EventSaucePHP/EventSauce/?branch=master">
        <img src="https://scrutinizer-ci.com/g/EventSaucePHP/EventSauce/badges/quality-score.png?b=master" alt="Scrutinizer Code Quality">
    </a>
    <a href="https://scrutinizer-ci.com/g/EventSaucePHP/EventSauce/?branch=master">
        <img src="https://scrutinizer-ci.com/g/EventSaucePHP/EventSauce/badges/coverage.png?b=master" alt="Code Coverage">
    </a>
    <a href="https://packagist.org/packages/eventsauce/eventsauce">
        <img src="https://img.shields.io/packagist/v/eventsauce/eventsauce.svg" alt="Latest Stable Version">
    </a>
    <a href="https://packagist.org/packages/eventsauce/eventsauce">
        <img src="https://img.shields.io/packagist/dt/eventsauce/eventsauce.svg" alt="Total Downloads">
    </a>
</p>

# Symfony EventSauce

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
    dispatcher:
        chain:
            eventBus: #just define an alias
```

Defining the consumer is as follows

```php

use EventSauce\EventSourcing\MessageConsumer;
use Andreo\EventSauceBundle\Attribute\AsMessageConsumer;

#[AsMessageConsumer(dispatcher: eventBus)]
final class FooConsumer implements MessageConsumer {

}
```

If you need it, you can inject dispatcher

```php

use Symfony\Component\DependencyInjection\Attribute\Target;
use EventSauce\EventSourcing\MessageDispatcher;

final class Example {
    public function __construct(
        #[Target('eventBus')] private MessageDispatcher $fooBus
    ){}
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
    dispatcher:
        messenger:
            mode: event
        chain:
            eventBus: outboxBus # message bus alias from messenger config

```

The mode option is a way of dispatch messages. Available values:

`event`

- Event is only send to the handler that supports the  event type 
- Doesn't send headers

`event_with_headers`

- Event is only send to the handler that supports the  event type
- Receive of message headers in the second handler argument

`message`

- Message is send to the any handler that supports the Message type