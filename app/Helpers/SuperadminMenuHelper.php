<?php

namespace App\Helpers;

class SuperadminMenuHelper
{
    private static function filterMenuItems(array $items): array
    {
        $user = auth()->user();
        if (!$user) {
            return [];
        }

        $filtered = [];

        foreach ($items as $item) {
            // Agar itemda permission talab qilingan bo‘lsa va userda yo‘q bo‘lsa — butunlay o‘tkazib yuboramiz
            if (isset($item['permission']) && !$user->hasPermission($item['permission'])) {
                continue;
            }

            // SubItems bo‘lsa — ularni ham filter qilamiz
            if (isset($item['subItems']) && is_array($item['subItems'])) {
                $item['subItems'] = self::filterMenuItems($item['subItems']);

                // Agar subItems butunlay bo‘sh qolsa — uni olib tashlaymiz (parentni bo‘sh dropdown bilan ko‘rsatmaslik uchun)
                if (empty($item['subItems'])) {
                    unset($item['subItems']);
                }
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    public static function getAdminItems($department = null)
    {
        $items = [
            [
                'icon' => 'dashboard',
                'name' => "PRO",
                'subItems' => [
                    ['name' => __('messages.layout.departments'), 'path' => '/', "pro" => true],
                    ['name' => __('messages.admin.users'), 'path' => '/pro-users', "pro" => true],
                ],
            ],
            [
                'icon' => 'dashboard',
                'name' => 'FREE',
                'subItems' => [
                    ['name' => __('messages.layout.departments'), 'path' => '/free', "free" => true],
                    ['name' => __('messages.admin.users'), 'path' => '/free-users', "free" => true],
                    ['name' => __('messages.dp_banned'), 'path' => '/free-banned', "free" => true],
                ],
            ],

            [
                'icon' => 'department',
                'name' => __('messages.deleted_departments'),
                'path' => '/departments/deleted',
            ],
            [
                'icon' => 'catalog',
                'name' => __('messages.catalogs.title'),
                'path' => '/admin/catalogs',
                'permission' => 'nav:catalogs'
            ],
        ];

        return self::filterMenuItems($items);
    }

    public static function getLogItems()
    {
        $items = [
            [
                'icon' => 'user-profile',
                'name' => __('messages.admin.users'),
                'path' => '/superadmin',
                'permission' => 'nav:users'
            ],
            [
                'icon' => 'dashboard',
                'name' => __('messages.logs.title'),
                'path' => '/logs',
                'permission' => 'nav:logs',
                'subItems' => [
                    ['name' => "Pro", 'path' => '/logs/pro'],
                    ['name' => "Free", 'path' => '/logs/trial'],
                    ['name' => "Subscription", 'path' => '/logs/subscription'],
                ],
            ],
        ];

        return self::filterMenuItems($items);
    }

    public static function getOthersItems()
    {
        $items = [
            [
                'icon' => 'import',
                'name' => __('messages.import'),
                'path' => '/import',
                'permission' => 'nav:import',
            ],
            [
                'icon' => 'export',
                'name' => __('messages.export'),
                'path' => '/export',
                'permission' => 'nav:export',
            ],
            [
                'icon' => 'turkey',
                'name' => __('messages.turkey'),
                'path' => '/turkey',
                'permission' => 'nav:turkey',
            ],
        ];

        return self::filterMenuItems($items);
    }

    /**
     * Endi agar biror guruhda item qolmasa (barcha itemlar permission tufayli o‘chirilgan bo‘lsa)
     * — o‘sha guruhning sarlavhasi (Menyu, Boshqaruv, Boshqalar) ham umuman ko‘rinmaydi.
     */
    public static function getMenuGroups()
    {
        $allGroups = [
            [
                'title' => __('messages.menu'),
                'items' => self::getAdminItems()
            ],
            [
                'title' => __('messages.control'),
                'items' => self::getLogItems()
            ],
            [
                'title' => __('messages.others'),
                'items' => self::getOthersItems()
            ]
        ];

        // Faqat itemlari bo‘lgan guruhlarni qoldiramiz
        return array_values(array_filter($allGroups, function ($group) {
            return !empty($group['items']);
        }));
    }

    public static function isActive($path)
    {
        return request()->is(ltrim($path, '/'));
    }

    public static function getIconSvg($iconName)
    {
        $icons = [
            'user-profile' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1526 4.3002 16.1184 5.61936 17.616C6.17279 15.3096 8.24852 13.5955 10.7246 13.5955H13.2746C15.7509 13.5955 17.8268 15.31 18.38 17.6167C19.6996 16.119 20.5 14.153 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.0246 18.8566V18.8455C17.0246 16.7744 15.3457 15.0955 13.2746 15.0955H10.7246C8.65354 15.0955 6.97461 16.7744 6.97461 18.8455V18.856C8.38223 19.8895 10.1198 20.5 12 20.5C13.8798 20.5 15.6171 19.8898 17.0246 18.8566ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM11.9991 7.25C10.8847 7.25 9.98126 8.15342 9.98126 9.26784C9.98126 10.3823 10.8847 11.2857 11.9991 11.2857C13.1135 11.2857 14.0169 10.3823 14.0169 9.26784C14.0169 8.15342 13.1135 7.25 11.9991 7.25ZM8.48126 9.26784C8.48126 7.32499 10.0563 5.75 11.9991 5.75C13.9419 5.75 15.5169 7.32499 15.5169 9.26784C15.5169 11.2107 13.9419 12.7857 11.9991 12.7857C10.0563 12.7857 8.48126 11.2107 8.48126 9.26784Z" fill="currentColor"/></svg>',
            'dashboard' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 3.25C4.25736 3.25 3.25 4.25736 3.25 5.5V8.99998C3.25 10.2426 4.25736 11.25 5.5 11.25H9C10.2426 11.25 11.25 10.2426 11.25 8.99998V5.5C11.25 4.25736 10.2426 3.25 9 3.25H5.5ZM4.75 5.5C4.75 5.08579 5.08579 4.75 5.5 4.75H9C9.41421 4.75 9.75 5.08579 9.75 5.5V8.99998C9.75 9.41419 9.41421 9.74998 9 9.74998H5.5C5.08579 9.74998 4.75 9.41419 4.75 8.99998V5.5ZM5.5 12.75C4.25736 12.75 3.25 13.7574 3.25 15V18.5C3.25 19.7426 4.25736 20.75 5.5 20.75H9C10.2426 20.75 11.25 19.7427 11.25 18.5V15C11.25 13.7574 10.2426 12.75 9 12.75H5.5ZM4.75 15C4.75 14.5858 5.08579 14.25 5.5 14.25H9C9.41421 14.25 9.75 14.5858 9.75 15V18.5C9.75 18.9142 9.41421 19.25 9 19.25H5.5C5.08579 19.25 4.75 18.9142 4.75 18.5V15ZM12.75 5.5C12.75 4.25736 13.7574 3.25 15 3.25H18.5C19.7426 3.25 20.75 4.25736 20.75 5.5V8.99998C20.75 10.2426 19.7426 11.25 18.5 11.25H15C13.7574 11.25 12.75 10.2426 12.75 8.99998V5.5ZM15 4.75C14.5858 4.75 14.25 5.08579 14.25 5.5V8.99998C14.25 9.41419 14.5858 9.74998 15 9.74998H18.5C18.9142 9.74998 19.25 9.41419 19.25 8.99998V5.5C19.25 5.08579 18.9142 4.75 18.5 4.75H15ZM15 12.75C13.7574 12.75 12.75 13.7574 12.75 15V18.5C12.75 19.7426 13.7574 20.75 15 20.75H18.5C19.7426 20.75 20.75 19.7427 20.75 18.5V15C20.75 13.7574 19.7426 12.75 18.5 12.75H15ZM14.25 15C14.25 14.5858 14.5858 14.25 15 14.25H18.5C18.9142 14.25 19.25 14.5858 19.25 15V18.5C19.25 18.9142 18.9142 19.25 18.5 19.25H15C14.5858 19.25 14.25 18.9142 14.25 18.5V15Z" fill="currentColor"></path></svg>',
            'trash' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 7H20" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M10 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M14 11V17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M6 7L7 19C7.1 20.1 8 21 9.1 21H14.9C16 21 16.9 20.1 17 19L18 7" stroke="currentColor" stroke-width="1.5"/><path d="M9 7V4C9 3.45 9.45 3 10 3H14C14.55 3 15 3.45 15 4V7" stroke="currentColor" stroke-width="1.5"/></svg>',
            'catalog' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5" /><path d="M7 8H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 12H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 16H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'department' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="7" width="18" height="14" stroke="currentColor" stroke-width="1.5"/><path d="M3 11H21M3 15H21" stroke="currentColor" stroke-width="1.5"/><rect x="10" y="16" width="4" height="5" fill="currentColor"/></svg>',
            'import' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 3V15M12 15L8 11M12 15L16 11M4 21H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'export' => '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21V9M12 9L8 13M12 9L16 13M4 3H20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ];

        return $icons[$iconName] ?? '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor"/></svg>';
    }
}