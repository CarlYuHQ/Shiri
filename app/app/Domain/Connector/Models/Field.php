<?php

namespace Shiri\Domain\Connector\Models;

class Field
{
    public int $id;

    public int $entityConnectionId;

    public string $shiriFields;

    public string $providerEntity;

    public string $typeConnector;

    public function __construct() {}
}
