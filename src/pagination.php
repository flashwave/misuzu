<?php
define('MSZ_PAGINATION_PAGE_START', 1);
define('MSZ_PAGINATION_OFFSET_INVALID', -1);

function pagination_create(int $count, int $range): array {
    $pages = ceil($count / $range);
    return compact('count', 'range', 'pages');
}

function pagination_is_valid_array(array $pagination): bool {
    return !empty($pagination['count']) && !empty($pagination['range']);
}

function pagination_is_valid_offset(int $offset): bool {
    return $offset !== MSZ_PAGINATION_OFFSET_INVALID;
}

// Adds 'page' and 'offset' to the pagination array transparently!!!
function pagination_offset(array &$pagination, ?int $page): int {
    if(!pagination_is_valid_array($pagination)) {
        return MSZ_PAGINATION_OFFSET_INVALID;
    }

    $page = $page ?? MSZ_PAGINATION_PAGE_START;

    if($page < MSZ_PAGINATION_PAGE_START) {
        return MSZ_PAGINATION_OFFSET_INVALID;
    }

    $offset = $pagination['range'] * ($page - 1);

    if($offset > $pagination['count']) {
        return MSZ_PAGINATION_OFFSET_INVALID;
    }

    $pagination['page'] = $page;
    return $pagination['offset'] = $offset;
}

function pagination_param(string $name = 'p', int $default = 1, ?array $source = null): int {
    if(!isset(($source ?? $_GET)[$name]) || !is_string(($source ?? $_GET)[$name])) {
        return $default;
    }

    return (int)(($source ?? $_GET)[$name] ?? $default);
}
