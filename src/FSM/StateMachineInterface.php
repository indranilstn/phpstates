<?php

declare(strict_types=1);

namespace ID\Workflow\FSM;

interface StateMachineInterface
{
    public function start(...$args): bool;
    public function trigger(string $event, ...$args): bool;
    public function getState(): string;
    public function hasTerminated(): bool;
    public function register(string $id, \Closure $callable, mixed $payload = null): void;
    public function unregister(string $id): void;
}
