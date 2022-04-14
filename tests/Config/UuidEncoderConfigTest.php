<?php

declare(strict_types=1);

namespace Tests\Config;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\UuidEncoding\UuidEncoder;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\Dummy\DummyUuidEncoder;

final class UuidEncoderConfigTest extends AbstractExtensionTestCase
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
    public function should_register_custom_uuid_encoder(): void
    {
        $this->load([
            'uuid_encoder' => DummyUuidEncoder::class,
        ]);

        $this->assertContainerBuilderHasAlias(UuidEncoder::class);
        $uuidEncoderAlias = $this->container->getAlias(UuidEncoder::class);
        $this->assertEquals(DummyUuidEncoder::class, $uuidEncoderAlias->__toString());
    }
}
