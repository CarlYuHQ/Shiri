<?php

namespace Shiri\Domain\Connector\Repositories;

use Shiri\Core\Db\Repository;
use Shiri\Domain\Connector\Models\Integration;

class Integrations extends Repository
{
    public function __construct()
    {
        $this->entity = 'integration';
        $this->model = Integration::class;
    }
}
