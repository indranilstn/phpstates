<?php

declare(strict_types=1);

namespace ID\Workflow\Action;

use ID\Workflow\Context\ContextInterface;
use ID\Workflow\FSM\StateMachineInterface;

abstract class BaseAction
{
    public function __invoke(StateMachineInterface $fsm, ContextInterface $context, ...$args): void
    {

    }
}
