<?php

declare(strict_types=1);

namespace Tests;

use ID\Workflow\Context\ContextInterface;
use ID\Workflow\State\BaseState;

class State extends BaseState
{
    public function canTransition(ContextInterface $context, ...$args): bool
    {
        return true;
    }
}
