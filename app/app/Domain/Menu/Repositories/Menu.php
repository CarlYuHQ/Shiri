<?php

/**
 * menu class - Menu definitions
 */

namespace Shiri\Domain\Menu\Repositories;

use Shiri\Core\Configuration\Environment as EnvironmentCore;
use Shiri\Core\Events\DispatchesEvents;
use Shiri\Core\Language as LanguageCore;
use Shiri\Core\Modules\ProjectModules;
use Shiri\Core\UI\UiState;
use Shiri\Domain\Auth\Services\Auth as AuthService;
use Shiri\Domain\Setting\Repositories\Setting as SettingRepository;
use Shiri\Domain\Tickets\Services\Tickets as TicketService;

class Menu
{
    use DispatchesEvents;

    // Default menu
    public const DEFAULT_MENU = 'default';

    // Menus that aren't about the current project, so project module switches don't apply to them
    private const NON_PROJECT_MENUS = ['personal', 'company', 'projecthub'];

    // 本机极简改造：只保留「日历」一项。
    // 原来的 仪表盘/待办/里程碑/目标/想法/蓝图/数据室/工时表/公司管理 全部去掉，
    // 侧栏本来也不再显示（见 minimal.css），这里同步清空避免残留死链。
    public array $menuStructures = [
        'default' => [
            10 => ['type' => 'item', 'module' => 'calendar', 'title' => 'menu.sidemenu_my_calendar', 'icon' => 'fa fa-calendar', 'tooltip' => 'menu.my_calendar_tooltip', 'href' => '/calendar/showMyCalendar', 'active' => ['showMyCalendar']],
        ],
        'personal' => [
            10 => ['type' => 'item', 'module' => 'calendar', 'title' => 'menu.sidemenu_my_calendar', 'icon' => 'fa fa-calendar', 'tooltip' => 'menu.my_calendar_tooltip', 'href' => '/calendar/showMyCalendar', 'active' => ['showMyCalendar']],
        ],
        'projecthub' => [],
        'company' => [],
    ];

    public function __construct(
        /** @var SettingRepository */
        private SettingRepository $settingsRepo,
        /** @var LanguageCore */
        private LanguageCore $language,
        /** @var EnvironmentCore */
        private EnvironmentCore $config,
        /** @var TicketService */
        private TicketService $ticketsService,
        private ProjectModules $projectModules,
    ) {}

    /**
     * setSubmenuState - Save a UI state of the current user (submenu / accordion open or closed,
     * a sidebar width, a calendar view …).
     *
     * Stored per state in the database (UiState), not in the session: parallel requests can't
     * overwrite each other, and the next page load shows it.
     *
     * @param  string  $submenu  State name, e.g. "accordion_content-tickets-organization"
     * @param  string  $state  New value ("open", "closed", "320" …)
     */
    public function setSubmenuState(string $submenu, string $state): void
    {
        app(UiState::class)->set($submenu, $state);
    }

    /**
     * getSubmenuState - A saved UI state of the current user, or false when never saved.
     *
     * @param  string  $submenu  State name
     */
    public function getSubmenuState(string $submenu): string|false
    {
        return app(UiState::class)->get($submenu) ?? false;
    }

    /**
     * Builds the menu structure recursively.
     *
     * @param  array  &$menuStructure  The menu structure to build. Passed by reference.
     * @param  string  $filter  The filter to apply to the menu structure.
     * @return array The built menu structure.
     */
    protected function buildMenuStructure(array &$menuStructure, string $filter): array
    {

        foreach ($menuStructure as &$menuItem) {

            if ($menuItem['type'] !== 'submenu') {
                continue;
            }

            $menuItem['submenu'] = $this->buildMenuStructure($menuItem['submenu'], $filter);

            $filter = $filter.'.'.$menuItem['id'];

            return self::dispatch_filter(
                hook: $filter,
                payload: $menuItem['submenu'],
                function: 'getMenuStructure'
            );

        }

        return $menuStructure;
    }

    /**
     * getMenu - Return a specific menu structure
     *
     * @param  string  $menuType  Menu type to return
     * @return array Array of menu structrue
     */
    public function getMenuStructure(string $menuType = ''): array
    {

        if (empty($menuType)) {
            $menuType = self::DEFAULT_MENU;
        }

        $this->menuStructures = self::dispatch_filter(
            'menuStructures',
            $this->menuStructures,
            ['menuType' => $menuType]
        );

        // If menu structure cannot be found, don't return anything
        if (! isset($this->menuStructures[$menuType])) {
            return [];
        }

        $language = $this->language;
        $filter = "menuStructures.$menuType";

        $this->menuStructures[$menuType] = self::dispatch_filter(
            $filter,
            $this->menuStructures[$menuType],
            ['menuType' => $menuType]
        );

        $menuStructure = $this->menuStructures[$menuType];

        ksort($menuStructure);

        foreach ($menuStructure as $key => $element) {
            if (isset($menuStructure[$key]['title'])) {
                $menuStructure[$key]['title'] = $language->__($element['title']);
            }

            switch ($element['type']) {
                case 'header':
                case 'separator':
                    break;

                case 'item':
                    if ($this->isModuleOff($element, $menuType)) {
                        unset($menuStructure[$key]);
                        break;
                    }

                    $this->processMenuItem($element, $menuStructure[$key]);
                    break;

                case 'submenu':
                    if (isset($element['submenuFunction'])) {
                        if (method_exists($this, $this->{$element['submenuFunction']})) {
                            $menuStructure[$key]['submenu'] = $this->{$element['submenuFunction']}();
                        }
                    }

                    // Update menu toggle
                    if ($element['visual'] == 'always') {
                        $submenuState = 'open';
                    } else {
                        $submenuState = app(UiState::class)->get($element['id']) ?? $element['visual'];
                    }
                    $menuStructure[$key]['visual'] = $submenuState;

                    // Parse submenu
                    foreach ($element['submenu'] as $subkey => $subelement) {
                        ksort($menuStructure[$key]['submenu']);
                        $menuStructure[$key]['submenu'][$subkey]['title'] = $language->__($menuStructure[$key]['submenu'][$subkey]['title']);

                        switch ($subelement['type']) {
                            case 'header':
                                break;

                            case 'item':
                                if ($this->isModuleOff($subelement, $menuType)) {
                                    unset($menuStructure[$key]['submenu'][$subkey]);
                                    break;
                                }

                                $this->processMenuItem($subelement, $menuStructure[$key]['submenu'][$subkey]);
                                break;

                            default:
                                exit("Cannot proceed due to invalid submenu element: '".$subelement['type']."'");
                        }
                    }

                    // A submenu whose items all belong to switched-off modules goes too
                    if (empty($menuStructure[$key]['submenu'])) {
                        unset($menuStructure[$key]);
                    }

                    break;

                default:
                    exit("Cannot proceed due to invalid menu element: '".$element['type']."'");
            }
        }

        // TODO: Add menu filter here!

        return $menuStructure;
    }

    /**
     * Whether a menu item belongs to a module that is switched off in the current project.
     *
     * @param  array<string, mixed>  $element  The menu item.
     * @param  string  $menuType  The menu being built; only project menus are filtered.
     */
    private function isModuleOff(array $element, string $menuType): bool
    {
        if (empty($element['module']) || in_array($menuType, self::NON_PROJECT_MENUS, true)) {
            return false;
        }

        return ! $this->projectModules->isEnabled((string) $element['module'], (int) session('currentProject'));
    }

    public function processMenuItem($element, &$structure): void
    {

        // Update security
        if (isset($element['role'])) {
            $accessGranted = AuthService::userIsAtLeast($element['role'], true);

            if (! $accessGranted) {
                $structure['type'] = 'disabled';
            }
        }

        if (isset($element['hrefFunction'])) {
            if (method_exists($this, $element['hrefFunction'])) {
                $structure['href'] = $this->{$element['hrefFunction']}();
            }
        }

    }

    /**
     * @return array|mixed|string|string[]
     */
    public function getTicketMenu(): mixed
    {

        $ticketService = $this->ticketsService;

        // Removing base URL from here since it is being added in the menu for loop in the template
        $base_url = ! empty($this->config->appUrl) ? $this->config->appUrl : BASE_URL;

        return str_replace($base_url, '', $ticketService->getLastTicketViewUrl());
    }

    public function getTimelineMenu(): mixed
    {

        $ticketService = $this->ticketsService;

        // Removing base URL from here since it is being added in the menu for loop in the template
        $base_url = ! empty($this->config->appUrl) ? $this->config->appUrl : BASE_URL;

        return str_replace($base_url, '', $ticketService->getLastTimelineViewUrl());
    }

    public function getIdeaMenu(): string
    {
        $url = '/ideas/showBoards';
        if (session()->exists('lastIdeaView')) {
            if (session('lastIdeaView') == 'kanban') {
                $url = '/ideas/advancedBoards';
            }
        }

        return $url;
    }

    /**
     * Request-level cache for getSectionMenuType results.
     * Prevents redundant computation when called from multiple composers (App, Menu, HeadMenu).
     * Keyed by route+default since different callers may pass different defaults.
     *
     * @var array<string, string>
     */
    private static array $sectionMenuTypeCache = [];

    public function getSectionMenuType($currentRoute, $default = 'default')
    {
        // Cache key includes both route and default since the result depends on both.
        // Different composers may pass different defaults for routes not in the sections map.
        $cacheKey = $currentRoute.'|'.$default;
        if (isset(self::$sectionMenuTypeCache[$cacheKey])) {
            return self::$sectionMenuTypeCache[$cacheKey];
        }

        $sections = [
            'dashboard.home' => 'personal',
            'projects.showMy' => 'personal',
            'timesheets.showMy' => 'personal',
            'calendar.showMyCalendar' => 'personal',
            'calendar.showMyList' => 'personal',
            'tickets.roadmapAll' => 'personal',
            'notes.showNotes' => 'personal',
            'notes.showNotesList' => 'personal',
            'tickets.showAllMilestonesOverview' => 'personal',
            'users.editOwn' => 'personal',
            'setting.editCompanySettings' => 'company',
            'timesheets.showAll' => 'company',
            'projects.showAll' => 'company',
            'clients.showAll' => 'company',
            'clients.newClient' => 'company',
            'clients.showClient' => 'company',
            'users.showAll' => 'company',
            'users.editUser' => 'company',
            'plugins.show' => 'company',
            'plugins.marketplace' => 'company',
            'plugins.myapps' => 'company',
            'connector.show' => 'company',
            'connector.integration' => 'company',
            'billing.subscriptions' => 'company',
            'llamadorian.statusCollector' => 'personal',
        ];

        $sections = self::dispatch_filter('menuSections', $sections, ['currentRoute' => $currentRoute, 'default' => $default]);

        $result = $sections[$currentRoute] ?? $default;

        self::$sectionMenuTypeCache[$cacheKey] = $result;

        return $result;
    }
}
