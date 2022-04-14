<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle;

use Andreo\EventSauce\Messenger\DependencyInjection\HandleEventSauceMessageMiddlewarePass;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclInboundPass;
use Andreo\EventSauceBundle\DependencyInjection\CompilerPass\AclOutboundPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class AndreoEventSauceBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        if ($container->hasParameter('andreo.eventsauce.acl_outbound')) {
            $container->addCompilerPass(new AclOutboundPass(), priority: -10);
        }
        if ($container->hasParameter('andreo.eventsauce.acl_inbound')) {
            $container->addCompilerPass(new AclInboundPass(), priority: -10);
        }
        if ($container->hasParameter('andreo.eventsauce.messenger_dispatcher')) {
            $container->addCompilerPass(new HandleEventSauceMessageMiddlewarePass(), priority: -15);
        }
    }
}
