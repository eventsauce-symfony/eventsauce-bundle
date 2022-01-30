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

### Features

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

```php
composer require andreo/event-sauce-bundle
```

After completing the installation process, verify that 
the bundle has been added the `config/bundles.php` file

```php
return [
    Andreo\EventSauceBundle\AndreoEventSauceBundle::class => ['all' => true],
];
```

### Configuration

```yaml

```

