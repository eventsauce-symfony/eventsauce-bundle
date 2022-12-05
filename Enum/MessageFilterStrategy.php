<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Enum;

enum MessageFilterStrategy: string implements CanCheckIdentity
{
    use CheckIdentityTrait;

    case MATCH_ALL = 'match_all';
    case MATCH_ANY = 'match_any';
}
