<?php

declare(strict_types=1);

namespace ID\Workflow\State;

use ID\Workflow\Context\ContextInterface;
use ID\Workflow\FSM\StateMachineInterface;

interface StateInterface
{
    public function getName(): string;
    public function publishEvents(): array;
    public function getTarget(string $event): ?string;
    public function enter(string $event, StateMachineInterface $fsm, ContextInterface $context, ...$args): bool;
    public function leave(StateMachineInterface $fsm, ContextInterface $context, ...$args): void;
}
