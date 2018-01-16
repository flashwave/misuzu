<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\Database;

// phpcs:disable
class InitialUsersTable extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->create('users', function (Blueprint $table) {
            $table->increments('user_id');

            $table->string('username', 255)
                ->unique();

            $table->string('password', 255)
                ->nullable()
                ->default(null);

            $table->string('email', 255)
                ->unique();

            $table->binary('register_ip');
            $table->binary('last_ip');

            $table->char('user_country', 2)
                ->default('XX');

            $table->integer('user_registered')
                ->unsigned()
                ->default(0);

            $table->string('user_chat_key', 32)
                ->nullable()
                ->default(null);
        });
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function down()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->drop('users');
    }
}
