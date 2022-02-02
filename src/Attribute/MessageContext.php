<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Attribute;

enum MessageContext
{
    case AGGREGATE;
    case EVENT_DISPATCHER;
    case ALL;
}
