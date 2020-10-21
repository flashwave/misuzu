<?php
function forum_topic_views_increment(int $topicId): void {
    if($topicId < 1) {
        return;
    }

    $bumpViews = \Misuzu\DB::prepare('
        UPDATE `msz_forum_topics`
        SET `topic_count_views` = `topic_count_views` + 1
        WHERE `topic_id` = :topic_id
    ');
    $bumpViews->bind('topic_id', $topicId);
    $bumpViews->execute();
}

function forum_topic_mark_read(int $userId, int $topicId, int $forumId): void {
    if($userId < 1) {
        return;
    }

    // previously a TRIGGER was used to achieve this behaviour,
    // but those explode when running on a lot of queries (like forum_mark_read() does)
    // so instead we get to live with this garbage now
    // JUST TO CLARIFY: "this behaviour" refers to forum_topic_views_increment only being executed when the topic is viewed for the first time
    try {
        $markAsRead = \Misuzu\DB::prepare('
            INSERT INTO `msz_forum_topics_track`
                (`user_id`, `topic_id`, `forum_id`, `track_last_read`)
            VALUES
                (:user_id, :topic_id, :forum_id, NOW())
        ');
        $markAsRead->bind('user_id', $userId);
        $markAsRead->bind('topic_id', $topicId);
        $markAsRead->bind('forum_id', $forumId);

        if($markAsRead->execute()) {
            forum_topic_views_increment($topicId);
        }
    } catch(PDOException $ex) {
        if($ex->getCode() != '23000') {
            throw $ex;
        }

        $markAsRead = \Misuzu\DB::prepare('
            UPDATE `msz_forum_topics_track`
            SET `track_last_read` = NOW(),
                `forum_id` = :forum_id
            WHERE `user_id` = :user_id
            AND `topic_id` = :topic_id
        ');
        $markAsRead->bind('user_id', $userId);
        $markAsRead->bind('topic_id', $topicId);
        $markAsRead->bind('forum_id', $forumId);
        $markAsRead->execute();
    }
}
