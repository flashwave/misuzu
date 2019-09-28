<?php
namespace Misuzu;

require_once '../../../misuzu.php';

if(!perms_check_user(MSZ_PERMS_NEWS, user_session_current('user_id'), MSZ_PERM_NEWS_MANAGE_CATEGORIES)) {
    echo render_error(403);
    return;
}

$category = [];
$categoryId = (int)($_GET['c'] ?? null);

if(!empty($_POST['category']) && csrf_verify_request()) {
    $originalCategoryId = (int)($_POST['category']['id'] ?? null);
    $categoryId = news_category_create(
        $_POST['category']['name'] ?? null,
        $_POST['category']['description'] ?? null,
        !empty($_POST['category']['hidden']),
        $originalCategoryId
    );

    audit_log(
        $originalCategoryId === $categoryId
            ? MSZ_AUDIT_NEWS_CATEGORY_EDIT
            : MSZ_AUDIT_NEWS_CATEGORY_CREATE,
        user_session_current('user_id'),
        [$categoryId]
    );
}

if($categoryId > 0) {
    $category = news_category_get($categoryId);
}

echo tpl_render('manage.news.category', compact('category'));
