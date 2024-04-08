<?php

declare(strict_types=1);

namespace Stn\Tests;

use Stn\Workflow\Context\ContextInterface;
use Stn\Workflow\State\BaseState;

class State extends BaseState
{
    public function canTransition(ContextInterface $context, ...$args): bool
    {
        return true;
    }
}
