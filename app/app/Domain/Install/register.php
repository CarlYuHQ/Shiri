<?php

use Shiri\Core\Controller\Frontcontroller;
use Shiri\Core\Events\EventDispatcher;

EventDispatcher::add_filter_listener('shiri.*.welcomeText', function ($welcomeText) {

    $language = app()->make(\Shiri\Core\Language::class);

    if (Frontcontroller::getCurrentRoute() == 'install') {
        $welcomeText = '<h1 class="mainWelcome">'.$language->__('headlines.welcome').'</h1>';
        $subText = '';
        $welcomeText = $welcomeText.$subText;
    }

    if (Frontcontroller::getCurrentRoute() == 'install.update') {
        $welcomeText = '<h1 class="mainWelcome">'.$language->__('headlines.welcome').'</h1>';
        $subText = '';
        $welcomeText = $welcomeText.$subText;
    }

    return $welcomeText;
});
