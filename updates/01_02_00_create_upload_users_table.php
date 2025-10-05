<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateUploadUsersTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mercator_uploader_users')) {
            Schema::create('mercator_uploader_users', function ($table) {
                $table->increments('id');
                $table->integer('upload_form_id')->unsigned()->index();
                $table->string('token', 32)->unique();
                $table->string('name')->nullable();
                $table->string('email')->index();
                $table->boolean('is_active')->default(true);
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('last_accessed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('mercator_uploader_forms', 'restricted')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->boolean('restricted')->default(false);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('mercator_uploader_users');

        if (Schema::hasColumn('mercator_uploader_forms', 'restricted')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->dropColumn('restricted');
            });
        }
    }
}
