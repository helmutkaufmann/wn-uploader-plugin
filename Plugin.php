<?php namespace Mercator\Uploader;

use System\Classes\PluginBase;
use Illuminate\Support\Facades\Route;
use Mercator\Uploader\Models\UploadForm;
use Mercator\Uploader\Models\UploadUser;
use Carbon\Carbon; // <-- ADDED THIS LINE
use Log;

class Plugin extends PluginBase
{
    /**
     * @var array Plugin dependencies
     */
    public $require = ["Winter.Blocks"];

    public function pluginDetails()
    {
        return [
            "name" => "Uploader",
            "description" =>
                "Managed file uploads via databse-defined forms and route-based frontend (UIKit + Uppy). Includes a CMS component without styling. Note: When using .blocks, you can omly have ONE uploader per page for the time being.",
            "author" => "Helmut Kaufmann, Kuessnacht am Rigi, Switzerland",
            "icon" => "icon-upload",
        ];
    }

    public function registerPermissions()
    {
        return [
            "mercator.uploader.manage" => [
                "tab" => "Uploaders",
                "label" => "Manage upload forms",
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            "uploader" => [
                "label" => "Uploaders",
                "url" => \Backend::url("mercator/uploader/uploadforms"),
                "icon" => "icon-upload",
                "permissions" => ["mercator.uploader.manage"],
                "order" => 500,
            ],
        ];
    }

    public function registerMarkupTags()
    {
        return [
            "functions" => [
                // Obtain a handle to the backend upload form with identifer AbCdEf1234.
                // Usage in Twig: {% set form = uploader_form('AbCdEf1234') %}
                // Then use, e.g., title and description, such as form.title or form.description
                "uploaderForm" => function ($formId) {
                    if (!is_string($formId) || $formId === "") {
                        return null;
                    }
                    return \Mercator\Uploader\Models\UploadForm::where("form_id", $formId)->first();
                },
                // Check if an upload form has access restrictions on it
                // Or, when passing a uiser ID, check if that user has access to an upload form.
                // Usage in Twig: {% if uploaderUserIsPermissioned("FORMID") %} or {% if uploaderUserIsPermissioned(i, "FORMID", "USERID") %}
                "uploaderUserIsPermissioned" => function ($id, $user = "") {
                    /// Check if form exsists, loads it's users
                    $form = UploadForm::where("form_id", $id)->with("users")->first();

                    if (!$form) {
                        return false;
                    } // form does not exist

                    if (!$form->restricted) {
                        return true;
                    } else {
                        // Search the form users collection for one matching the given credentials
                        return $form->users->where("is_active", true)->whereStrict("token", $user)->first()
                            ? true
                            : false;
                    }
                },

                // --- ADDED THIS FUNCTION REGISTRATION ---
                // Checks the form's upload time window status.
                // Returns: 0 = OK, 1 = Too Early, 2 = Too Late, 
                "uploaderUploaderOpen" => function ($id, $user=null): int {
                    
                    $form = UploadForm::where("form_id", $id)->with("users")->first();

                    // Ensure we have a valid form object
                    if (!is_object($form)) {
                        return -1; // Form does not exist
                    }
                    
                    if ($form->restricted) {
                        if (! $form->users->where("is_active", true)->whereStrict("token", $user)->first())
                            return -2; // User is not authorized
                    }

                    $now = Carbon::now();

                    // If 'start_date' is in the future, it's not time yet.
                    $validFrom = Carbon::parse($form->start_date);
                    if ($now->isBefore($validFrom)) {
                        return 1; // 1 = Too Early
                    }

                    // If 'end_date' exists is in the past, time has expired.
                    $validUntil = Carbon::parse($form->end_date);
                    if ($now->isAfter($validUntil)) {
                        return 2; // 2 = Too Late
                    }

                    // f neither check failed, we are within the time window
                    return 0; // 0 = OK
                },

                // Files uploaded to a form, newest first. Optionally filter by category (sub-gallery).
                "uploaderFiles" => function ($formId, $category = null, $limit = 500) {
                    $form = UploadForm::where("form_id", $formId)->first();
                    if (!$form) {
                        return collect();
                    }

                    $query = \Mercator\Uploader\Models\UploadedFile::where("upload_form_id", $form->id)->orderBy(
                        "id",
                        "desc"
                    );

                    if ($category) {
                        // -1 never matches a real id: an unknown category name should return no
                        // files, not silently ignore the filter and return everything.
                        $categoryId = \Mercator\Uploader\Models\UploadCategory::where("upload_form_id", $form->id)
                            ->where("name", $category)
                            ->value("id");
                        $query->where("category_id", $categoryId ?? -1);
                    }

                    return $query->limit($limit)->get();
                },
                "uploaderMediaUrl" => function ($formId, $fileToken, $userToken = null) {
                    return url("/mercator/uploader/media/" . $formId . "/" . $fileToken . "/" . ($userToken ?: "NONE"));
                },
                "uploaderThumbUrl" => function ($formId, $fileToken, $userToken = null) {
                    return url("/mercator/uploader/thumb/" . $formId . "/" . $fileToken . "/" . ($userToken ?: "NONE"));
                },
                "uploaderDownloadUrl" => function ($formId, $userToken = null) {
                    return url("/mercator/uploader/download/" . $formId . "/" . ($userToken ?: "NONE"));
                },
                "uploaderSlideshowFeedUrl" => function ($formId, $userToken = null) {
                    return url("/mercator/uploader/slideshow-feed/" . $formId . "/" . ($userToken ?: "NONE"));
                },
                "uploaderModerateDeleteUrl" => function ($formId, $ownerToken, $fileToken) {
                    return url("/mercator/uploader/moderate/" . $formId . "/" . $ownerToken . "/file/" . $fileToken);
                },
                "uploaderModerateCategoryUrl" => function ($formId, $ownerToken, $fileToken) {
                    return url(
                        "/mercator/uploader/moderate/" . $formId . "/" . $ownerToken . "/file/" . $fileToken . "/category"
                    );
                },

                // Resolves a "Restrict to Sub-Categories/Galleries" block field's selected keys
                // (from UploadCategory::getAllCategoryOptions/getScopedCategoryOptions — category
                // ids plus the "all" sentinel) down to the category names that actually belong to
                // $formId. Returns null for "no restriction", so callers can pass the result
                // straight through as an allowedCategories param.
                //
                // "all" is the checkbox list's default, and checkboxes are independent — checking
                // specific categories doesn't uncheck "all", so a saved value of ["all", "1", "2"]
                // is the common case, not an edge case. Only treat it as unrestricted when "all"
                // is the *sole* selection; any specific picks alongside it take precedence.
                "uploaderRestrictedCategoryNames" => function ($formId, $selectedKeys) {
                    $selectedKeys = is_array($selectedKeys) ? $selectedKeys : [];
                    $specificKeys = array_values(array_filter($selectedKeys, fn($key) => $key !== "all"));

                    if (empty($specificKeys)) {
                        return null;
                    }
                    $selectedKeys = $specificKeys;

                    $form = UploadForm::where("form_id", $formId)->first();
                    if (!$form) {
                        return [];
                    }

                    $ids = array_values(array_filter(array_map(
                        fn($key) => is_numeric($key) ? (int) $key : null,
                        $selectedKeys
                    )));
                    if (empty($ids)) {
                        return [];
                    }

                    return \Mercator\Uploader\Models\UploadCategory::where("upload_form_id", $form->id)
                        ->whereIn("id", $ids)
                        ->orderBy("sort_order")
                        ->pluck("name")
                        ->all();
                },

                // Create a QR Code (as an inline image data URI) from a string ($data).
                // See blocks/qrcode.block for an example. Shared with the backend "Print QR
                // Card" form partial via UploadForm::generateQrDataUri().
                "uploaderQRCode" => function ($data, $size = 300, $margin = 6) {
                    return UploadForm::generateQrDataUri($data, $size, $margin);
                },
            ],
        ];
    }

    // Register WinterCMS .blocks
    public function registerBlocks()
    {
        return [
            "mercator_uploader_qrcode" => '$/mercator/uploader/blocks/qrcode.block',
            // "mercator_uploader_qrcode_bootstrap" => '$/mercator/uploader/blocks/qrcode_bootstrap.block',
            "mercator_uploader_uploader" => '$/mercator/uploader/blocks/upload.block',
            // "mercator_uploader_uploader_bootstrap" => '$/mercator/uploader/blocks/upload_bootstrap.block',
            "mercator_uploader_gallery" => '$/mercator/uploader/blocks/gallery.block',
            "mercator_uploader_slideshow" => '$/mercator/uploader/blocks/slideshow.block',
        ];
    }

    // Frontend route handling all forms by {id} and {user}
    public function boot()
    {
        Route::group(["middleware" => ["web"]], function () {
            Route::get("/mercator/uploader/default", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "showDefault",
            ]);
            Route::get("/mercator/uploader/default/{id}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "show",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");
            Route::post("/mercator/uploader/endpoint/{formToken}/{userToken}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "upload",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::get("/mercator/uploader/gallery/{id}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "gallery",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::get("/mercator/uploader/slideshow/{id}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "slideshow",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::get("/mercator/uploader/slideshow-feed/{id}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "slideshowFeed",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::get("/mercator/uploader/media/{id}/{fileToken}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "media",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::get("/mercator/uploader/thumb/{id}/{fileToken}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "thumb",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::get("/mercator/uploader/download/{id}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "download",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::get("/mercator/uploader/moderate/{id}/{ownerToken}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "moderate",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::delete("/mercator/uploader/moderate/{id}/{ownerToken}/file/{fileToken}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "deleteFile",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");

            Route::post("/mercator/uploader/moderate/{id}/{ownerToken}/file/{fileToken}/category", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "updateFileCategory",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");
        });
    }

    public function registerComponents()
    {
        return [
            \Mercator\Uploader\Components\Uploader::class => "uploader",
        ];
    }
}