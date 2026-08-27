<?php namespace Mercator\Uploader\Models;

use Model;

/**
 * A sub-gallery category belonging to one UploadForm. Renaming a row here is what makes renaming
 * a category actually propagate to every file already tagged with it — the old design stored the
 * category as a free-text string copied onto each UploadedFile at upload time, so a rename left
 * existing files holding the stale name.
 */
class UploadCategory extends Model
{
    public $table = "mercator_uploader_categories";
    public $timestamps = true;

    public $fillable = [
        "upload_form_id",
        "name",
        "sort_order",
    ];

    public $belongsTo = [
        "form" => [\Mercator\Uploader\Models\UploadForm::class, "key" => "upload_form_id"],
    ];

    public $hasMany = [
        "files" => [\Mercator\Uploader\Models\UploadedFile::class, "key" => "category_id"],
    ];

    /**
     * All categories across every form, as [id => "Form Title: Category Name"], plus a leading
     * "all" sentinel. Fallback for getScopedCategoryOptions() below when the current form can't
     * be determined from the request (e.g. dependsOn refresh not wired up the way expected).
     */
    public static function getAllCategoryOptions(): array
    {
        $options = ["all" => "— All —"];

        static::with("form")
            ->orderBy("upload_form_id")
            ->orderBy("sort_order")
            ->get()
            ->each(function (self $category) use (&$options) {
                $formTitle = $category->form->title ?? "?";
                $options[(string) $category->id] = "{$formTitle}: {$category->name}";
            });

        return $options;
    }

    /**
     * Categories for just the form currently selected in the same block instance's "Upload
     * Form" field, as [id => "Category Name"], plus a leading "all" sentinel — backing the
     * "Restrict to Sub-Categories/Galleries" checkbox list on the uploader/gallery/slideshow
     * .blocks. Paired with `dependsOn: form_id` on that field.
     *
     * Winter's backend Form widget calls a fully-qualified "Class::method" options callback as
     * $method($formWidget, $field) for checkboxlist/dropdown/radio/balloon-selector fields (see
     * Backend\Widgets\Form::getOptionsFromModel) — $formWidget->data holds this repeater item's
     * *current* field values, form_id included, synchronously at render time. That covers both
     * the field's first render and a dependsOn refresh alike, unlike reading the request (which
     * only carries this item's data during the AJAX refresh, not the initial page render).
     *
     * Falls back to scanning the request (needed if $formWidget->data doesn't carry form_id for
     * some reason — e.g. nested repeaters), then to every form's categories, if the form can't
     * be determined.
     */
    public static function getScopedCategoryOptions($formWidget = null, $field = null): array
    {
        $formToken = null;

        if (is_object($formWidget) && isset($formWidget->data)) {
            $data = (array) $formWidget->data;
            if (!empty($data["form_id"]) && is_string($data["form_id"])) {
                $formToken = trim($data["form_id"]);
            }
        }

        if ($formToken === null || $formToken === "") {
            $formToken = static::findFormIdInRequest(request()->all());
        }

        if ($formToken === null || $formToken === "") {
            return static::getAllCategoryOptions();
        }

        $form = UploadForm::where("form_id", $formToken)->first();
        if (!$form) {
            return static::getAllCategoryOptions();
        }

        $options = ["all" => "— All —"];
        static::where("upload_form_id", $form->id)
            ->orderBy("sort_order")
            ->get()
            ->each(function (self $category) use (&$options) {
                $options[(string) $category->id] = $category->name;
            });

        return $options;
    }

    /** Depth-first search for a non-empty "form_id" value anywhere in a (possibly nested) array. */
    protected static function findFormIdInRequest(array $data): ?string
    {
        if (isset($data["form_id"]) && is_string($data["form_id"]) && trim($data["form_id"]) !== "") {
            return trim($data["form_id"]);
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = static::findFormIdInRequest($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
