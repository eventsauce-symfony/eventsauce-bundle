<?php

declare(strict_types=1);

namespace Tests\DefaultConfig;

use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use EventSauce\EventSourcing\ClassNameInflector;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use Tests\Dummy\DummyClassNameInflector;

final class ClassNameInflectorConfigTest extends AbstractExtensionTestCase
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
    public function should_register_class_name_inflector(): void
    {
        $this->load([
            'class_name_inflector' => DummyClassNameInflector::class,
        ]);

        $this->assertContainerBuilderHasAlias(ClassNameInflector::class);
        $classNameInflectorAlias = $this->container->getAlias(ClassNameInflector::class);
        $this->assertEquals(DummyClassNameInflector::class, $classNameInflectorAlias->__toString());
    }
}
