<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

use Stn\Workflow\Action\BaseAction;
use Stn\Workflow\FSM\StateMachineInterface;

class FinalState extends BaseState
{
    public function __construct(
        string $name,
        ?BaseAction $entry = null,
    ) {
        parent::__construct($name, $entry);
    }

    public function getTarget(string $event): ?string
    {
        return null;
    }

    public function canTransition(StateMachineInterface $fsm, ...$args): bool
    {
        return true;
    }
}
