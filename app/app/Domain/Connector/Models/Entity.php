<?php

namespace Shiri\Domain\Connector\Models;

class Entity
{
    public int $id;

    public string $name;

    public string $authData;

    public string $notes;

    // Shiri domain object
    public mixed $shiriEntity;

    // Array of field objects
    public array $fieldMappings = [];

    // External domain object
    public mixed $providerEntity;

    public function __construct() {}
}
