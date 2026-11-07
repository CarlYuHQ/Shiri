<?php

namespace Shiri\Domain\Api\Models;

use Shiri\Domain\Api\Contracts\StaticAssetType;

/**
 * Represents a static asset file.
 */
class StaticAsset
{
    public function __construct(
        public string $key,
        public string $absPath,
        public StaticAssetType $fileType,
    ) {}
}
