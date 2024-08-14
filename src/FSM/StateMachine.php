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

    /** @var array<string, StateInterface> */
    private array $states = [];

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

            if ($state instanceof StateMachineInterface) {
                $state->setRoot($this);
            }

            $this->states[$stateName] = $state;
        }

        if ($startState) {
            if (!array_key_exists($state, $this->states)) {
                throw new \Exception("Starting state does not exist: $startState");
            }

            $this->initialState = $startState;
        } else {
            $firstState = array_key_first($this->states);
            if ($firstState && !($firstState instanceof StateMachineInterface)) {
                $this->initialState = $firstState;
            }
        }

        $this->root = $this;
    }

    public function getRootZero(): StateMachineInterface
    {
        static $root = ($this->root == $this) ? $this : $this->root->getRoot();
        return $root;
    }

    public function setRoot(StateMachineInterface $root): void
    {
        $this->root = $root;
    }

    public function getRoot(): StateMachineInterface
    {
        return $this->root;
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
            $this->register($fsm->getName(), $fsm->receiveSignal(...));
            $result = $this->start(...$args) ? $this->currentStateName : null;

            if (!$result) {
                return null;
            }
        }

        if ($eventData?->target) {
            $result = $this->transition($eventData, ...$args);
        }

        return $result;
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
     * @throws Exception on error
     */
    private function getStateByName(string $name): array
    {
        $stateName = $name;
        $target = null;

        $stateParts = explode('/', $name);
        if (count($stateParts) > 1) {
            if ($stateParts[0]) {
                if ($stateParts[0] == $this->name) {
                    $stateName = $stateParts[1];
                    $target = ltrim($name, "{$this->name}/");
                } else {
                    $stateName = $stateParts[0];
                    $target = ltrim($name, "{$stateName}/");
                }
            } else {
                $rootTarget = ltrim($name, '/');

                $result = ($this->root == $this)
                    ? $this->getStateByName($rootTarget)
                    : [
                        'state' => $this->getRootZero(),
                        'target' => $rootTarget,
                    ];

                return $result;
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
     * @return ContextInterface|null
     * @throws \Exception
     */
    public function getContext(): ?ContextInterface
    {
        if ($this->context) {
            if ($this->context instanceof \Closure) {
                $contextObject = ($this->context)();
                if (!($contextObject instanceof ContextInterface)) {
                    throw new \Exception('Invalid context');
                }

                $this->context = $contextObject;
            }

            return $this->context;
        }

        return $this->root == $this ? null : $this->root->getContext();
    }

    public function setContext(ContextInterface $context): void
    {
        $this->context = $context;
    }

    public function register(string $id, \Closure $callable, mixed $payload = null): void
    {
        $this->consumers[$id] = [$callable, $payload];
    }

    public function unregister(string $id): void
    {
        unset($this->consumers[$id]);
    }

    private function cleanup($state): void
    {
        if ($state->isFinal()) {
            $this->isTerminated = true;

            if ($state instanceof StateMachineInterface) {
                $state->unregister($this->name);
            }
        }
    }

    public function start(...$args): bool
    {
        if (!$this->initialState) {
            if ($this == $this->getRootZero()) {
                throw new \Exception('Initial state is mandatory for root state machine');
            }

            $this->isStarted = true;
            return true;
        }

        $this->isStarted = true;
        $result = $this->transition(new EventData(null, $this->initialState), ...$args);

        if (!$result) {
            $this->isStarted = false;
        }

        return $this->isStarted;
    }

    private function transition(EventData $eventData, ...$args): ?string
    {
        [
            'state' => $targetState,
            'target' => $nextTarget,
        ] = $this->getStateByName($eventData->target);

        $result = $targetState->enter(
            new EventData($eventData->event, $nextTarget),
            $this,
            ...$args
        );

        if ($result) {
            [
                'state' => $stateName,
                'target' => $newtarget
            ] = is_array($result) ? $result : ['state' => $result, 'target' => null];

            if ($stateName != $this->currentStateName) {
                $this->currentStateName = "{$this->name}/$stateName";

                if ($this->current) {
                    $this->current->leave($this, ...$args);
                }
                $this->current = $targetState;
            }

            $this->signal();

            $retval = $this->currentStateName;
            if ($newtarget) {
                $targetState->leave($this, ...$args);
                $retval = $this->transition(new EventData(null, $newtarget), ...$args);
            }

            $this->cleanup($targetState);

            return $retval;
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
