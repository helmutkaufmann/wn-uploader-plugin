<?php namespace Mercator\Uploader\Updates;

use Db;
use Schema;
use Winter\Storm\Database\Updates\Migration;

/**
 * Moves sub-gallery categories from a comma-separated string (mercator_uploader_forms.categories,
 * parsed on read) to a proper table, so renaming a category updates every file tagged with it
 * instead of leaving already-uploaded files holding a stale, now-orphaned string.
 *
 * Reads/writes go through the Db facade rather than the Eloquent models: this migration must
 * keep working exactly as written even after the models grow the `categories` relation and
 * `category` accessor this same change introduces, which would otherwise shadow the raw columns
 * being migrated here.
 */
class CategoriesTable_008000 extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mercator_uploader_categories')) {
            Schema::create('mercator_uploader_categories', function ($table) {
                $table->increments('id');
                $table->integer('upload_form_id')->unsigned()->index();
                $table->string('name');
                $table->integer('sort_order')->unsigned()->default(0);
                $table->timestamps();
                $table->unique(['upload_form_id', 'name']);
            });
        }

        if (!Schema::hasColumn('mercator_uploader_files', 'category_id')) {
            Schema::table('mercator_uploader_files', function ($table) {
                $table->integer('category_id')->unsigned()->nullable()->index();
            });
        }

        $this->backfill();

        if (Schema::hasColumn('mercator_uploader_forms', 'categories')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->dropColumn('categories');
            });
        }

        if (Schema::hasColumn('mercator_uploader_files', 'category')) {
            // SQLite chokes dropping an indexed column ("no such column: category" while
            // rebuilding the index) unless the index is dropped first. Wrapped in a try/catch
            // since a retry after a partial failure may have already dropped it.
            try {
                Schema::table('mercator_uploader_files', function ($table) {
                    $table->dropIndex('mercator_uploader_files_category_index');
                });
            } catch (\Throwable $e) {
                // Already gone — fine.
            }

            Schema::table('mercator_uploader_files', function ($table) {
                $table->dropColumn('category');
            });
        }
    }

    /**
     * Turn each form's "Morning, Lunch, Church" string into real mercator_uploader_categories
     * rows (preserving order), then repoint every uploaded file's old string category at the
     * matching row's id. A no-op if either legacy column is already gone (e.g. a fresh test
     * database that never had them, or a migration re-run after a partial failure).
     */
    protected function backfill(): void
    {
        if (!Schema::hasColumn('mercator_uploader_forms', 'categories')) {
            return;
        }

        $hasLegacyFileCategory = Schema::hasColumn('mercator_uploader_files', 'category');

        $forms = Db::table('mercator_uploader_forms')->select('id', 'categories')->get();

        foreach ($forms as $form) {
            $names = array_values(array_filter(array_map('trim', explode(',', (string) $form->categories))));
            if (empty($names)) {
                continue;
            }

            $categoryIdsByName = [];
            foreach ($names as $index => $name) {
                $existing = Db::table('mercator_uploader_categories')
                    ->where('upload_form_id', $form->id)
                    ->where('name', $name)
                    ->first();

                $categoryIdsByName[$name] = $existing
                    ? $existing->id
                    : Db::table('mercator_uploader_categories')->insertGetId([
                        'upload_form_id' => $form->id,
                        'name' => $name,
                        'sort_order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            if (!$hasLegacyFileCategory) {
                continue;
            }

            $files = Db::table('mercator_uploader_files')
                ->select('id', 'category')
                ->where('upload_form_id', $form->id)
                ->whereNotNull('category')
                ->get();

            foreach ($files as $file) {
                $name = trim((string) $file->category);
                if ($name !== '' && isset($categoryIdsByName[$name])) {
                    Db::table('mercator_uploader_files')
                        ->where('id', $file->id)
                        ->update(['category_id' => $categoryIdsByName[$name]]);
                }
            }
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('mercator_uploader_forms', 'categories')) {
            Schema::table('mercator_uploader_forms', function ($table) {
                $table->text('categories')->nullable();
            });
        }

        if (!Schema::hasColumn('mercator_uploader_files', 'category')) {
            Schema::table('mercator_uploader_files', function ($table) {
                $table->string('category')->nullable()->index();
            });
        }

        if (Schema::hasColumn('mercator_uploader_files', 'category_id')) {
            // Same SQLite quirk as the 'category' column above: dropping an indexed column
            // fails ("no such column" while rebuilding the index) unless the index is dropped
            // first. Wrapped in a try/catch since a retry after a partial failure may have
            // already dropped it.
            try {
                Schema::table('mercator_uploader_files', function ($table) {
                    $table->dropIndex('mercator_uploader_files_category_id_index');
                });
            } catch (\Throwable $e) {
                // Already gone — fine.
            }

            Schema::table('mercator_uploader_files', function ($table) {
                $table->dropColumn('category_id');
            });
        }

        Schema::dropIfExists('mercator_uploader_categories');
    }
}
