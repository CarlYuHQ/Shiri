<?php

use Shiri\Core\Events\EventDispatcher;

EventDispatcher::addEventListener('shiri.domain.auth.*.userSignUpSuccess', function ($params) {

    $userId = session('userdata.id');
    $userRole = session('userdata.role');

    $helperService = app()->make(\Shiri\Domain\Help\Services\Helper::class);
    $helperService->createDefaultProject($userId, $userRole);

});
