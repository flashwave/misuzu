<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\Database;

// phpcs:disable
class CreateLoginAttemptsTable extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->create('login_attempts', function (Blueprint $table) {
            $table->increments('attempt_id');

            $table->boolean('was_successful');

            $table->binary('attempt_ip');

            $table->char('attempt_country', 2)
                ->default('XX');

            $table->integer('user_id')
                ->nullable()
                ->default(null)
                ->unsigned();

            $table->timestamps();

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
        $schema->drop('login_attempts');
    }
}
