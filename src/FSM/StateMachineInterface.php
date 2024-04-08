<?php

declare(strict_types=1);

namespace Stn\Workflow\FSM;

use Stn\Workflow\Context\ContextInterface;

interface StateMachineInterface
{
    public function start(...$args): bool;
    public function trigger(string $event, ...$args): bool;
    public function getState(): string;
    public function getContext(): ContextInterface;
    public function hasTerminated(): bool;
    public function register(string $id, \Closure $callable, mixed $payload = null): void;
    public function unregister(string $id): void;
}
