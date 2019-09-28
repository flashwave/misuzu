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
    if($postId < 1) {
        $post = \Misuzu\DB::prepare('
            INSERT INTO `msz_news_posts`
                (`category_id`, `user_id`, `post_is_featured`, `post_title`, `post_text`, `post_scheduled`)
            VALUES
                (:category, :user, :featured, :title, :text, COALESCE(:scheduled, CURRENT_TIMESTAMP))
        ');
    } else {
        $post = \Misuzu\DB::prepare('
            UPDATE `msz_news_posts`
            SET `category_id` = :category,
                `user_id` = :user,
                `post_is_featured` = :featured,
                `post_title` = :title,
                `post_text` = :text,
                `post_scheduled` = COALESCE(:scheduled, `post_scheduled`)
            WHERE `post_id` = :id
        ');
        $post->bind('id', $postId);
    }

    $post->bind('title', $title);
    $post->bind('text', $text);
    $post->bind('category', $category);
    $post->bind('user', $user);
    $post->bind('featured', $featured ? 1 : 0);
    $post->bind('scheduled', empty($scheduled) ? null : date('Y-m-d H:i:s', $scheduled));

    return $post->execute() ? ($postId < 1 ? \Misuzu\DB::lastId() : $postId) : 0;
}

function news_category_create(string $name, string $description, bool $isHidden, ?int $categoryId = null): int {
    if($categoryId < 1) {
        $category = \Misuzu\DB::prepare('
            INSERT INTO `msz_news_categories`
                (`category_name`, `category_description`, `category_is_hidden`)
            VALUES
                (:name, :description, :hidden)
        ');
    } else {
        $category = \Misuzu\DB::prepare('
            UPDATE `msz_news_categories`
            SET `category_name` = :name,
                `category_description` = :description,
                `category_is_hidden` = :hidden
            WHERE `category_id` = :id
        ');
        $category->bind('id', $categoryId);
    }

    $category->bind('name', $name);
    $category->bind('description', $description);
    $category->bind('hidden', $isHidden ? 1 : 0);

    return $category->execute() ? ($categoryId < 1 ? \Misuzu\DB::lastId() : $categoryId) : 0;
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

    if($includePostCount) {
        $query = sprintf(
            '
                SELECT
                    c.`category_id`, c.`category_name`, c.`category_is_hidden`,
                    c.`category_created`,
                    (
                        SELECT COUNT(p.`post_id`)
                        FROM `msz_news_posts` as p
                        WHERE p.`category_id` = c.`category_id` %2$s %3$s %4$s
                    ) as `posts_count`
                FROM `msz_news_categories` as c
                %5$s
                GROUP BY c.`category_id`
                ORDER BY c.`category_id` DESC
                %1$s
            ',
            $getAll ? '' : 'LIMIT :offset, :take',
            $featuredOnly ? 'AND p.`post_is_featured` != 0' : '',
            $exposeScheduled ? '' : 'AND p.`post_scheduled` < NOW()',
            $excludeDeleted ? 'AND p.`post_deleted` IS NULL' : '',
            $includeHidden ? '' : 'WHERE c.`category_is_hidden` = 0'
        );
    } else {
        $query = sprintf(
            '
                SELECT
                    `category_id`, `category_name`, `category_is_hidden`,
                    `category_created`
                FROM `msz_news_categories`
                %2$s
                ORDER BY `category_id` DESC
                %1$s
            ',
            $getAll ? '' : 'LIMIT :offset, :take',
            $includeHidden ? '' : 'WHERE c.`category_is_hidden` != 0'
        );
    }

    $getCats = \Misuzu\DB::prepare($query);

    if(!$getAll) {
        $getCats->bind('offset', $offset);
        $getCats->bind('take', $take);
    }

    return $getCats->fetchAll();
}

function news_categories_count(bool $includeHidden = false): int {
    $countCats = \Misuzu\DB::prepare(sprintf('
        SELECT COUNT(`category_id`)
        FROM `msz_news_categories`
        %s
    ', $includeHidden ? '' : 'WHERE `category_is_hidden` = 0'));

    return (int)$countCats->fetchColumn();
}

function news_category_get(
    int $category,
    bool $includePostCount = false,
    bool $featuredOnly = false,
    bool $exposeScheduled = false,
    bool $excludeDeleted = true
): array {
    if($includePostCount) {
        $query = sprintf(
            '
                SELECT
                    c.`category_id`, c.`category_name`, c.`category_description`,
                    c.`category_is_hidden`, c.`category_created`,
                    (
                        SELECT COUNT(p.`post_id`)
                        FROM `msz_news_posts` as p
                        WHERE p.`category_id` = c.`category_id` %1$s %2$s %3$s
                    ) as `posts_count`
                FROM `msz_news_categories` as c
                WHERE c.`category_id` = :category
                GROUP BY c.`category_id`
            ',
            $featuredOnly ? 'AND p.`post_is_featured` != 0' : '',
            $exposeScheduled ? '' : 'AND p.`post_scheduled` < NOW()',
            $excludeDeleted ? 'AND p.`post_deleted` IS NULL' : ''
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

    $getCategory = \Misuzu\DB::prepare($query);
    $getCategory->bind('category', $category);
    return $getCategory->fetch();
}

function news_posts_count(
    ?int $category = null,
    bool $featuredOnly = false,
    bool $exposeScheduled = false,
    bool $excludeDeleted = true
): int {
    $hasCategory= $category !== null;

    $countPosts = \Misuzu\DB::prepare(sprintf(
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

    if($hasCategory) {
        $countPosts->bind('category', $category);
    }

    return (int)$countPosts->fetchColumn();
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
    $hasCategory = $category !== null;

    $getPosts = \Misuzu\DB::prepare(sprintf(
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

    if($hasCategory) {
        $getPosts->bind('category', $category);
    }

    if(!$getAll) {
        $getPosts->bind('take', $take);
        $getPosts->bind('offset', $offset);
    }

    return $getPosts->fetchAll();
}

function news_posts_search(string $query): array {
    $searchPosts = \Misuzu\DB::prepare('
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
        WHERE MATCH(`post_title`, `post_text`)
        AGAINST (:query IN NATURAL LANGUAGE MODE)
        AND p.`post_deleted` IS NULL
        AND p.`post_scheduled` < NOW()
        ORDER BY p.`post_created` DESC
    ');
    $searchPosts->bind('query', $query);

    return $searchPosts->fetchAll();
}

function news_post_comments_set(int $postId, int $sectionId): void {
    \Misuzu\DB::prepare('
        UPDATE `msz_news_posts`
        SET `comment_section_id` = :comment_section_id
        WHERE `post_id` = :post_id
    ')->execute([
        'comment_section_id' => $sectionId,
        'post_id' => $postId,
    ]);
}

function news_post_get(int $postId): array {
    $getPost = \Misuzu\DB::prepare('
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
    $getPost->bind(':post_id', $postId);
    return $getPost->fetch();
}

define('MSZ_NEWS_FEED_ATOM', 'atom');
define('MSZ_NEWS_FEED_RSS', 'rss');
define('MSZ_NEWS_SUPPORTED_FEEDS', [
    MSZ_NEWS_FEED_ATOM, MSZ_NEWS_FEED_RSS,
]);

function news_feed_supported(string $type): string {
    return in_array($type, MSZ_NEWS_SUPPORTED_FEEDS);
}

function news_feed(string $type, array $posts, array $info): string {
    if(!news_feed_supported($type)) {
        return '';
    }

    $document = new DOMDocument('1.0', 'utf-8');
    $urlPrefix = url_prefix(false);
    $htmlUrl = $urlPrefix . $info['html-url'];
    $feedUrl = $urlPrefix . $info['feed-url'];

    if($type === 'rss') {
        $dateFormat = 'r';
        $rss = $document->appendChild($document->createElement('rss'));
        $rss->setAttribute('version', '2.0');
        $rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $feed = $rss->appendChild($document->createElement('channel'));
        $feed->appendChild($document->createElement('ttl', '900'));
    } else {
        $dateFormat = 'c';
        $feed = $document->appendChild($document->createElement('feed'));
        $feed->setAttribute('xmlns', 'http://www.w3.org/2005/Atom');
        $link = $feed->appendChild($document->createElement('link'));
        $link->setAttribute('href', $htmlUrl);
    }

    $feed->appendChild($document->createElement('title', $info['title']));
    $feed->appendChild($document->createElement(
        $type === 'rss' ? 'description' : 'subtitle', $info['subtitle']
    ));
    $feed->appendChild($document->createElement(
        $type === 'rss' ? 'link' : 'id', $htmlUrl
    ));
    $feed->appendChild($document->createElement(
        $type === 'rss' ? 'pubDate' : 'updated',
        date($dateFormat, strtotime($posts[0]['post_created']))
    ));
    $link = $feed->appendChild($document->createElement($type === 'rss' ? 'atom:link' : 'link'));
    $link->setAttribute('rel', 'self');
    $link->setAttribute('href', $feedUrl);

    foreach($posts as $post) {
        $entry = $feed->appendChild($document->createElement($type === 'rss' ? 'item' : 'entry'));
        $entry->appendChild($document->createElement('title', $post['post_title']));
        $entry->appendChild($document->createElement(
            $type === 'rss' ? 'link' : 'id',
            $urlPrefix . url('news-post', ['post' => $post['post_id']])
        ));
        $entry->appendChild($document->createElement(
            $type === 'rss' ? 'pubDate' : 'updated',
            date($dateFormat, strtotime($post['post_created']))
        ));

        $entry->appendChild($document->createElement(
            $type === 'rss' ? 'description' : 'summary',
            first_paragraph($post['post_text'])
        ));

        if($type === 'rss') {
            $entry->appendChild($document->createElement(
                'comments',
                $urlPrefix . url('news-post-comments', ['post' => $post['post_id']])
            ));
            $guid = $entry->appendChild($document->createElement(
                'guid',
                $urlPrefix . url('news-post', ['post' => $post['post_id']])
            ));
            $guid->setAttribute('isPermaLink', 'true');
        } else {
            $link = $entry->appendChild($document->createElement('link'));
            $link->setAttribute('href', $urlPrefix . url('news-post', ['post' => $post['post_id']]));
            $link->setAttribute('type', 'text/html');
            $html = $entry->appendChild($document->createElement(
                'content',
                htmlentities(parse_text($post['post_text'], MSZ_PARSER_MARKDOWN))
            ));
            $html->setAttribute('type', 'html');
            $author = $entry->appendChild($document->createElement('author'));
            $author->appendChild($document->createElement('name', $post['username']));
            $author->appendChild($document->createElement('uri', $urlPrefix . url('user-profile', ['user' => $post['user_id']])));
        }
    }

    return $document->saveXML();
}
