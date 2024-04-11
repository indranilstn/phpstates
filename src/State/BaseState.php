<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

use Stn\Workflow\State\EventData;
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
        protected ?string $target = null,
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

    public function enter(?EventData $eventData, StateMachineInterface $fsm, ...$args): string|array|null
    {
        $context = $fsm->getContext();
        if ($this->canTransition($context, ...$args)) {
            if ($this->entry) {
                $this->entry($context, ...$args);
            }

            return $this->target
                ? [
                    'state' => $this->name,
                    'target' => $this->target
                ] : $this->name;
        }

        return null;
    }

    public function leave(StateMachineInterface $fsm, ...$args): void
    {
        if ($this->exit) {
            $this->exit($fsm->getContext(), ...$args);
        }
    }

    public function isFinal(): bool
    {
        return !$this->target && count($this->events) == 0;
    }
}
