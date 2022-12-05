<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use DateTimeZone;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class ClockLoader
{
    public static function load(ContainerBuilder $container, array $config): void
    {
        $clockConfig = $config['clock'];
        $recordingTimezoneConfig = $clockConfig['timezone'];
        $container
            ->register('andreo.eventsauce.clock.timezone', DateTimeZone::class)
            ->addArgument($recordingTimezoneConfig)
            ->setPublic(false)
        ;
    }
}
