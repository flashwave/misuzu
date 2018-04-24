<?php
namespace Misuzu\News;

use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Users\User;
use Misuzu\Model;
use Parsedown;

/**
 * Class NewsPost
 * @package Misuzu\News
 * @property-read int $post_id
 * @property int $category_id
 * @property bool $is_featured
 * @property int $user_id
 * @property string $post_title
 * @property string $post_text
 * @property Carbon $scheduled_for
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read NewsCategory $category
 */
final class NewsPost extends Model
{
    use SoftDeletes;

    protected $table = 'news_posts';
    protected $primaryKey = 'post_id';
    protected $dates = ['scheduled_for'];

    /**
     * Parses post_text and returns the final HTML.
     * @return string
     */
    public function getHtml(): string
    {
        return (new Parsedown)->text($this->post_text);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }
}
