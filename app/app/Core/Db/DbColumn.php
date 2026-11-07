<?php

namespace Shiri\Core\Db;

use Attribute;

#[Attribute]
class DbColumn
{
    public function __construct(
        public string $name,
    ) {
        //
    }
}
