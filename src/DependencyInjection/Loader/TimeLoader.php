<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\DependencyInjection\Loader;

use DateTimeZone;
use EventSauce\Clock\Clock;
use EventSauce\Clock\SystemClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TimeLoader
{
    public function __construct(private ContainerBuilder $container)
    {
    }

    public function __invoke(array $config): void
    {
        $timeConfig = $config['time'];
        $recordingTimezoneConfig = $timeConfig['timezone'];
        $this->container
            ->register('andreo.eventsauce.time.timezone', DateTimeZone::class)
            ->addArgument($recordingTimezoneConfig)
            ->setPublic(false)
        ;

        $clockId = $timeConfig['clock'];
        if (!in_array($clockId, [null, Clock::class, SystemClock::class], true)) {
            $this->container->setAlias(Clock::class, $clockId);
        }
    }
}
