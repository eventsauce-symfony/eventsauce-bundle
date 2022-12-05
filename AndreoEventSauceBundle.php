<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclPass;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\SyncMessageConsumerPass;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\UpcasterPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AndreoEventSauceBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new UpcasterPass());
        $container->addCompilerPass(new SyncMessageConsumerPass());
        $container->addCompilerPass(new AclPass(), priority: -10);
    }
}
