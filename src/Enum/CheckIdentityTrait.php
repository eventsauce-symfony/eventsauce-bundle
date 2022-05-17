<?php

declare(strict_types=1);

namespace Andreo\EventSauceBundle\Enum;

trait CheckIdentityTrait
{
    public function identity(CheckIdentity $other): bool
    {
        return $other === $this;
    }
}
