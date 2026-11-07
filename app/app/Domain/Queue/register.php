<?php

namespace Shiri\Domain\Queue;

use Illuminate\Console\Scheduling\Schedule;
use Shiri\Core\Events\EventDispatcher;
use Shiri\Domain\Queue\Workers\Workers;

EventDispatcher::add_event_listener('shiri.core.console.consolekernel.schedule.cron', function ($params) {

    if (get_class($scheduler = $params['schedule']) !== Schedule::class) {
        return;
    }

    $scheduler
        ->call(fn () => app()->make(Services\Queue::class)->processQueue(Workers::EMAILS))
        ->name('queue:emails')
        ->everyMinute();

    $scheduler
        ->call(fn () => app()->make(Services\Queue::class)->processQueue(Workers::HTTPREQUESTS))
        ->name('queue:httprequests')
        ->everyFiveMinutes();

    $scheduler
        ->call(fn () => app()->make(Services\Queue::class)->processQueue(Workers::DEFAULT))
        ->name('queue:default')
        ->everyFiveMinutes();
});
