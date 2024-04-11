<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

use Stn\Workflow\Context\ContextInterface;
use Stn\Workflow\FSM\StateMachineInterface;

interface StateInterface
{
    public function getName(): string;
    public function getTarget(string $event): ?string;
    public function enter(?EventData $eventData, StateMachineInterface $fsm, ...$args): string|array|null;
    public function leave(StateMachineInterface $fsm, ...$args): void;
    public function isFinal(): bool;
}
