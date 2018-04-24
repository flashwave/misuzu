<?php
namespace Misuzu\News;

use Misuzu\Model;

/**
 * Class NewsCategory
 * @package Misuzu\News
 * @property-read int $category_id
 * @property string $category_name
 * @property string $category_description
 * @property bool $is_hidden
 * @property-read array $posts
 */
final class NewsCategory extends Model
{
    protected $table = 'news_categories';
    protected $primaryKey = 'category_id';

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function posts()
    {
        return $this->hasMany(NewsPost::class, 'category_id');
    }
}
