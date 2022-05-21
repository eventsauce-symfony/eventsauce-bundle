<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use Andreo\EventSauce\Messenger\DependencyInjection\HandleEventSauceMessageMiddlewarePass;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclInboundPass;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclOutboundPass;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AndreoEventSauceBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new AclOutboundPass('andreo.eventsauce.acl_outbound_enabled'), priority: -10);
        $container->addCompilerPass(new AclInboundPass('andreo.eventsauce.acl_inbound_enabled'), priority: -10);

        $container->addResource(new ClassExistenceResource(HandleEventSauceMessageMiddlewarePass::class));
        if (class_exists(HandleEventSauceMessageMiddlewarePass::class)) {
            $container->addCompilerPass(new HandleEventSauceMessageMiddlewarePass('andreo.eventsauce.messenger_enabled'), priority: -15);
        }
    }
}
