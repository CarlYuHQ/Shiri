<?php

use Shiri\Core\Events\EventDispatcher;

// Register event listener
EventDispatcher::add_filter_listener(
    'shiri.domain.connector.services.providers.loadProviders.providerList',
    function (mixed $payload) {

        $provider = app()->make(\Shiri\Domain\CsvImport\Services\CsvImport::class);
        $payload[$provider->id] = $provider;

        return $payload;
    }
);
