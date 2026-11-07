<?php

namespace Shiri\Domain\Calendar\Services;

use Shiri\Core\Language;
use Shiri\Domain\Calendar\Support\Lang;
use Shiri\Domain\Calendar\Support\Prefs;

/**
 * 设置的写入口（页面与桌面卡片的滑杆都走这里）
 *
 * 路径：/api/jsonrpc → shiri.rpc.Calendar.Settings.save
 */
class Settings
{
    public function __construct(private Prefs $prefs) {}

    /** 我们的语言代码 => Shiri 自己的语言代码（内置文案 __() 用后者） */
    private const SHIRI_LOCALES = [Lang::ZH => 'zh-CN', Lang::EN => 'en-US'];

    /**
     * 保存一项或多项偏好
     *
     * @param  array<string, mixed>  $params  lang / theme / cardAlpha / weekStart，给几项存几项
     * @return array<string, mixed>
     *
     * @api
     */
    public function save(array $params): array
    {
        $userId = (int) session('userdata.id');
        if ($userId <= 0) {
            return ['ok' => false, 'message' => '未登录'];
        }

        $saved = [];
        foreach ([Prefs::LANG, Prefs::THEME, Prefs::CARD_ALPHA, Prefs::WEEK_START] as $key) {
            if (! array_key_exists($key, $params)) {
                continue;
            }

            $this->prefs->set($userId, $key, (string) $params[$key]);
            $saved[] = $key;
        }

        // 语言要同时告诉 Shiri 本体：日历页、弹窗那些内置文案走的是它自己的语言包，
        // 只存我们自己的字典会出现「导航变了、日历没变」的半截效果。
        if (in_array(Prefs::LANG, $saved, true)) {
            $locale = self::SHIRI_LOCALES[$this->prefs->get($userId, Prefs::LANG, Lang::ZH)] ?? null;
            if ($locale !== null) {
                try {
                    app(Language::class)->setLanguage($locale);
                } catch (\Throwable) {
                    // 语言包缺失不影响我们自己的文案
                }
                Lang::setLocale(null);   // 清掉本请求的缓存，后续调用重新解析
            }
        }

        return ['ok' => true, 'saved' => $saved, 'prefs' => $this->prefs->all($userId)];
    }

    /**
     * 读当前偏好（前端要初始化控件时用）
     *
     * @return array<string, string>
     *
     * @api
     */
    public function get(): array
    {
        $userId = (int) session('userdata.id');

        return $this->prefs->all($userId);
    }
}
