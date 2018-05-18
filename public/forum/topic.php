<?php
use Misuzu\Database;

require_once __DIR__ . '/../../misuzu.php';

echo $app->getTemplating()->render('forum.topic');
