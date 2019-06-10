<?php
function forum_poll_get(int $poll): array {
    if($poll < 1) {
        return [];
    }

    $getPoll = db_prepare("
        SELECT fp.`poll_id`, fp.`poll_max_votes`, fp.`poll_expires`, fp.`poll_preview_results`, fp.`poll_change_vote`,
            (fp.`poll_expires` < CURRENT_TIMESTAMP) AS `poll_expired`,
            (
                SELECT COUNT(*)
                FROM `msz_forum_polls_answers`
                WHERE `poll_id` = fp.`poll_id`
            ) AS `poll_votes`
        FROM `msz_forum_polls` AS fp
        WHERE fp.`poll_id` = :poll
    ");
    $getPoll->bindValue('poll', $poll);
    return db_fetch($getPoll);
}

function forum_poll_create(int $maxVotes = 1): int {
    if($maxVotes < 1) {
        return -1;
    }

    $createPoll = db_prepare("
        INSERT INTO `msz_forum_polls`
            (`poll_max_votes`)
        VALUES
            (:max_votes)
    ");
    $createPoll->bindValue('max_votes', $maxVotes);
    return (int)($createPoll->execute() ? db_last_insert_id() : -1);
}

function forum_poll_get_options(int $poll): array {
    if($poll < 1) {
        return [];
    }

    static $polls = [];

    if(array_key_exists($poll, $polls)) {
        return $polls[$poll];
    }

    $getOptions = db_prepare('
        SELECT `option_id`, `option_text`,
            (
                SELECT COUNT(*)
                FROM `msz_forum_polls_answers`
                WHERE `option_id` = fpo.`option_id`
            ) AS `option_votes`
        FROM `msz_forum_polls_options` AS fpo
        WHERE `poll_id` = :poll
    ');
    $getOptions->bindValue('poll', $poll);

    return $polls[$poll] = db_fetch_all($getOptions);
}

function forum_poll_get_user_answers(int $poll, int $user): array {
    if($poll < 1 || $user < 1) {
        return [];
    }

    $getAnswers = db_prepare("
        SELECT `option_id`
        FROM `msz_forum_polls_answers`
        WHERE `poll_id` = :poll
        AND `user_id` = :user
    ");
    $getAnswers->bindValue('poll', $poll);
    $getAnswers->bindValue('user', $user);
    return array_column(db_fetch_all($getAnswers), 'option_id');
}

function forum_poll_reset_answers(int $poll): void {
    if($poll < 1) {
        return;
    }

    $resetAnswers = db_prepare("
        DELETE FROM `msz_forum_polls_answers`
        WHERE `poll_id` = :poll
    ");
    $resetAnswers->bindValue('poll', $poll);
    $resetAnswers->execute();
}

function forum_poll_option_add(int $poll, string $text): int {
    if($poll < 1 || empty($text) || strlen($text) > 0xFF) {
        return -1;
    }

    $addOption = db_prepare("
        INSERT INTO `msz_forum_polls_options`
            (`poll_id`, `option_text`)
        VALUES
            (:poll, :text)
    ");
    $addOption->bindValue('poll', $poll);
    $addOption->bindValue('text', $text);
    return (int)($createPoll->execute() ? db_last_insert_id() : -1);
}

function forum_poll_option_remove(int $option): void {
    if($option < 1) {
        return;
    }

    $removeOption = db_prepare("
        DELETE FROM `msz_forum_polls_options`
        WHERE `option_id` = :option
    ");
    $removeOption->bindValue('option', $option);
    $removeOption->execute();
}

function forum_poll_vote_remove(int $user, int $poll): void {
    if($user < 1 || $poll < 1) {
        return;
    }

    $purgeVote = db_prepare("
        DELETE FROM `msz_forum_polls_answers`
        WHERE `user_id` = :user
        AND `poll_id` = :poll
    ");
    $purgeVote->bindValue('user', $user);
    $purgeVote->bindValue('poll', $poll);
    $purgeVote->execute();
}

function forum_poll_vote_cast(int $user, int $poll, int $option): void {
    if($user < 1 || $poll < 1 || $option < 1) {
        return;
    }

    $castVote = db_prepare("
        INSERT INTO `msz_forum_polls_answers`
            (`user_id`, `poll_id`, `option_id`)
        VALUES
            (:user, :poll, :option)
    ");
    $castVote->bindValue('user', $user);
    $castVote->bindValue('poll', $poll);
    $castVote->bindValue('option', $option);
    $castVote->execute();
}

function forum_poll_validate_option(int $poll, int $option): bool {
    if($poll < 1 || $option < 1) {
        return false;
    }

    $checkVote = db_prepare("
        SELECT COUNT(`option_id`) > 0
        FROM `msz_forum_polls_options`
        WHERE `poll_id` = :poll
        AND `option_id` = :option
    ");
    $checkVote->bindValue('poll', $poll);
    $checkVote->bindValue('option', $option);

    return (bool)($checkVote->execute() ? $checkVote->fetchColumn() : false);
}

function forum_poll_has_voted(int $user, int $poll): bool {
    if($user < 1 || $poll < 1) {
        return false;
    }

    $getAnswers = db_prepare("
        SELECT COUNT(`user_id`) > 0
        FROM `msz_forum_polls_answers`
        WHERE `poll_id` = :poll
        AND `user_id` = :user
    ");
    $getAnswers->bindValue('poll', $poll);
    $getAnswers->bindValue('user', $user);

    return (bool)($getAnswers->execute() ? $getAnswers->fetchColumn() : false);
}

function forum_poll_get_topic(int $poll): array {
    if($poll < 1) {
        return [];
    }

    $getTopic = db_prepare("
        SELECT `forum_id`, `topic_id`, `topic_locked`
        FROM `msz_forum_topics`
        WHERE `poll_id` = :poll
    ");
    $getTopic->bindValue('poll', $poll);

    return db_fetch($getTopic);
}
