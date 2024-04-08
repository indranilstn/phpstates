<?php

declare(strict_types=1);

namespace Stn\Workflow\Action;

use Stn\Workflow\Context\ContextInterface;
use Stn\Workflow\FSM\StateMachineInterface;

abstract class BaseAction
{
    public function __invoke(StateMachineInterface $fsm, ContextInterface $context, ...$args): void
    {

    }
}
