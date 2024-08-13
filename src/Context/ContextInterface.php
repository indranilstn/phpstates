<?php

declare(strict_types=1);

namespace Stn\Workflow\Context;

interface ContextInterface
{
    public function get(string $name): mixed;
    public function set(string $name, mixed $data): void;
    public function setMultiple(array $data): void;
}