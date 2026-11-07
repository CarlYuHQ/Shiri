<?php

namespace Shiri\Views\Composers;

use Shiri\Core\Configuration\AppSettings;
use Shiri\Core\UI\Composer;

class Footer extends Composer
{
    public static array $views = [
        'global::sections.footer',
    ];

    protected AppSettings $settings;

    public function init(AppSettings $settings): void
    {
        $this->settings = $settings;
    }

    public function with(): array
    {
        return [
            'version' => $this->settings->appVersion,
        ];
    }
}
