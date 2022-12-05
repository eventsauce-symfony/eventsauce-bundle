<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Tests\DependencyInjection\Config;

use Andreo\EventSauceBundle\Attribute\AsMessageDecorator;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\Tests\Doubles\DummyMessageDecorator;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;

final class MessageDecoratorConfigTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_load_default_message_decorator(): void
    {
        $this->load();

        $attributes = $this->container->getAutoconfiguredAttributes();
        $this->assertArrayHasKey(AsMessageDecorator::class, $attributes);
        $this->assertContainerBuilderHasService('andreo.eventsauce.message_decorator_chain');

        $this->container
            ->register(DummyMessageDecorator::class, DummyMessageDecorator::class)
            ->setAutoconfigured(true)
        ;
        $this->container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(DummyMessageDecorator::class, 'andreo.eventsauce.message_decorator', [
            'priority' => 10,
        ]);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
