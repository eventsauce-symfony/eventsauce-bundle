<?php

declare(strict_types=1);

namespace Tests\Dummy;

use Andreo\EventSauceBundle\Attribute\AclInboundTarget;
use Andreo\EventSauceBundle\Attribute\AclOutboundTarget;
use Andreo\EventSauceBundle\Attribute\AsMessageTranslator;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslator;
use EventSauce\EventSourcing\Message;

#[AsMessageTranslator]
#[AclInboundTarget]
#[AclOutboundTarget]
final class DummyMessageTranslator implements MessageTranslator
{
    public function translateMessage(Message $message): Message
    {
    }
}
