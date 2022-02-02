<?php

declare(strict_types=1);

namespace Tests\ConfigExtension;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\Serialization\PayloadSerializer;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\ConfigExtension\Dummy\DummyCustomPayloadSerializer;

final class SerializerConfigTest extends AbstractExtensionTestCase
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
    public function custom_payload_serializer_is_loading(): void
    {
        $this->load([
            'payload_serializer' => DummyCustomPayloadSerializer::class,
        ]);

        $this->assertContainerBuilderHasAlias(PayloadSerializer::class);
        $payloadSerializerAlias = $this->container->getAlias(PayloadSerializer::class);
        $this->assertEquals(DummyCustomPayloadSerializer::class, $payloadSerializerAlias->__toString());
    }
}
