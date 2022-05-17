<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Enum;

enum FilterStrategy: string implements CheckIdentity
{
    use CheckIdentityTrait;

    case MATCH_ALL = 'match_all';
    case MATCH_ANY = 'match_any';
}
