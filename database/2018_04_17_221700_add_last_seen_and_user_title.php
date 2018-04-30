<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\DatabaseV1;

// phpcs:disable
class AddLastSeenAndUserTitle extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = DatabaseV1::connection()->getSchemaBuilder();
        $schema->table('users', function (Blueprint $table) {
            $table->string('user_title', 64)
                ->nullable()
                ->default(null);

            $table->timestamp('last_seen')
                ->nullable()
                ->default(null);
        });
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function down()
    {
        $schema = DatabaseV1::connection()->getSchemaBuilder();
        $schema->table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_title',
                'last_seen',
            ]);
        });
    }
}
