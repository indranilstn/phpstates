<?php

declare(strict_types=1);

namespace ID\Workflow\State;

use ID\Workflow\Context\ContextInterface;
use ID\Workflow\FSM\StateMachineInterface;
use ID\Workflow\Action\BaseAction;

abstract class BaseState implements StateInterface, GuardInterface
{
    public function __construct(
        protected string $name,
        protected ?BaseAction $entry = null,
        protected ?BaseAction $exit = null,
        /** @var array<string, string> $events */
        protected array $events = [],
    ) {

    }

    public function getName(): string
    {
        return $this->name;
    }

    public function publishEvents(): array
    {
        return $this->events;
    }

    public function getTarget(string $event): ?string
    {
        return array_key_exists($event, $this->events) ? $this->events[$event] : null;
    }

    public function enter(string $event, StateMachineInterface $fsm, ContextInterface $context, ...$args): bool
    {
        if ($this->canTransition($context, ...$args)) {
            if ($this->entry) {
                $this->entry($context, ...$args);
            }

            return true;
        }

        return false;
    }

    public function leave(StateMachineInterface $fsm, ContextInterface $context, ...$args): void
    {
        if ($this->exit) {
            $this->exit($context, ...$args);
        }
    }
}
