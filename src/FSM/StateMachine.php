<?php

declare(strict_types=1);

namespace Stn\Workflow\FSM;

use Stn\Workflow\State\EventData;
use Stn\Workflow\State\StateInterface;
use Stn\Workflow\Context\ContextInterface;

class StateMachine implements StateMachineInterface, StateInterface
{
    private ?string $initialState = null;
    private ?string $currentStateName = null;

    private ?StateMachineInterface $root = null;
    private ?StateInterface $current = null;

    private bool $isStarted = false;
    private bool $isTerminated = false;

    /** @var array<string, StateInterface> $states */
    private array $states = [];

    /** @var array<string, StateInterface> $events */
    private array $events = [];

    /**
     * Throws \Exception on duplicate state name or non-existant starting state
     */
    public function __construct(
        private string $name,
        private ContextInterface|\Closure|null $context = null,
        /** @var array<int, StateInterface|\Closure> $states */
        array $states,
        ?string $startState = null,
        /** @var array<string, \Closure> $consumers */
        private array $consumers = [],
    ) {
        foreach ($states as $state) {
            if ($state instanceof \Closure) {
                $state = $state();

                if (!($state instanceof StateInterface)) {
                    throw new \Exception("Invalid closure provided for state in machine {$this->name}");
                }
            }

            $stateName = $state->getName();
            if (array_key_exists($stateName, $this->states)) {
                throw new \Exception("Duplicate state name: $stateName");
            }

            $this->states[$stateName] = $state;
        }

        if ($startState && !array_key_exists($startState, $this->states)) {
            throw new \Exception("Starting state name mismatch: $startState");
        }

        $this->initialState = $startState ?? array_key_first($this->states);
        $this->root = $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTarget(string $event): ?string
    {
        $result = null;

        if ($this->isStarted && $this->current) {
            $result = $this->current->getTarget($event);
        }

        return $result;
    }

    public function enter(?EventData $eventData, StateMachineInterface $fsm, ...$args): ?string
    {
        $result = null;

        if (!$this->isStarted) {
            $this->root = $fsm->getRoot();
            $this->register($fsm->getName(), $fsm->receiveSignal(...));
            $result = $this->start(...$args) ? $this->currentStateName : null;

            if (!$result) {
                return null;
            }
        }

        return $eventData ? $this->transition($eventData, ...$args) : $result;
    }

    public function leave(StateMachineInterface $fsm, ...$args): void
    {
        if ($this->current) {
            $this->current->leave($this, ...$args);
        }
    }

    public function isFinal(): bool
    {
        return $this->isTerminated;
    }

    /**
     * Find target state object based on state path
     *   e.g., 'booked', 'some-nested/state'
     *
     * @param string $name state name or path
     * @return array{state: StateInterface, target: string}
     * @throws \Exception on error
     */
    private function getStateByName(string $name): array
    {
        $stateName = $name;
        $target = $name;

        $stateParts = explode('/', $name);
        if (count($stateParts) > 1) {
            if ($stateParts[0]) {
                if ($stateParts[0] == $this->name) {
                    $stateName = $stateParts[1];
                    $target = ltrim(ltrim($name, "{$this->name}"), '/');
                } else {
                    $stateName = $stateParts[0];
                }
            } else {
                $rootTarget = ltrim($name, '/');

                return ($this->root == $this)
                    ? $this->getStateByName($rootTarget)
                    : [
                        'state' => $this->root,
                        'target' => $rootTarget,
                    ];
            }
        }

        if (!array_key_exists($stateName, $this->states)) {
            throw new \Exception("Invalid state for $name");
        }

        $state = $this->states[$stateName];
        return [
            'state' => $state,
            'target' => $target,
        ];
    }

    public function getRoot(): StateMachineInterface
    {
        return $this->root;
    }

    public function signal(?string $state = null, mixed $signalLoad = null): void
    {
        foreach ($this->consumers as &$consumer) {
            [$callback, $payload] = $consumer;
            $callback(
                $state ?? $this->currentStateName,
                $signalLoad ?? $payload
            );
        }
    }

    protected function receiveSignal(string $state, mixed $payload = null): void
    {
        $this->currentStateName = "{$this->name}/$state";
    }

    public function getState(): string
    {
        return $this->currentStateName;
    }

    /**
     * Get the context
     *
     * @return ContextInterface
     * @throws \Exception
     */
    public function getContext(): ContextInterface
    {
        if ($this->context instanceof \Closure) {
            $contextObject = ($this->context)();
            if (!($contextObject instanceof ContextInterface)) {
                throw new \Exception('Invalid context');
            }

            $this->context = $contextObject;
        }

        return $this->context;
    }

    public function register(string $id, \Closure $callable, mixed $payload = null): void
    {
        $this->consumers[$id] = [$callable, $payload];
    }

    public function unregister(string $id): void
    {
        unset($this->consumers[$id]);
    }

    public function start(...$args): bool
    {
        ['state' => $state] = $this->getStateByName($this->initialState);
        $result = $state->enter(null, $this, ...$args);
        if ($result) {
            $this->isStarted = true;
            $this->currentStateName = "{$this->name}/$result";
            $this->current = $state;

            $this->signal();

            return true;
        }

        return false;
    }

    private function transition(EventData $eventData, ...$args): ?string
    {
        [
            'state' => $targetState,
            'target' => $nextTarget,
        ] = $this->getStateByName($eventData->target);

        $stateName = $targetState->enter(
            new EventData($eventData->event, $nextTarget),
            $this,
            ...$args
        );

        if ($stateName) {
            if ($stateName != $this->currentStateName) {
                $this->currentStateName = "{$this->name}/$stateName";

                if ($this->current) {
                    $this->current->leave($this, ...$args);
                }
                $this->current = $targetState;
            }

            $this->signal();

            if ($targetState->isFinal()) {
                $this->isTerminated = true;

                if ($targetState instanceof StateMachineInterface) {
                    $targetState->unregister($this->getName());
                }
            }

            return $this->currentStateName;
        }

        return null;
    }

    public function trigger(string $event, ...$args): bool
    {
        if (!($this->isStarted && $this->current) || $this->isTerminated) {
            return false;
        }

        $target = $this->current->getTarget($event);
        if (!$target) {
            return false;
        }

        $result = $this->transition(new EventData($event, $target), ...$args);

        return (bool) $result;
    }

    public function persist(\Closure $handler): mixed
    {
        return $handler($this->name, \serialize($this));
    }

    public static function hydrate(string $data): static
    {
        return \unserialize($data);
    }
}
