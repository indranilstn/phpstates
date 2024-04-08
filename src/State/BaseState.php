<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

use Stn\Workflow\Context\ContextInterface;
use Stn\Workflow\FSM\StateMachineInterface;
use Stn\Workflow\Action\BaseAction;

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

    public function enter(?string $event, StateMachineInterface $fsm, ...$args): bool
    {
        $context = $fsm->getContext();
        if ($this->canTransition($context, ...$args)) {
            if ($this->entry) {
                $this->entry($context, ...$args);
            }

            return true;
        }

        return false;
    }

    public function leave(StateMachineInterface $fsm, ...$args): void
    {
        if ($this->exit) {
            $this->exit($fsm->getContext(), ...$args);
        }
    }

    public function isFinal(): bool
    {
        return count($this->events) == 0;
    }
}
