<?php

use Stn\Tests\{Action, Context, State};
use Stn\Workflow\FSM\StateMachine;
use Stn\Workflow\State\FinalState;

include_once __DIR__ . '/vendor/autoload.php';

$fsm = new StateMachine(
    name: 'test-machine',
    context: fn() => new Context(),
    states: [
        new State(
            name: 'initial',
            events: [
                'book' => 'booked',
                'show' => 'shown',
            ],
        ),
        fn() => new State(
            name: 'booked',
            entry: new Action(),
            events: [
                'show' => 'shown',
                'apply' => 'nested/applied',
                'lease' => 'leased',
            ],
        ),
        new State(
            name: 'shown',
            events: [
                'apply' => 'nested/applied',
                'lease' => 'leased',
            ],
        ),
        new StateMachine(
            name: 'nested',
            context: new Context(),
            states: [
                new StateMachine(
                    name: 'second-nested',
                    context: new Context(),
                    states: [
                        new State(
                            name: 'nested-state',
                            events: [
                                'test' => '/nested/applied',
                            ],
                        ),
                    ],
                ),
                new State(
                    name: 'applied',
                    events: [
                        'lease' => '/leased',
                    ],
                ),
            ],
        ),
        new FinalState(
            name: 'leased'
        )
    ],
);

$fsm->register('test-consumer', function (string $state) {
    echo "Current state: $state\n";
});
$fsm->start();
$fsm->trigger('show');
$fsm->trigger('book');
$fsm->trigger('apply');
$fsm->trigger('lease');
$fsm->trigger('initial');

