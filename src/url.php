<?php
// URL FORMATTING
// [0] => Path part of URL.
// [1] => Query part of URL.
// [2] => Fragment part of URL.
//
// text surrounded by < and > will be replaced accordingly to an array of variables supplied to the format function
// text surrounded by [ and ] will be replaced by the constant/define of that name
// text surrounded by { and } will be replaced by a CSRF token with the given text as its realm, this will have no effect in a sessionless environment
define('MSZ_URLS', [
    'index'                             => ['/'],
    'info'                              => ['/info.php/<title>'],
    'media-proxy'                       => ['/proxy.php/<hash>/<url>'],

    'search-index'                      => ['/search.php'],
    'search-query'                      => ['/search.php',              ['q' => '<query>']],

    'auth-login'                        => ['/auth/login.php',          ['username' => '<username>', 'redirect' => '<redirect>']],
    'auth-login-welcome'                => ['/auth/login.php',          ['welcome' => '1', 'username' => '<username>']],
    'auth-register'                     => ['/auth/register.php'],
    'auth-forgot'                       => ['/auth/password.php'],
    'auth-reset'                        => ['/auth/password.php',       ['user' => '<user>']],
    'auth-logout'                       => ['/auth/logout.php',         ['token' => '{logout}']],
    'auth-resolve-user'                 => ['/auth/login.php',          ['resolve_user' => '<username>']],
    'auth-two-factor'                   => ['/auth/twofactor.php',      ['token' => '<token>']],

    'changelog-index'                   => ['/changelog.php'],
    'changelog-change'                  => ['/changelog.php',           ['c' => '<change>']],
    'changelog-date'                    => ['/changelog.php',           ['d' => '<date>']],
    'changelog-tag'                     => ['/changelog.php',           ['t' => '<tag>']],

    'news-index'                        => ['/news.php',                ['page' => '<page>']],
    'news-post'                         => ['/news.php',                ['p' => '<post>']],
    'news-post-comments'                => ['/news.php',                ['p' => '<post>'], 'comments'],
    'news-category'                     => ['/news.php',                ['c' => '<category>', 'page' => '<page>']],
    'news-feed-rss'                     => ['/news.php/rss'],
    'news-category-feed-rss'            => ['/news.php/rss',            ['c' => '<category>']],
    'news-feed-atom'                    => ['/news.php/atom'],
    'news-category-feed-atom'           => ['/news.php/atom',           ['c' => '<category>']],

    'forum-index'                       => ['/forum'],
    'forum-leaderboard'                 => ['/forum/leaderboard.php',   ['id' => '<id>', 'mode' => '<mode>']],
    'forum-mark-global'                 => ['/forum/index.php',         ['m' => 'mark', 'c' => '{forum_mark}']],
    'forum-mark-single'                 => ['/forum/index.php',         ['m' => 'mark', 'c' => '{forum_mark}', 'f' => '<forum>']],
    'forum-topic-new'                   => ['/forum/posting.php',       ['f' => '<forum>']],
    'forum-reply-new'                   => ['/forum/posting.php',       ['t' => '<topic>']],
    'forum-category'                    => ['/forum/forum.php',         ['f' => '<forum>', 'p' => '<page>']],
    'forum-topic'                       => ['/forum/topic.php',         ['t' => '<topic>', 'page' => '<page>']],
    'forum-topic-create'                => ['/forum/posting.php',       ['f' => '<forum>']],
    'forum-topic-bump'                  => ['/forum/topic.php',         ['t' => '<topic>', 'm' => 'bump', 'csrf[forum_post]' => '{forum_post}']],
    'forum-topic-lock'                  => ['/forum/topic.php',         ['t' => '<topic>', 'm' => 'lock', 'csrf[forum_post]' => '{forum_post}']],
    'forum-topic-unlock'                => ['/forum/topic.php',         ['t' => '<topic>', 'm' => 'unlock', 'csrf[forum_post]' => '{forum_post}']],
    'forum-topic-delete'                => ['/forum/topic.php',         ['t' => '<topic>', 'm' => 'delete', 'csrf[forum_post]' => '{forum_post}']],
    'forum-topic-restore'               => ['/forum/topic.php',         ['t' => '<topic>', 'm' => 'restore', 'csrf[forum_post]' => '{forum_post}']],
    'forum-topic-nuke'                  => ['/forum/topic.php',         ['t' => '<topic>', 'm' => 'nuke', 'csrf[forum_post]' => '{forum_post}']],
    'forum-topic-priority'              => ['/forum/topic-priority.php', ['t' => '<topic>', 'b' => '<bump>']],
    'forum-post'                        => ['/forum/topic.php',         ['p' => '<post>'], '<post_fragment>'],
    'forum-post-create'                 => ['/forum/posting.php',       ['t' => '<topic>']],
    'forum-post-delete'                 => ['/forum/post.php',          ['p' => '<post>', 'm' => 'delete']],
    'forum-post-restore'                => ['/forum/post.php',          ['p' => '<post>', 'm' => 'restore']],
    'forum-post-nuke'                   => ['/forum/post.php',          ['p' => '<post>', 'm' => 'nuke']],
    'forum-post-quote'                  => ['/forum/posting.php',       ['q' => '<post>']],
    'forum-post-edit'                   => ['/forum/posting.php',       ['p' => '<post>', 'm' => 'edit']],
    'forum-poll-vote'                   => ['/forum/poll.php'],

    'user-list'                         => ['/members.php',             ['r' => '<role>', 'ss' => '<sort>', 'sd' => '<direction>', 'p' => '<page>']],

    'user-profile'                      => ['/profile.php',             ['u' => '<user>']],
    'user-profile-following'            => ['/profile.php',             ['u' => '<user>', 'm' => 'following']],
    'user-profile-followers'            => ['/profile.php',             ['u' => '<user>', 'm' => 'followers']],
    'user-profile-forum-topics'         => ['/profile.php',             ['u' => '<user>', 'm' => 'forum-topics']],
    'user-profile-forum-posts'          => ['/profile.php',             ['u' => '<user>', 'm' => 'forum-posts']],
    'user-profile-edit'                 => ['/profile.php',             ['u' => '<user>', 'edit' => '1']],
    'user-account-standing'             => ['/profile.php',             ['u' => '<user>'], 'account-standing'],

    'user-avatar'                       => ['/user-assets.php',         ['u' => '<user>', 'm' => 'avatar', 'r' => '<res>']],
    'user-background'                   => ['/user-assets.php',         ['u' => '<user>', 'm' => 'background']],

    'user-relation-create'              => ['/relations.php',           ['u' => '<user>', 'm' => '<type>', 'c' => '{user_relation}']],
    'user-relation-none'                => ['/relations.php',           ['u' => '<user>', 'm' => '[MSZ_USER_RELATION_NONE]', 'c' => '{user_relation}']],
    'user-relation-follow'              => ['/relations.php',           ['u' => '<user>', 'm' => '[MSZ_USER_RELATION_FOLLOW]', 'c' => '{user_relation}']],

    'settings-index'                    => ['/settings.php'],
    'settings-mode'                     => ['/settings.php',            [], '<mode>'],

    'comment-create'                    => ['/comments.php',            ['m' => 'create']],
    'comment-vote'                      => ['/comments.php',            ['c' => '<comment>', 'csrf' => '{comments}', 'm' => 'vote', 'v' => '<vote>']],
    'comment-delete'                    => ['/comments.php',            ['c' => '<comment>', 'csrf' => '{comments}', 'm' => 'delete']],
    'comment-restore'                   => ['/comments.php',            ['c' => '<comment>', 'csrf' => '{comments}', 'm' => 'restore']],
    'comment-pin'                       => ['/comments.php',            ['c' => '<comment>', 'csrf' => '{comments}', 'm' => 'pin']],
    'comment-unpin'                     => ['/comments.php',            ['c' => '<comment>', 'csrf' => '{comments}', 'm' => 'unpin']],

    'manage-changelog-tag-create'       => ['/manage/changelog.php',    ['v' => 'tag']],
    'manage-changelog-tag-edit'         => ['/manage/changelog.php',    ['v' => 'tag', 't' => '<tag>']],
    'manage-changelog-action-create'    => ['/manage/changelog.php',    ['v' => 'action']],
    'manage-changelog-action-edit'      => ['/manage/changelog.php',    ['v' => 'action', 'a' => '<action>']],
    'manage-changelog-change-create'    => ['/manage/changelog.php',    ['v' => 'change']],
    'manage-changelog-change-edit'      => ['/manage/changelog.php',    ['v' => 'change', 'c' => '<change>']],

    'manage-forum-category-view'        => ['/manage/forum.php',        ['v' => 'forum', 'f' => '<forum>']],

    'manage-news-category-create'       => ['/manage/news.php',         ['v' => 'category']],
    'manage-news-category-edit'         => ['/manage/news.php',         ['v' => 'category', 'c' => '<category>']],
    'manage-news-post-create'           => ['/manage/news.php',         ['v' => 'post']],
    'manage-news-post-edit'             => ['/manage/news.php',         ['v' => 'post', 'p' => '<post>']],

    'manage-user-index'                 => ['/manage/users.php',        ['v' => 'listing']],
    'manage-user-edit'                  => ['/manage/users.php',        ['v' => 'view', 'u' => '<user>']],

    'manage-role-index'                 => ['/manage/users.php',        ['v' => 'roles']],
    'manage-role-create'                => ['/manage/users.php',        ['v' => 'role']],
    'manage-role-edit'                  => ['/manage/users.php',        ['v' => 'role', 'r' => '<role>']],

    'manage-warning-delete'             => ['/manage/users.php',        ['v' => 'warnings', 'u' => '<user>', 'w' => '<warning>', 'm' => 'delete', 'c' => '<token>']],
]);

function url(string $name, array $variables = []): string
{
    if (!array_key_exists($name, MSZ_URLS)) {
        return '';
    }

    $info = MSZ_URLS[$name];

    if (!is_string($info[0] ?? null)) {
        return '';
    }

    $splitUrl = explode('/', $info[0]);

    for ($i = 0; $i < count($splitUrl); $i++) {
        $splitUrl[$i] = url_variable($splitUrl[$i], $variables);
    }

    $url = implode('/', $splitUrl);

    if (!empty($info[1]) && is_array($info[1])) {
        $url .= '?';

        foreach ($info[1] as $key => $value) {
            $value = url_variable($value, $variables);

            if (empty($value) || ($key === 'page' && $value < 2)) {
                continue;
            }

            $url .= sprintf('%s=%s&', $key, $value);
        }

        $url = trim($url, '?&');
    }

    if (!empty($info[2]) && is_string($info[2])) {
        $url .= rtrim(sprintf('#%s', url_variable($info[2], $variables)), '#');
    }

    return $url;
}

function url_variable(string $value, array $variables): string
{
    if (starts_with($value, '<') && ends_with($value, '>')) {
        return $variables[trim($value, '<>')] ?? '';
    }

    if (starts_with($value, '[') && ends_with($value, ']')) {
        return constant(trim($value, '[]'));
    }

    if (starts_with($value, '{') && ends_with($value, '}') && csrf_is_ready()) {
        return csrf_token(trim($value, '{}'));
    }

    return $value;
}

function url_list(): array
{
    $collection = [];

    foreach (MSZ_URLS as $name => $urlInfo) {
        $item = [
            'name' => $name,
            'path' => $urlInfo[0],
            'query' => [],
            'fragment' => $urlInfo[2] ?? '',
        ];

        if (!empty($urlInfo[1]) && is_array($urlInfo[1])) {
            foreach ($urlInfo[1] as $name => $value) {
                $item['query'][] = [
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }

        $collection[] = $item;
    }

    return $collection;
}

function url_construct(string $url, array $query = [], ?string $fragment = null): string
{
    if (count($query)) {
        $url .= mb_strpos($url, '?') !== false ? '&' : '?';

        foreach ($query as $key => $value) {
            if ($value) {
                $url .= rawurlencode($key) . '=' . rawurlencode($value) . '&';
            }
        }

        $url = mb_substr($url, 0, -1);
    }

    if (!empty($fragment)) {
        $url .= "#{$fragment}";
    }

    return $url;
}

function url_proxy_media(?string $url): ?string
{
    if (empty($url) || !config_get_default(false, 'Proxy', 'enabled') || is_local_url($url)) {
        return $url;
    }

    $secret = config_get_default('insecure', 'Proxy', 'secret_key');
    $url = base64url_encode($url);
    $hash = hash_hmac('sha256', $url, $secret);

    return url('media-proxy', compact('hash', 'url'));
}

function url_prefix(bool $trailingSlash = true): string
{
    return 'http' . (empty($_SERVER['HTTPS']) ? '' : 's') . '://' . $_SERVER['HTTP_HOST'] . ($trailingSlash ? '/' : '');
}

function is_local_url(string $url): bool
{
    $length = mb_strlen($url);

    if ($length < 1) {
        return false;
    }

    if ($url[0] === '/' && ($length > 1 ? $url[1] !== '/' : true)) {
        return true;
    }

    return starts_with($url, url_prefix());
}
