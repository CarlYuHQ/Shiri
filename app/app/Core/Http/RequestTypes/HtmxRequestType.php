<?php

namespace Shiri\Core\Http\RequestTypes;

use Shiri\Core\Http\HtmxRequest;
use Shiri\Core\Http\IncomingRequest;

class HtmxRequestType implements RequestTypeInterface
{
    public function matches(IncomingRequest $request): bool
    {
        return $request->headers->has('HX-Request');
    }

    public function getPriority(): int
    {
        return 200;
    }

    public function getRequestClass(): string
    {
        return HtmxRequest::class;
    }
}
