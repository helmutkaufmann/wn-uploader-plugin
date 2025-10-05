<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddUploadLimitsAndExifToUploadForms extends Migration
{
    public function up()
    {
        Schema::table('mercator_uploader_forms', function($table) {
            if (!Schema::hasColumn('mercator_uploader_forms', 'preserve_exif')) {
                $table->boolean('preserve_exif')->default(false);
            }
            if (!Schema::hasColumn('mercator_uploader_forms', 'max_file_size')) {
                $table->integer('max_file_size')->nullable();
            }
            if (!Schema::hasColumn('mercator_uploader_forms', 'max_total_file_size')) {
                $table->integer('max_total_file_size')->nullable();
            }
            if (!Schema::hasColumn('mercator_uploader_forms', 'use_image_editor')) {
                $table->boolean('use_image_editor')->default(true);
            }
        });
    }

    public function down()
    {
        Schema::table('mercator_uploader_forms', function($table) {
            $table->dropColumn(['preserve_exif', 'max_file_size', 'max_total_file_size', 'use_image_editor']);
        });
    }
}
