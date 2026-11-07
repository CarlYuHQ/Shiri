<?php

namespace Shiri\Domain\Notifications\Hxcontrollers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Shiri\Core\Controller\HtmxController;

class NewsBadge extends HtmxController
{
    protected static string $view = 'notifications::partials.newsBadge';

    private \Shiri\Domain\Notifications\Services\News $newsService;

    /**
     * Controller constructor
     */
    public function init(\Shiri\Domain\Notifications\Services\News $newsService): void
    {
        $this->newsService = $newsService;

    }

    /**
     * @return void
     *
     * @throws BindingResolutionException
     */
    public function get()
    {
        if (! env('LEAN_NEWS_ENABLED', true)) {
            $this->tpl->assign('hasNews', false);

            return;
        }

        try {
            $hasNews = $this->newsService->hasNews(session('userdata.id'));
        } catch (\Exception $e) {
            report($e);
            $hasNews = false;
        }

        $this->tpl->assign('hasNews', $hasNews);
    }
}
