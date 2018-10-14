<?php
define('MSZ_PERM_NEWS_MANAGE_POSTS', 1);
define('MSZ_PERM_NEWS_MANAGE_CATEGORIES', 1 << 1);

function news_post_create(
    string $title,
    string $text,
    int $category,
    int $user,
    bool $featured = false,
    ?int $scheduled = null,
    ?int $postId = null
): int {
    if ($postId < 1) {
        $post = db_prepare('
            INSERT INTO `msz_news_posts`
                (`category_id`, `user_id`, `post_is_featured`, `post_title`, `post_text`, `post_scheduled`)
            VALUES
                (:category, :user, :featured, :title, :text, COALESCE(:scheduled, CURRENT_TIMESTAMP))
        ');
    } else {
        $post = db_prepare('
            UPDATE `msz_news_posts`
            SET `category_id` = :category,
                `user_id` = :user,
                `post_is_featured` = :featured,
                `post_title` = :title,
                `post_text` = :text,
                `post_scheduled` = COALESCE(:scheduled, `post_scheduled`)
            WHERE `post_id` = :id
        ');
        $post->bindValue('id', $postId);
    }

    $post->bindValue('title', $title);
    $post->bindValue('text', $text);
    $post->bindValue('category', $category);
    $post->bindValue('user', $user);
    $post->bindValue('featured', $featured ? 1 : 0);
    $post->bindValue('scheduled', empty($scheduled) ? null : date('Y-m-d H:i:s', $scheduled));

    return $post->execute() ? ($postId < 1 ? (int)db_last_insert_id() : $postId) : 0;
}

function news_category_create(string $name, string $description, bool $isHidden, ?int $categoryId = null): int
{
    if ($categoryId < 1) {
        $category = db_prepare('
            INSERT INTO `msz_news_categories`
                (`category_name`, `category_description`, `category_is_hidden`)
            VALUES
                (:name, :description, :hidden)
        ');
    } else {
        $category = db_prepare('
            UPDATE `msz_news_categories`
            SET `category_name` = :name,
                `category_description` = :description,
                `category_is_hidden` = :hidden
            WHERE `category_id` = :id
        ');
        $category->bindValue('id', $categoryId);
    }

    $category->bindValue('name', $name);
    $category->bindValue('description', $description);
    $category->bindValue('hidden', $isHidden ? 1 : 0);

    return $category->execute() ? ($categoryId < 1 ? (int)db_last_insert_id() : $categoryId) : 0;
}

function news_categories_get(
    int $offset,
    int $take,
    bool $includePostCount = false,
    bool $featuredOnly = false,
    bool $includeHidden = false,
    bool $exposeScheduled = false,
    bool $excludeDeleted = true
): array {
    $getAll = $offset < 0 || $take < 1;

    if ($includePostCount) {
        $query = sprintf(
            '
                SELECT
                    `category_id`, `category_name`, `category_is_hidden`,
                    `category_created`,
                    (
                        SELECT COUNT(`post_id`)
                        FROM `msz_news_posts`
                        WHERE %2$s %3$s %4$s
                    ) as `posts_count`
                FROM `msz_news_categories`
                %5$s
                GROUP BY `category_id`
                ORDER BY `category_id` DESC
                %1$s
            ',
            $getAll ? '' : 'LIMIT :offset, :take',
            $featuredOnly ? '`post_is_featured` != 0' : '1',
            $exposeScheduled ? '' : 'AND `post_scheduled` < NOW()',
            $excludeDeleted ? 'AND `post_deleted` IS NULL' : '',
            $includeHidden ? '' : 'WHERE `category_is_hidden` != 0'
        );
    } else {
        $query = sprintf('
            SELECT
                `category_id`, `category_name`, `category_is_hidden`,
                `category_created`
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

function news_categories_count(bool $includeHidden = false): int
{
    $countCats = db_prepare(sprintf('
        SELECT COUNT(`category_id`)
        FROM `msz_news_categories`
        %s
    ', $includeHidden ? '' : 'WHERE `category_is_hidden` = 0'));

    return $countCats->execute() ? (int)$countCats->fetchColumn() : 0;
}

function news_category_get(
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
                    `category_id`, `category_name`, `category_description`,
                    `category_is_hidden`, `category_created`,
                    (
                        SELECT COUNT(`post_id`)
                        FROM `msz_news_posts`
                        WHERE `category_id` = `category_id` %1$s %2$s %3$s
                    ) as `posts_count`
                FROM `msz_news_categories`
                WHERE `category_id` = :category
                GROUP BY `category_id`
            ',
            $featuredOnly ? 'AND `post_is_featured` != 0' : '',
            $exposeScheduled ? '' : 'AND `post_scheduled` < NOW()',
            $excludeDeleted ? 'AND `post_deleted` IS NULL' : ''
        );
    } else {
        $query = '
            SELECT
                `category_id`, `category_name`, `category_description`,
                `category_is_hidden`, `category_created`
            FROM `msz_news_categories`
            WHERE `category_id` = :category
            GROUP BY `category_id`
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
        $featuredOnly ? 'AND `post_is_featured` != 0' : '',
        $exposeScheduled ? '' : 'AND `post_scheduled` < NOW()',
        $excludeDeleted ? 'AND `post_deleted` IS NULL' : ''
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
                p.`post_id`, p.`post_is_featured`, p.`post_title`, p.`post_text`, p.`comment_section_id`,
                p.`post_created`, p.`post_updated`, p.`post_deleted`, p.`post_scheduled`,
                c.`category_id`, c.`category_name`,
                u.`user_id`, u.`username`,
                COALESCE(u.`user_colour`, r.`role_colour`) as `user_colour`,
                (
                    SELECT COUNT(`comment_id`)
                    FROM `msz_comments_posts`
                    WHERE `category_id` = `comment_section_id`
                    AND `comment_deleted` IS NULL
                ) as `post_comments`
            FROM `msz_news_posts` as p
            LEFT JOIN `msz_news_categories` as c
            ON p.`category_id` = c.`category_id`
            LEFT JOIN `msz_users` as u
            ON p.`user_id` = u.`user_id`
            LEFT JOIN `msz_roles` as r
            ON u.`display_role` = r.`role_id`
            WHERE %5$s %2$s %3$s %4$s
            ORDER BY p.`post_created` DESC
            %1$s
        ',
        $getAll ? '' : 'LIMIT :offset, :take',
        $featuredOnly ? 'AND p.`post_is_featured` != 0' : '',
        $exposeScheduled ? '' : 'AND p.`post_scheduled` < NOW()',
        $excludeDeleted ? 'AND p.`post_deleted` IS NULL' : '',
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
            p.`post_id`, p.`post_title`, p.`post_text`, p.`post_is_featured`, p.`post_scheduled`,
            p.`post_created`, p.`post_updated`, p.`post_deleted`, p.`comment_section_id`,
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
