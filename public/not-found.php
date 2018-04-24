<?php
require_once __DIR__ . '/../misuzu.php';

echo $app->getTemplating()->render('errors.404');
