<?php
namespace Misuzu\News;

use Illuminate\Database\Eloquent\SoftDeletes;
use Misuzu\Users\User;
use Misuzu\Model;
use Parsedown;

final class NewsPost extends Model
{
    use SoftDeletes;

    protected $table = 'news_posts';
    protected $primaryKey = 'post_id';

    public function getHtml(): string
    {
        return (new Parsedown)->text($this->post_text);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(NewsCategory::class, 'category_id');
    }
}
