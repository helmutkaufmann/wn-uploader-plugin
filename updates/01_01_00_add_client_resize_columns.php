<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class AddClientResizeColumns extends Migration
{
    public function up()
    {
        Schema::table('mercator_uploader_forms', function ($table) {
            $table->boolean('client_resize_enabled')->default(false);
            $table->integer('client_resize_max_width')->nullable();   // e.g. 1920
            $table->integer('client_resize_max_height')->nullable();  // e.g. 1080
            $table->decimal('client_resize_quality', 3, 2)->nullable(); // 0.00–1.00 (e.g. 0.80)
        });
    }

    public function down()
    {
        Schema::table('mercator_uploader_forms', function ($table) {
            $table->dropColumn([
                'client_resize_enabled',
                'client_resize_max_width',
                'client_resize_max_height',
                'client_resize_quality',
            ]);
        });
    }
}

