<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;

class QrCardLayout_009000 extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('mercator_uploader_forms', 'qr_card_layout')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->string('qr_card_layout', 20)->default('classic');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('mercator_uploader_forms', 'qr_card_layout')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->dropColumn('qr_card_layout');
            });
        }
    }
}
