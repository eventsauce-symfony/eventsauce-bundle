<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use Andreo\EventSauce\Messenger\DependencyInjection\HandleEventWithHeadersPass;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AndreoEventSauceBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addResource(new ClassExistenceResource(HandleEventWithHeadersPass::class));
        if (class_exists(HandleEventWithHeadersPass::class)) {
            $container->addCompilerPass(new HandleEventWithHeadersPass());
        }
    }
}
