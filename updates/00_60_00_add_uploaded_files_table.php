<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddUploadedFilesTable_006000 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mercator_uploader_files')) {
            Schema::create('mercator_uploader_files', function ($table) {
                $table->increments('id');
                $table->integer('upload_form_id')->unsigned()->index();
                $table->integer('upload_user_id')->unsigned()->nullable()->index();
                $table->string('file_token', 24)->unique();
                $table->string('disk', 20)->default('local');
                $table->string('path');
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('category')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('mercator_uploader_forms', 'categories')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->text('categories')->nullable();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('mercator_uploader_files');

        if (Schema::hasColumn('mercator_uploader_forms', 'categories')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->dropColumn('categories');
            });
        }
    }
}
