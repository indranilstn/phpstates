<?php

declare(strict_types=1);

namespace Tests;

use ID\Workflow\Action\BaseAction;
use ID\Workflow\Context\ContextInterface;
use ID\Workflow\FSM\StateMachineInterface;

class Action extends BaseAction
{
    public function __invoke(StateMachineInterface $fsm, ContextInterface $context, ...$args): void
    {
        echo __CLASS__;
    }
}
