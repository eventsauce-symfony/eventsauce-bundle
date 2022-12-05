<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Enum;

enum MessageFilterTrigger: string implements CanCheckIdentity
{
    use CheckIdentityTrait;

    case BEFORE_TRANSLATE = 'before_translate';
    case AFTER_TRANSLATE = 'after_translate';
}
