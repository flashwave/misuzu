<?php
define('MSZ_GIT_FORMAT_HASH_DATE_TIME', '%cd');
define('MSZ_GIT_FORMAT_HASH_SHORT', '%h');
define('MSZ_GIT_FORMAT_HASH_LONG', '%H');

function git_commit_info(string $format, string $args = ''): string
{
    return trim(shell_exec(sprintf("git log --pretty=\"%s\" {$args} -n1 HEAD", $format)));
}

function git_commit_hash(bool $long = false): string
{
    return git_commit_info($long ? MSZ_GIT_FORMAT_HASH_LONG : MSZ_GIT_FORMAT_HASH_SHORT);
}

function git_tag(): string
{
    return trim(shell_exec('git describe --abbrev=0 --tags'));
}
