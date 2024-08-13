<?php

declare(strict_types=1);

namespace Stn\Tests;

use Stn\Workflow\FSM\StateMachineInterface;
use Stn\Workflow\State\BaseState;

class State extends BaseState
{
    public function canTransition(StateMachineInterface $fsm, ...$args): bool
    {
        return true;
    }
}
