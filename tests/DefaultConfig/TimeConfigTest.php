<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\Clock\Clock;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Dummy\DummyClock;

final class TimeConfigTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }

    /**
     * @test
     */
    public function should_load_time(): void
    {
        $this->load([
            'time' => [
                'timezone' => 'Europe/Warsaw',
                'clock' => DummyClock::class,
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'andreo.eventsauce.time.timezone',
            0,
            'Europe/Warsaw'
        );

        $this->assertContainerBuilderHasAlias(Clock::class);
        $clockAlias = $this->container->getAlias(Clock::class);
        $this->assertEquals(DummyClock::class, $clockAlias->__toString());
    }
}
