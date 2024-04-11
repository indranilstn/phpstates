<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

class EventData
{
    public function __construct(
        public ?string $event,
        public string $target,
    ) {

    }
}
