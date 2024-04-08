<?php

declare(strict_types=1);

namespace Stn\Workflow\FSM;

use Stn\Workflow\State\StateInterface;
use Stn\Workflow\Context\ContextInterface;

class StateMachine implements StateMachineInterface, StateInterface
{
    private ?string $initialState = null;
    private ?string $currentState = null;

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
        private ContextInterface|\Closure $context,
        /** @var array<int, StateInterface|\Closure> $states */
        array $states,
        ?string $startState = null,
        /** @var array<string, \Closure> $consumers */
        private array $consumers = [],
    ) {
        foreach ($states as &$state) {
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
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTarget(string $event): ?string
    {
        $result = null;

        if (isset($this->events[$event])) {
            $result = $this->name;
        } else {
            foreach ($this->states as $name => $_) {
                $state = $this->getStateByName($name);
                $target = $state->getTarget($event);
                if ($target) {
                    $this->events[$event] = $state;
                    $result = $this->name;
                }
            }
        }

        return $result;
    }

    public function enter(?string $event, StateMachineInterface $fsm, ...$args): bool
    {
        $result = false;

        if (!$this->isStarted) {
            $result = $this->start(...$args);
        }

        if ($result && $event) {
            $result = $this->trigger($event, ...$args);
        }

        return $result;
    }

    public function leave(StateMachineInterface $fsm, ...$args): void
    {
        $state = $this->getStateByName($this->currentState);
        if ($state) {
            $state->leave($this, ...$args);
        }
    }

    public function isFinal(): bool
    {
        return $this->isTerminated;
    }

    /**
     * Find target state object
     *
     * @param string $name state name
     * @return StateInterface
     * @throws \Exception on error
     */
    private function getStateByName(string $name): StateInterface
    {
        if (!($name && array_key_exists($name, $this->states))) {
            throw new \Exception("Invalid state for $name");
        }

        $state = $this->states[$name];

        if ($state instanceof \Closure) {
            $stateObject = $state();

            if (!($stateObject instanceof StateInterface)) {
                throw new \Exception("Invalid state object for $name");
            }

            $state = $stateObject;
            $this->states[$name] = $stateObject;
        }

        return $state;
    }

    public function getState(): string
    {
        return $this->currentState;
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
        $state = $this->getStateByName($this->initialState);
        $result = $state->enter(null, $this, ...$args);
        if ($result) {
            $this->isStarted = true;
            $this->currentState = $this->initialState;

            foreach ($this->consumers as &$consumer) {
                [$callback, $payload] = $consumer;
                $callback($this->currentState, $payload);
            }
        }

        return $result;
    }

    public function trigger(string $event, ...$args): bool
    {
        if (!$this->isStarted || $this->isTerminated) {
            return false;
        }

        $state = $this->getStateByName($this->currentState);
        $target = $state->getTarget($event);

        if (!$target) {
            return false;
        }

        $targetState = $this->getStateByName($target);

        if ($targetState->enter($event, $this, ...$args)) {
            if ($targetState->isFinal()) {
                $this->isTerminated = true;
            }

            if ($target != $this->currentState) {
                $this->currentState = $target;
                $state->leave($this, ...$args);
            }

            foreach ($this->consumers as &$consumer) {
                [$callback, $payload] = $consumer;
                $callback($this->currentState, $payload);
            }

            return true;
        }

        return false;
    }

    public function hasTerminated(): bool
    {
        return $this->isTerminated;
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
