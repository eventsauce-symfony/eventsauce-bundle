<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Enum;

interface CheckIdentity
{
    public function identity(self $other): bool;
}
