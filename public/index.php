<?php
use Misuzu\Database;

require_once __DIR__ . '/../misuzu.php';

$config = $app->getConfig();
$tpl = $app->getTemplating();
$db = Database::connection();

if ($config->get('Site', 'embed_linked_data', 'bool', false)) {
    $tpl->vars([
        'embed_linked_data' => true,
        'embed_name' => $config->get('Site', 'name'),
        'embed_url' => $config->get('Site', 'url'),
        'embed_logo' => $config->get('Site', 'external_logo'),
        'embed_same_as' => explode(',', $config->get('Site', 'social_media')),
    ]);
}

$featuredNews = $db->query('
    SELECT
        p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
        u.`user_id`, u.`username`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `user_colour`
    FROM `msz_news_posts` as p
    LEFT JOIN `msz_users` as u
    ON p.`user_id` = u.`user_id`
    LEFT JOIN `msz_roles` as r
    ON u.`display_role` = r.`role_id`
    WHERE p.`is_featured` = true
    ORDER BY p.`created_at` DESC
    LIMIT 3
')->fetchAll(PDO::FETCH_ASSOC);

$usersCount = (int)$db->query('
    SELECT COUNT(`user_id`)
    FROM `msz_users`
')->fetchColumn();

$lastUser = $db->query('
    SELECT
        u.`user_id`, u.`username`, u.`created_at`,
        COALESCE(r.`role_colour`, CAST(0x40000000 AS UNSIGNED)) as `user_colour`
    FROM `msz_users` as u
    LEFT JOIN `msz_roles` as r
    ON r.`role_id` = u.`display_role`
    ORDER BY u.`created_at` DESC
    LIMIT 1
')->fetch(PDO::FETCH_ASSOC);

$featuredChangelog = $db->query('
    SELECT
        c.`change_id`, c.`change_log`,
        a.`action_name`, a.`action_colour`, a.`action_class`,
        DATE(`change_created`) as `change_date`,
        !ISNULL(c.`change_text`) as `change_has_text`
    FROM `msz_changelog_changes` as c
    LEFT JOIN `msz_changelog_actions` as a
    ON a.`action_id` = c.`action_id`
    WHERE DATE(c.`change_created`) >= (
         SELECT DATE(`change_created`)
         FROM `msz_changelog_changes`
         ORDER BY `change_created` DESC
         LIMIT 2, 1
    )
    ORDER BY c.`change_created` DESC
')->fetchAll(PDO::FETCH_ASSOC);

//var_dump(Database::connection()->query('SHOW SESSION STATUS LIKE "Questions"')->fetch()['Value']);

echo $tpl->render('home.index', [
    'users_count' => $usersCount,
    'last_user' => $lastUser,
    'featured_changelog' => $featuredChangelog,
    'featured_news' => $featuredNews,
]);
