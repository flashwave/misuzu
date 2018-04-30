<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Misuzu\DatabaseV1;

// phpcs:disable
class CreateRolesTables extends Migration
{
    /**
     * @SuppressWarnings(PHPMD)
     */
    public function up()
    {
        $schema = DatabaseV1::connection()->getSchemaBuilder();
        $schema->create('roles', function (Blueprint $table) {
            $table->increments('role_id');

            $table->integer('role_hierarchy')
                ->default(1);

            $table->string('role_name', 255);

            $table->string('role_title', 64)
                ->nullable();

            $table->text('role_description')
                ->nullable();

            $table->boolean('role_secret')
                ->default(false);

            $table->integer('role_colour')
                ->nullable()
                ->default(null);

            $table->timestamps();
        });

        $schema->create('user_roles', function (Blueprint $table) {
            $table->integer('user_id')
                ->unsigned();

            $table->integer('role_id')
                ->unsigned();

            $table->primary(['user_id', 'role_id']);

            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('role_id')
                ->on('roles')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });

        $schema->table('users', function (Blueprint $table) {
            $table->integer('display_role')
                ->unsigned()
                ->nullable()
                ->default(null);

            $table->foreign('display_role')
                ->references('role_id')
                ->on('roles')
                ->onUpdate('cascade')
                ->onDelete('set null');
        });
    }

    /**
     * @SuppressWarnings(PHPMD)
     */
    public function down()
    {
        $schema = DatabaseV1::connection()->getSchemaBuilder();

        $schema->table('users', function (Blueprint $table) {
            $table->dropForeign(['display_role']);
            $table->dropColumn('display_role');
        });

        $schema->drop('user_roles');
        $schema->drop('roles');
    }
}
