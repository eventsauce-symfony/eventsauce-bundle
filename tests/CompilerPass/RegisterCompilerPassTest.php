<?php

declare(strict_types=1);

namespace Tests\CompilerPass;

use Andreo\EventSauce\Messenger\DependencyInjection\HandleEventSauceMessageMiddlewarePass;
use Andreo\EventSauceBundle\AndreoEventSauceBundle;
use Andreo\EventSauceBundle\DependencyInjection\AndreoEventSauceExtension;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclInboundPass;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclOutboundPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;

final class RegisterCompilerPassTest extends AbstractExtensionTestCase
{
    /**
     * @test
     */
    public function should_register_inbound_acl_pass(): void
    {
        $this->load([
            'acl' => [
                'inbound' => true,
                'outbound' => false,
            ],
        ]);

        $bundle = new AndreoEventSauceBundle();
        $bundle->build($this->container);

        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof AclInboundPass) {
                $this->assertTrue(true);
            }
        }
        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof AclOutboundPass) {
                $this->fail();
            }
        }
    }

    /**
     * @test
     */
    public function should_register_outbound_acl_pass(): void
    {
        $this->load([
            'acl' => [
                'inbound' => false,
                'outbound' => true,
            ],
        ]);

        $bundle = new AndreoEventSauceBundle();
        $bundle->build($this->container);

        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof AclOutboundPass) {
                $this->assertTrue(true);
            }
        }
        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof AclInboundPass) {
                $this->fail();
            }
        }
    }

    /**
     * @test
     */
    public function should_register_acl_pass(): void
    {
        $this->load([
            'acl' => true,
        ]);

        $bundle = new AndreoEventSauceBundle();
        $bundle->build($this->container);

        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof AclOutboundPass) {
                $this->assertTrue(true);
            }
        }
        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof AclInboundPass) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * @test
     */
    public function should_register_messenger_pass(): void
    {
        $this->load([
            'messenger_message_dispatcher' => true,
        ]);

        $bundle = new AndreoEventSauceBundle();
        $bundle->build($this->container);

        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof HandleEventSauceMessageMiddlewarePass) {
                $this->assertTrue(true);
            }
        }
    }

    /**
     * @test
     */
    public function should_not_register_messenger_pass(): void
    {
        $this->load([
            'messenger_message_dispatcher' => false,
        ]);

        $bundle = new AndreoEventSauceBundle();
        $bundle->build($this->container);

        foreach ($this->container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof HandleEventSauceMessageMiddlewarePass) {
                $this->fail();
            }
        }

        $this->assertTrue(true);
    }

    protected function getContainerExtensions(): array
    {
        return [
            new AndreoEventSauceExtension(),
        ];
    }
}
