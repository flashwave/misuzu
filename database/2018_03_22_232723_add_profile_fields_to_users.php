<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\Database;

// phpcs:disable
class AddProfileFieldsToUsers extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->table('users', function (Blueprint $table) {
            $table->string('user_website', 255)
                ->default('');

            $table->string('user_twitter', 20)
                ->default('');

            $table->string('user_github', 40)
                ->default('');

            $table->string('user_skype', 60)
                ->default('');

            $table->string('user_discord', 40)
                ->default('');

            $table->string('user_youtube', 255)
                ->default('');

            $table->string('user_steam', 255)
                ->default('');

            $table->string('user_twitchtv', 30)
                ->default('');

            $table->string('user_osu', 20)
                ->default('');

            $table->string('user_lastfm', 20)
                ->default('');
        });
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function down()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_website',
                'user_twitter',
                'user_github',
                'user_skype',
                'user_discord',
                'user_youtube',
                'user_steam',
                'user_twitchtv',
                'user_osu',
                'user_lastfm',
            ]);
        });
    }
}
