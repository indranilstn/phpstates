<?php

declare(strict_types=1);

namespace Stn\Workflow\FSM;

use Stn\Workflow\State\StateInterface;
use Stn\Workflow\Context\ContextInterface;
use Stn\Workflow\State\FinalState;

class StateMachine implements StateMachineInterface
{
    private ?string $initialState = null;
    private ?string $currentState = null;
    private bool $isTerminated = false;

    /** @var array<string, StateInterface> $states */
    private array $states = [];

    /**
     * Throws \Exception on duplicate state name or non-existant starting state
     */
    public function __construct(
        private string $name,
        private ContextInterface $context,
        /** @var array<int, StateInterface> $states */
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

    public function getState(): string
    {
        return $this->currentState;
    }

    public function getContext(): ContextInterface
    {
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
        $result = $this->states[$this->initialState]->enter(null, $this, ...$args);
        if ($result) {
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
        if ($this->isTerminated) {
            return false;
        }

        $state = $this->states[$this->currentState];
        $target = $state->getTarget($event);

        if ($target && array_key_exists($target, $this->states)) {
            $targetState = $this->states[$target];
            if ($targetState->enter($event, $this, ...$args)) {
                $this->currentState = $target;
                if ($targetState instanceof FinalState) {
                    $this->isTerminated = true;
                }
                $state->leave($this, ...$args);

                foreach ($this->consumers as &$consumer) {
                    [$callback, $payload] = $consumer;
                    $callback($this->currentState, $payload);
                }

                return true;
            }
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
