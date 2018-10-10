<?php
define('MSZ_PERM_NEWS_MANAGE_POSTS', 1);
define('MSZ_PERM_NEWS_MANAGE_CATEGORIES', 1 << 1);

function news_categories_get(
    int $offset,
    int $take,
    bool $includePostCount = false,
    bool $featuredOnly = false,
    bool $exposeScheduled = false,
    bool $excludeDeleted = true
): array {
    $getAll = $offset < 0 || $take < 1;

    if ($includePostCount) {
        $query = sprintf(
            '
                SELECT
                    `category_id`, `category_name`, `is_hidden`,
                    `created_at`, `updated_at`,
                    (
                        SELECT COUNT(`post_id`)
                        FROM `msz_news_posts`
                        WHERE %2$s %3$s %4$s
                    ) as `posts_count`
                FROM `msz_news_categories`
                GROUP BY `category_id`
                ORDER BY `category_id` DESC
                %1$s
            ',
            $getAll ? '' : 'LIMIT :offset, :take',
            $featuredOnly ? '`is_featured`' : '1',
            $exposeScheduled ? '' : 'AND `scheduled_for` < NOW()',
            $excludeDeleted ? 'AND `deleted_at` IS NULL' : ''
        );
    } else {
        $query = sprintf('
            SELECT
                `category_id`, `category_name`, `is_hidden`,
                `created_at`, `updated_at`
            FROM `msz_news_categories`
            ORDER BY `category_id` DESC
            %s
        ', $getAll ? '' : 'LIMIT :offset, :take');
    }

    $getCats = db_prepare($query);

    if (!$getAll) {
        $getCats->bindValue('offset', $offset);
        $getCats->bindValue('take', $take);
    }

    $cats = $getCats->execute() ? $getCats->fetchAll(PDO::FETCH_ASSOC) : false;
    return $cats ? $cats : [];
}

function news_categories_single(
    int $category,
    bool $includePostCount = false,
    bool $featuredOnly = false,
    bool $exposeScheduled = false,
    bool $excludeDeleted = true
): array {
    if ($includePostCount) {
        $query = sprintf(
            '
                SELECT
                    c.`category_id`, c.`category_name`, c.`category_description`,
                    COUNT(p.`post_id`) AS `posts_count`
                FROM `msz_news_categories` as c
                LEFT JOIN `msz_news_posts` as p
                ON c.`category_id` = p.`category_id`
                WHERE c.`category_id` = :category %1$s %2$s %3$s
                GROUP BY c.`category_id`
            ',
            $featuredOnly ? 'AND p.`is_featured` = 1' : '',
            $exposeScheduled ? '' : 'AND p.`scheduled_for` < NOW()',
            $excludeDeleted ? 'AND p.`deleted_at` IS NULL' : ''
        );
    } else {
        $query = '
            SELECT
                c.`category_id`, c.`category_name`, c.`category_description`,
            FROM `msz_news_categories` as c
            WHERE c.`category_id` = :category
            GROUP BY c.`category_id`
        ';
    }

    $getCategory = db_prepare($query);
    $getCategory->bindValue('category', $category);
    $category = $getCategory->execute() ? $getCategory->fetch(PDO::FETCH_ASSOC) : false;
    return $category ? $category : [];
}

function news_posts_count(
    ?int $category = null,
    bool $featuredOnly = false,
    bool $exposeScheduled = false,
    bool $excludeDeleted = true
): int {
    $hasCategory= $category !== null;

    $countPosts = db_prepare(sprintf(
        '
            SELECT COUNT(`post_id`)
            FROM `msz_news_posts`
            WHERE %1$s %2$s %3$s %4$s
        ',
        $hasCategory ? '`category_id` = :category' : '1',
        $featuredOnly ? 'AND `is_featured` = 1' : '',
        $exposeScheduled ? '' : 'AND `scheduled_for` < NOW()',
        $excludeDeleted ? 'AND `deleted_at` IS NULL' : ''
    ));

    if ($hasCategory) {
        $countPosts->bindValue('category', $category);
    }

    return $countPosts->execute() ? (int)$countPosts->fetchColumn() : 0;
}

function news_posts_get(
    int $offset,
    int $take,
    ?int $category = null,
    bool $featuredOnly = false,
    bool $exposeScheduled = false,
    bool $excludeDeleted = true
): array {
    $getAll = $offset < 0 || $take < 1;
    $hasCategory= $category !== null;

    $getPosts = db_prepare(sprintf(
        '
            SELECT
                p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`,
                c.`category_id`, c.`category_name`,
                u.`user_id`, u.`username`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
                (
                    SELECT COUNT(`comment_id`)
                    FROM `msz_comments_posts`
                    WHERE `category_id` = `comment_section_id`
                ) as `post_comments`
            FROM `msz_news_posts` as p
            LEFT JOIN `msz_news_categories` as c
            ON p.`category_id` = c.`category_id`
            LEFT JOIN `msz_users` as u
            ON p.`user_id` = u.`user_id`
            LEFT JOIN `msz_roles` as r
            ON u.`display_role` = r.`role_id`
            WHERE %5$s %2$s %3$s %4$s
            ORDER BY p.`created_at` DESC
            %1$s
        ',
        $getAll ? '' : 'LIMIT :offset, :take',
        $featuredOnly ? 'AND p.`is_featured` = 1' : '',
        $exposeScheduled ? '' : 'AND p.`scheduled_for` < NOW()',
        $excludeDeleted ? 'AND p.`deleted_at` IS NULL' : '',
        $hasCategory ? 'p.`category_id` = :category' : '1'
    ));

    if ($hasCategory) {
        $getPosts->bindValue('category', $category);
    }

    if (!$getAll) {
        $getPosts->bindValue('take', $take);
        $getPosts->bindValue('offset', $offset);
    }

    $posts = $getPosts->execute() ? $getPosts->fetchAll(PDO::FETCH_ASSOC) : false;
    return $posts ? $posts : [];
}

function news_post_comments_set(int $postId, int $sectionId): void
{
    db_prepare('
        UPDATE `msz_news_posts`
        SET `comment_section_id` = :comment_section_id
        WHERE `post_id` = :post_id
    ')->execute([
        'comment_section_id' => $sectionId,
        'post_id' => $postId,
    ]);
}

function news_post_get(int $postId): array
{
    $getPost = db_prepare('
        SELECT
            p.`post_id`, p.`post_title`, p.`post_text`, p.`created_at`, p.`comment_section_id`,
            c.`category_id`, c.`category_name`,
            u.`user_id`, u.`username`,
            COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`
        FROM `msz_news_posts` as p
        LEFT JOIN `msz_news_categories` as c
        ON p.`category_id` = c.`category_id`
        LEFT JOIN `msz_users` as u
        ON p.`user_id` = u.`user_id`
        LEFT JOIN `msz_roles` as r
        ON u.`display_role` = r.`role_id`
        WHERE `post_id` = :post_id
    ');

    $getPost->bindValue(':post_id', $postId);
    $post = $getPost->execute() ? $getPost->fetch(PDO::FETCH_ASSOC) : false;

    return $post ? $post : [];
}
