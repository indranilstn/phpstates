<?php

declare(strict_types=1);

namespace ID\Workflow\State;

use ID\Workflow\Context\ContextInterface;

interface GuardInterface
{
    public function canTransition(ContextInterface $context, ...$args): bool;
}
