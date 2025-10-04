<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CreateUploadFormsTable extends Migration
{
    public function up()
    {
        Schema::create('mercator_uploader_forms', function($table) {
            $table->increments('id');
            $table->string('form_id', 16)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('upload_dir')->default('uploader');
            $table->string('allowed_types')->default('jpg,png,pdf');
            $table->dateTime('start_date')->default('2025-01-01 00:00:00');
            $table->dateTime('end_date')->default('2099-12-31 23:59:59');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('mercator_uploader_forms');
    }
}