<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\Database;

// phpcs:disable
class CreateNewsTables extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = Database::connection()->getSchemaBuilder();

        $schema->create('news_categories', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('category_name');
            $table->text('category_description');
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        $schema->create('news_posts', function (Blueprint $table) {
            $table->increments('post_id');
            $table->integer('category_id')->unsigned();
            $table->boolean('is_featured')->default(false);
            $table->integer('user_id')->unsigned()->nullable();
            $table->string('post_title');
            $table->text('post_text');
            $table->timestamp('scheduled_for')->useCurrent();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')
                ->references('category_id')
                ->on('news_categories')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function down()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->drop('news_posts');
        $schema->drop('news_categories');
    }
}
