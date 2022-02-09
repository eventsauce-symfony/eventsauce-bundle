<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use Andreo\EventSauce\Messenger\DependencyInjection\HandleEventAndHeadersPass;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AndreoEventSauceBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addResource(new ClassExistenceResource(HandleEventAndHeadersPass::class));
        if (class_exists(HandleEventAndHeadersPass::class)) {
            $container->addCompilerPass(new HandleEventAndHeadersPass(), priority: -10);
        }
    }
}
