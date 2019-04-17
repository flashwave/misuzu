<?php
function forum_poll_create(int $maxVotes = 1): int
{
    if ($maxVotes < 1) {
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

function forum_poll_options(int $poll): array
{
    if($poll < 1) {
        return [];
    }

    static $polls = [];

    if(array_key_exists($poll, $polls)) {
        return $polls[$poll];
    }

    $getOptions = db_prepare('
        SELECT `option_id`, `option_text`
        FROM `msz_forum_polls_options`
        WHERE `poll_id` = :poll
    ');
    $getOptions->bindValue('poll', $poll);

    return $polls[$poll] = db_fetch_all($getOptions);
}

function forum_poll_reset_answers(int $poll): void
{
    if ($poll < 1) {
        return;
    }

    $resetAnswers = db_prepare("
        DELETE FROM `msz_forum_polls_answers`
        WHERE `poll_id` = :poll
    ");
    $resetAnswers->bindValue('poll', $poll);
    $resetAnswers->execute();
}

function forum_poll_option_add(int $poll, string $text): int
{
    if ($poll < 1 || empty($text) || strlen($text) > 0xFF) {
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

function forum_poll_option_remove(int $option): void
{
    if ($option < 1) {
        return;
    }

    $removeOption = db_prepare("
        DELETE FROM `msz_forum_polls_options`
        WHERE `option_id` = :option
    ");
    $removeOption->bindValue('option', $option);
    $removeOption->execute();
}
