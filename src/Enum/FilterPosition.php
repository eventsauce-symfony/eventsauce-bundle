<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Enum;

enum FilterPosition: string implements CheckIdentity
{
    use CheckIdentityTrait;

    case BEFORE = 'before';
    case AFTER = 'after';
}
