<?php

declare(strict_types=1);

namespace Stn\Workflow\Action;

use Stn\Workflow\FSM\StateMachineInterface;

abstract class BaseAction
{
    public function __invoke(StateMachineInterface $fsm, ...$args): void
    {

    }
}
