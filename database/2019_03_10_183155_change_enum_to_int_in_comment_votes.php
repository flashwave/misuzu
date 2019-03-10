<?php
namespace Misuzu\DatabaseMigrations\ChangeEnumToIntInCommentVotes;

use PDO;

function migrate_up(PDO $conn): void
{
    // Step 1, add new column
    $conn->exec("
        ALTER TABLE `msz_comments_votes`
            ADD COLUMN `comment_vote` TINYINT NOT NULL DEFAULT '0' AFTER `user_id`,
            CHANGE COLUMN `comment_vote` `comment_vote_old` ENUM('Like','Dislike') NULL DEFAULT NULL COLLATE 'utf8mb4_bin' AFTER `comment_vote`,
            ADD INDEX `comments_vote_old` (`comment_vote_old`);
    ");

    // Step 2, migrate old values
    $conn->exec(sprintf(
        '
            UPDATE `msz_comments_votes`
            SET `comment_vote` = %d
            WHERE `comment_vote_old` = "Like"
        ',
        MSZ_COMMENTS_VOTE_LIKE
    ));
    $conn->exec(sprintf(
        '
            UPDATE `msz_comments_votes`
            SET `comment_vote` = %d
            WHERE `comment_vote_old` = "Dislike"
        ',
        MSZ_COMMENTS_VOTE_DISLIKE
    ));

    // Step 3, nuke the old column
    $conn->exec('
        ALTER TABLE `msz_comments_votes`
            DROP COLUMN `comment_vote_old`;
    ');
}

function migrate_down(PDO $conn): void
{
    // this one only goes one way!
    $conn->exec("TRUNCATE `msz_comments_votes`");
    $conn->exec("
        ALTER TABLE `msz_comments_votes`
            CHANGE COLUMN `comment_vote` `comment_vote` ENUM('Like','Dislike') NULL DEFAULT NULL COLLATE 'utf8mb4_bin' AFTER `user_id`;
    ");
}
