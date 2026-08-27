<?php namespace Mercator\Uploader\Updates;

use Schema;
use Winter\Storm\Database\Updates\Migration;
use Mercator\Uploader\Models\UploadForm;

class AddOwnerTokenToForms_007000 extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('mercator_uploader_forms', 'owner_token')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->string('owner_token', 32)->nullable()->unique();
            });
        }

        // Backfill existing forms so their moderation link works immediately.
        UploadForm::where(function ($q) {
            $q->whereNull('owner_token')->orWhere('owner_token', '');
        })
            ->get()
            ->each(function (UploadForm $form) {
                $form->timestamps = false;
                $form->owner_token = bin2hex(random_bytes(12));
                $form->save();
            });
    }

    public function down()
    {
        if (Schema::hasColumn('mercator_uploader_forms', 'owner_token')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->dropColumn('owner_token');
            });
        }
    }
}
