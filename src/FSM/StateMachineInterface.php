<?php

declare(strict_types=1);

namespace Stn\Workflow\FSM;

use Stn\Workflow\Context\ContextInterface;

interface StateMachineInterface
{
    public function start(...$args): bool;
    public function trigger(string $event, ...$args): bool;
    public function getName(): string;
    public function getState(): string;
    public function isFinal(): bool;
    public function getContext(): ?ContextInterface;
    public function setContext(ContextInterface $context): void;
    public function register(string $id, \Closure $callable, mixed $payload = null): void;
    public function unregister(string $id): void;
}
