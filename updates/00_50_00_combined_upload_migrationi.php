<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class CombinedUploadMigration_010100 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mercator_uploader_forms')) {
            Schema::create('mercator_uploader_forms', function ($table) {
                $table->increments('id');
                $table->string('form_id', 16)->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('upload_dir')->default('uploader');
                $table->string('allowed_types')->default('jpg,png,pdf');
                $table->dateTime('start_date')->default('2025-01-01 00:00:00');
                $table->dateTime('end_date')->default('2099-12-31 23:59:59');
                $table->string('timezone')->default('UTC');

                // Client-side resize
                $table->boolean('client_resize_enabled')->default(false);
                $table->integer('client_resize_max_width')->nullable();   // e.g. 1920
                $table->integer('client_resize_max_height')->nullable();  // e.g. 1080
                $table->decimal('client_resize_quality', 3, 2)->nullable(); // 0.00–1.00 (e.g. 0.80)

                // Upload behavior flags and limits
                $table->boolean('auto_upload_enabled')->default(true);
                $table->boolean('preserve_exif')->default(false);
                $table->integer('max_file_size')->nullable();
                $table->integer('max_total_file_size')->nullable();
                $table->boolean('use_image_editor')->default(true);

                // Access control
                $table->boolean('restricted')->default(false);

                $table->timestamps();
            });
        } else {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->string('form_id', 16)->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('upload_dir')->default('uploader');
                $table->string('allowed_types')->default('jpg,png,pdf');
                $table->dateTime('start_date')->default('2025-01-01 00:00:00');
                $table->dateTime('end_date')->default('2099-12-31 23:59:59');
                $table->string('timezone')->default('UTC');
                $table->boolean('client_resize_enabled')->default(false);
                $table->integer('client_resize_max_width')->nullable();
                $table->integer('client_resize_max_height')->nullable();
                $table->decimal('client_resize_quality', 3, 2)->nullable();
                $table->boolean('auto_upload_enabled')->default(true);
                $table->boolean('preserve_exif')->default(false);
                $table->integer('max_file_size')->nullable();
                $table->integer('max_total_file_size')->nullable();
                $table->boolean('use_image_editor')->default(true);
                $table->boolean('restricted')->default(false);
            });
        }

        // Final schema for mercator_uploader_users (from 01_02_00)
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
    }

    public function down()
    {
        Schema::dropIfExists('mercator_uploader_users');
        Schema::dropIfExists('mercator_uploader_forms');
    }
}

