<?php
require_once '../misuzu.php';

if (MSZ_DEBUG) {
    if (!empty($_GET['pdump'])) {
        header('Content-Type: text/plain');

        for ($i = 0; $i < 10; $i++) {
            $perms = [];

            echo "# USER {$i}\n";

            foreach (MSZ_PERM_MODES as $mode) {
                $perms = decbin(perms_get_user($mode, $i));
                echo "{$mode}: {$perms}\n";
            }

            echo "\n";
        }
        return;
    }

    if (!empty($_GET['cidr'])) {
        header('Content-Type: text/plain');

        $checks = [
            [
                'cidr' => '104.16.0.0/12',
                'addrs' => [
                    '104.28.8.4',
                    '104.28.9.4',
                    '94.211.73.13',
                ],
            ],
        ];

        foreach ($checks as $check) {
            $mask = ip_cidr_to_mask($check['cidr']);

            echo 'MASK> ' .  inet_ntop($mask) . "\t" . decbin_str($mask) . PHP_EOL;

            foreach ($check['addrs'] as $addr) {
                $addr = inet_pton($addr);
                echo 'ADDR> ' . inet_ntop($addr) . "\t" . decbin_str($addr) . "\t" . ip_match_mask($addr, $mask) . PHP_EOL;
            }

            echo PHP_EOL;
        }
        return;
    }
}

if (config_get_default(false, 'Site', 'embed_linked_data')) {
    tpl_var('linked_data', [
        'name' => config_get('Site', 'name'),
        'url' => config_get('Site', 'url'),
        'logo' => config_get('Site', 'external_logo'),
        'same_as' => explode(',', config_get_default('', 'Site', 'social_media')),
    ]);
}

$news = news_posts_get(0, 5, null, true);

$statistics = cache_get('index:stats:v1', function () {
    return [
        'users' => (int)db_query('
            SELECT COUNT(`user_id`)
            FROM `msz_users`
        ')->fetchColumn(),
        'lastUser' => db_query('
            SELECT
                u.`user_id`, u.`username`, u.`user_created`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
            FROM `msz_users` as u
            LEFT JOIN `msz_roles` as r
            ON r.`role_id` = u.`display_role`
            ORDER BY u.`user_id` DESC
            LIMIT 1
        ')->fetch(PDO::FETCH_ASSOC),
    ];
}, 10800);

$changelog = cache_get('index:changelog:v1', function () {
    return db_query('
        SELECT
            c.`change_id`, c.`change_log`,
            a.`action_name`, a.`action_colour`, a.`action_class`,
            DATE(`change_created`) as `change_date`,
            !ISNULL(c.`change_text`) as `change_has_text`
        FROM `msz_changelog_changes` as c
        LEFT JOIN `msz_changelog_actions` as a
        ON a.`action_id` = c.`action_id`
        ORDER BY c.`change_created` DESC
        LIMIT 10
    ')->fetchAll(PDO::FETCH_ASSOC);
}, 1800);

$onlineUsers = cache_get('index:online:v1', function () {
    return db_query('
        SELECT
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_users` as u
        LEFT JOIN `msz_roles` as r
        ON r.`role_id` = u.`display_role`
        WHERE u.`user_active` >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY RAND()
        LIMIT 104
    ')->fetchAll(PDO::FETCH_ASSOC);
}, -1);

echo tpl_render('home.' . (user_session_active() ? 'home' : 'landing'), [
    'users_count' => $statistics['users'],
    'last_user' => $statistics['lastUser'],
    'online_users' => $onlineUsers,
    'chat_quote' => chat_quotes_random(),
    'featured_changelog' => $changelog,
    'featured_news' => $news,
]);
