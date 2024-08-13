<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

use Stn\Workflow\FSM\StateMachineInterface;

interface GuardInterface
{
    public function canTransition(StateMachineInterface $fsm, ...$args): bool;
}
