<?php

namespace Shiri\Core\Configuration\Attributes;

use Attribute;

#[Attribute]
class LaravelConfig
{
    public function __construct(
        public string $config,
    ) {
        //
    }
}
