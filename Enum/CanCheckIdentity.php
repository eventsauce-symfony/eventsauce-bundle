<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Enum;

interface CanCheckIdentity
{
    public function identity(self $other): bool;
}
