<?php
namespace Misuzu\News;

use Misuzu\Model;

final class NewsCategory extends Model
{
    protected $table = 'news_categories';
    protected $primaryKey = 'category_id';

    public function posts()
    {
        return $this->hasMany(NewsPost::class, 'category_id');
    }
}
