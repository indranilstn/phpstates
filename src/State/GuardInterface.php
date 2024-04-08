<?php

declare(strict_types=1);

namespace Stn\Workflow\State;

use Stn\Workflow\Context\ContextInterface;

interface GuardInterface
{
    public function canTransition(ContextInterface $context, ...$args): bool;
}
