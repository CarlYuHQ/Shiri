<?php

namespace Shiri\Domain\Dashboard\Repositories;

use Shiri\Core\Db\Db as DbCore;

class Dashboard
{
    public ?DbCore $db;

    /**
     * __construct - neu db connection
     */
    public function __construct(DbCore $db)
    {
        $this->db = $db;
    }
}
