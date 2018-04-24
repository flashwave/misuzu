<?php
use Misuzu\News\NewsPost;

require_once __DIR__ . '/../misuzu.php';

$featured_news = NewsPost::where('is_featured', true)->orderBy('created_at', 'desc')->take(3)->get();

echo $app->getTemplating()->render('home.landing', compact('featured_news'));
