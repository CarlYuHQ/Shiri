<?php

namespace Shiri\Domain\Calendar\Controllers;

use Shiri\Core\Auth\Permissions\RequiresPermission;
use Shiri\Core\Controller\Controller;
use Shiri\Domain\Calendar\Permissions\CalendarPermissions;
use Shiri\Domain\Calendar\Support\CardTheme;
use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Support\Prefs;
use Symfony\Component\HttpFoundation\Response;

/**
 * 设置 /calendar/showSettings
 *
 * 四件事：界面语言、桌面卡片配色（跟随时间或固定一档）、卡片不透明度、一周从周几开始。
 * 改动即时保存（走 JSON-RPC Calendar.Settings.save），换语言后整页刷新一次。
 */
class ShowSettings extends Controller
{
    private Prefs $prefs;

    public function init(Prefs $prefs): void
    {
        $this->prefs = $prefs;
    }

    #[RequiresPermission(CalendarPermissions::VIEW)]
    public function get(array $params): Response
    {
        // 先把语言偏好同步给 Shiri 本体，模板里的 __() 才会跟着切
        Lang::applyToShiri();
        $userId = (int) session('userdata.id');
        $prefs = $this->prefs->all($userId);

        $this->tpl->assign('prefs', $prefs);
        $this->tpl->assign('languages', Lang::all());
        $this->tpl->assign('themes', CardTheme::all());
        $this->tpl->assign('cardAlphaPct', (int) round(((float) $prefs[Prefs::CARD_ALPHA]) * 100));
        $this->tpl->assign('currentLang', Lang::current());

        return $this->tpl->display('calendar.showSettings');
    }
}
