<?php

declare(strict_types=1);

namespace Tests\CompilerPass;

use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclOutboundPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AclOutboundPassTest extends AbstractCompilerPassTestCase
{
    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AclOutboundPass());
    }
}
