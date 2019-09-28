<?php
function forum_poll_get(int $poll): array {
    if($poll < 1) {
        return [];
    }

    $getPoll = \Misuzu\DB::prepare("
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
    $getPoll->bind('poll', $poll);
    return $getPoll->fetch();
}

function forum_poll_create(int $maxVotes = 1): int {
    if($maxVotes < 1) {
        return -1;
    }

    $createPoll = \Misuzu\DB::prepare("
        INSERT INTO `msz_forum_polls`
            (`poll_max_votes`)
        VALUES
            (:max_votes)
    ");
    $createPoll->bind('max_votes', $maxVotes);
    return $createPoll->execute() ? \Misuzu\DB::lastId() : -1;
}

function forum_poll_get_options(int $poll): array {
    if($poll < 1) {
        return [];
    }

    static $polls = [];

    if(array_key_exists($poll, $polls)) {
        return $polls[$poll];
    }

    $getOptions = \Misuzu\DB::prepare('
        SELECT `option_id`, `option_text`,
            (
                SELECT COUNT(*)
                FROM `msz_forum_polls_answers`
                WHERE `option_id` = fpo.`option_id`
            ) AS `option_votes`
        FROM `msz_forum_polls_options` AS fpo
        WHERE `poll_id` = :poll
    ');
    $getOptions->bind('poll', $poll);

    return $polls[$poll] = $getOptions->fetchAll();
}

function forum_poll_get_user_answers(int $poll, int $user): array {
    if($poll < 1 || $user < 1) {
        return [];
    }

    $getAnswers = \Misuzu\DB::prepare("
        SELECT `option_id`
        FROM `msz_forum_polls_answers`
        WHERE `poll_id` = :poll
        AND `user_id` = :user
    ");
    $getAnswers->bind('poll', $poll);
    $getAnswers->bind('user', $user);
    return array_column($getAnswers->fetchAll(), 'option_id');
}

function forum_poll_reset_answers(int $poll): void {
    if($poll < 1) {
        return;
    }

    $resetAnswers = \Misuzu\DB::prepare("
        DELETE FROM `msz_forum_polls_answers`
        WHERE `poll_id` = :poll
    ");
    $resetAnswers->bind('poll', $poll);
    $resetAnswers->execute();
}

function forum_poll_option_add(int $poll, string $text): int {
    if($poll < 1 || empty($text) || strlen($text) > 0xFF) {
        return -1;
    }

    $addOption = \Misuzu\DB::prepare("
        INSERT INTO `msz_forum_polls_options`
            (`poll_id`, `option_text`)
        VALUES
            (:poll, :text)
    ");
    $addOption->bind('poll', $poll);
    $addOption->bind('text', $text);
    return $addOption->execute() ? \Misuzu\DB::lastId() : -1;
}

function forum_poll_option_remove(int $option): void {
    if($option < 1) {
        return;
    }

    $removeOption = \Misuzu\DB::prepare("
        DELETE FROM `msz_forum_polls_options`
        WHERE `option_id` = :option
    ");
    $removeOption->bind('option', $option);
    $removeOption->execute();
}

function forum_poll_vote_remove(int $user, int $poll): void {
    if($user < 1 || $poll < 1) {
        return;
    }

    $purgeVote = \Misuzu\DB::prepare("
        DELETE FROM `msz_forum_polls_answers`
        WHERE `user_id` = :user
        AND `poll_id` = :poll
    ");
    $purgeVote->bind('user', $user);
    $purgeVote->bind('poll', $poll);
    $purgeVote->execute();
}

function forum_poll_vote_cast(int $user, int $poll, int $option): void {
    if($user < 1 || $poll < 1 || $option < 1) {
        return;
    }

    $castVote = \Misuzu\DB::prepare("
        INSERT INTO `msz_forum_polls_answers`
            (`user_id`, `poll_id`, `option_id`)
        VALUES
            (:user, :poll, :option)
    ");
    $castVote->bind('user', $user);
    $castVote->bind('poll', $poll);
    $castVote->bind('option', $option);
    $castVote->execute();
}

function forum_poll_validate_option(int $poll, int $option): bool {
    if($poll < 1 || $option < 1) {
        return false;
    }

    $checkVote = \Misuzu\DB::prepare("
        SELECT COUNT(`option_id`) > 0
        FROM `msz_forum_polls_options`
        WHERE `poll_id` = :poll
        AND `option_id` = :option
    ");
    $checkVote->bind('poll', $poll);
    $checkVote->bind('option', $option);

    return (bool)$checkVote->fetchColumn();
}

function forum_poll_has_voted(int $user, int $poll): bool {
    if($user < 1 || $poll < 1) {
        return false;
    }

    $getAnswers = \Misuzu\DB::prepare("
        SELECT COUNT(`user_id`) > 0
        FROM `msz_forum_polls_answers`
        WHERE `poll_id` = :poll
        AND `user_id` = :user
    ");
    $getAnswers->bind('poll', $poll);
    $getAnswers->bind('user', $user);

    return (bool)$getAnswers->fetchColumn();
}

function forum_poll_get_topic(int $poll): array {
    if($poll < 1) {
        return [];
    }

    $getTopic = \Misuzu\DB::prepare("
        SELECT `forum_id`, `topic_id`, `topic_locked`
        FROM `msz_forum_topics`
        WHERE `poll_id` = :poll
    ");
    $getTopic->bind('poll', $poll);

    return $getTopic->fetch();
}
