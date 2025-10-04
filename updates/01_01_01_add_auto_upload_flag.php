<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddAutoUploadFlag extends Migration
{
    public function up()
    {
        Schema::table('mercator_uploader_forms', function ($table) {
            $table->boolean('auto_upload_enabled')->default(true);
        });
    }

    public function down()
    {
        Schema::table('mercator_uploader_forms', function ($table) {
            $table->dropColumn('auto_upload_enabled');
        });
    }
}

