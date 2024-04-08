<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

use Stn\Workflow\Context\ContextInterface;
use Stn\Workflow\FSM\StateMachineInterface;

interface StateInterface
{
    public function getName(): string;
    public function publishEvents(): array;
    public function getTarget(string $event): ?string;
    public function enter(?string $event, StateMachineInterface $fsm, ...$args): bool;
    public function leave(StateMachineInterface $fsm, ...$args): void;
}
