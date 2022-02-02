<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\Clock\Clock;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\ConfigExtension\Dummy\DummyCustomClock;

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
    public function time_is_loading(): void
    {
        $this->load([
            'time' => [
                'recording_timezone' => 'Europe/Warsaw',
                'clock' => DummyCustomClock::class,
            ],
        ]);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            'andreo.event_sauce.recording_timezone',
            0,
            'Europe/Warsaw'
        );

        $this->assertContainerBuilderHasAlias(Clock::class);
        $clockAlias = $this->container->getAlias(Clock::class);
        $this->assertEquals(DummyCustomClock::class, $clockAlias->__toString());
    }
}
