<?php

declare(strict_types=1);

namespace Stn\Tests;

use Stn\Workflow\Action\BaseAction;
use Stn\Workflow\Context\ContextInterface;
use Stn\Workflow\FSM\StateMachineInterface;

class Action extends BaseAction
{
    public function __invoke(StateMachineInterface $fsm, ContextInterface $context, ...$args): void
    {
        echo __CLASS__;
    }
}
