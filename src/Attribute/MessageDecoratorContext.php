<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

enum MessageDecoratorContext
{
    case AGGREGATE;
    case EVENT_DISPATCHER;
    case ALL;
}
