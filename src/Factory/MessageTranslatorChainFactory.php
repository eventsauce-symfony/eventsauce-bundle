<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Factory;

use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslator;
use EventSauce\EventSourcing\AntiCorruptionLayer\MessageTranslatorChain;

final class MessageTranslatorChainFactory
{
    /**
     * @param iterable<MessageTranslator> $translators
     */
    public static function create(iterable $translators): MessageTranslator
    {
        return new MessageTranslatorChain(...$translators);
    }
}
