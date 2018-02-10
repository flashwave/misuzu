<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\Database;

// phpcs:disable
class CreateSessionsTable extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->create('sessions', function (Blueprint $table) {
            $table->increments('session_id');

            $table->integer('user_id')
                ->unsigned();

            $table->string('session_key', 255);

            $table->binary('session_ip');

            $table->string('user_agent', 255)
                ->nullable()
                ->default(null);

            $table->timestamp('expires_on')
                ->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn('user_registered');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function down()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->drop('sessions');

        $schema->table('users', function (Blueprint $table) {
            $table->integer('user_registered')
                ->unsigned()
                ->default(0);

            $table->dropSoftDeletes();
            $table->dropTimestamps();
        });
    }
}
