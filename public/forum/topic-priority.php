<?php
require_once '../../misuzu.php';

$topicId = !empty($_GET['t']) && is_string($_GET['t']) ? (int)$_GET['t'] : 0;
$bump = !empty($_GET['b']) && is_string($_GET['b']) ? (int)$_GET['b'] : 1;

forum_topic_priority_increase($topicId, user_session_current('user_id', 0), $bump);

header('Location: ' . url('forum-topic', ['topic' => $topicId]));
