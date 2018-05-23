<?php
use Misuzu\Database;

define('MSZ_FORUM_TYPE_DISCUSSION', 0);
define('MSZ_FORUM_TYPE_CATEGORY', 1);
define('MSZ_FORUM_TYPE_LINK', 2);
define('MSZ_FORUM_TYPES', [
    MSZ_FORUM_TYPE_DISCUSSION,
    MSZ_FORUM_TYPE_CATEGORY,
    MSZ_FORUM_TYPE_LINK,
]);

function forum_get_breadcrumbs(
    int $forumId,
    string $linkFormat = '/forum/forum.php?f=%d',
    array $indexLink = ['Forums' => '/forum/']
): array {
    $breadcrumbs = [];
    $getBreadcrumb = Database::connection()->prepare('
        SELECT `forum_id`, `forum_name`, `forum_parent`
        FROM `msz_forum_categories`
        WHERE `forum_id` = :forum_id
    ');

    while ($forumId > 0) {
        $getBreadcrumb->bindValue('forum_id', $forumId);
        $breadcrumb = $getBreadcrumb->execute() ? $getBreadcrumb->fetch() : [];

        if (!$breadcrumb) {
            break;
        }

        $breadcrumbs[$breadcrumb['forum_name']] = sprintf($linkFormat, $breadcrumb['forum_id']);
        $forumId = $breadcrumb['forum_parent'];
    }

    return array_reverse($breadcrumbs + $indexLink);
}

function forum_increment_clicks(int $forumId): void
{
    $incrementLinkClicks = Database::connection()->prepare('
        UPDATE `msz_forum_categories`
        SET `forum_link_clicks` = `forum_link_clicks` + 1
        WHERE `forum_id` = :forum_id
        AND `forum_type` = 2
        AND `forum_link_clicks` IS NOT NULL
    ');
    $incrementLinkClicks->bindValue('forum_id', $forumId);
    $incrementLinkClicks->execute();
}
