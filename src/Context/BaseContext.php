<?php

declare(strict_types=1);

namespace ID\Workflow\Context;

abstract class BaseContext implements ContextInterface
{
    private array $properties = [];

    public function __construct()
    {
        $reflector = new \ReflectionClass(static::class);
        $properties = $reflector->getProperties();
        foreach ($properties as $property) {
            $this->properties[$property->name] = $property->class;
        }
    }

    /**
     * Set a single property
     *
     * @param string $name property name
     * @param mixed $data property value to set
     * @return void
     * @throws \Exception
     */
    final public function set(string $name, mixed $data): void
    {
        if (array_key_exists($name, $this->properties)) {
            // audit trail current value
            $this->{$name} = $data;
        } else {
            throw new \Exception('Trying to set invalid property');
        }
    }

    final public function setMultiple(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }
}
