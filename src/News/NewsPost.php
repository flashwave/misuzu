<?php
namespace Misuzu\News;

use Carbon\Carbon;
use Misuzu\Database;
use Misuzu\Users\User;
use Parsedown;

/**
 * Class NewsPost
 * @package Misuzu\News
 */
final class NewsPost
{
    /**
     * Parses post_text and returns the final HTML.
     * @return string
     */
    public function getHtml(): string
    {
        return (new Parsedown)->text($this->post_text);
    }

    public function getUser(): ?User
    {
        if (empty($this->user_id) || $this->user_id < 1) {
            return null;
        }

        return User::find($this->user_id);
    }

    public function getCategory(): ?NewsCategory
    {
        if (empty($this->category_id) || $this->category_id < 1) {
            return null;
        }

        return NewsCategory::find($this->category_id);
    }
}
