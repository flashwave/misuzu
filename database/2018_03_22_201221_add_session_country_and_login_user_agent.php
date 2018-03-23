<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\Database;

// phpcs:disable
class AddSessionCountryAndLoginUserAgent extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->table('sessions', function (Blueprint $table) {
            $table->char('session_country', 2)
                ->default('XX');
        });
        $schema->table('login_attempts', function (Blueprint $table) {
            $table->string('user_agent', 255)
                ->default('');
        });
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function down()
    {
        $schema = Database::connection()->getSchemaBuilder();
        $schema->table('sessions', function (Blueprint $table) {
            $table->dropColumn('session_country');
        });
        $schema->table('login_attempts', function (Blueprint $table) {
            $table->dropColumn('user_agent');
        });
    }
}
