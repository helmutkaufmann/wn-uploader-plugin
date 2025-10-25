<?php namespace Mercator\Uploader;

use System\Classes\PluginBase;
use Illuminate\Support\Facades\Route;
use Mercator\Uploader\Models\UploadForm;
use Mercator\Uploader\Models\UploadUser;
use Carbon\Carbon; // <-- ADDED THIS LINE

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
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
                "tab" => "Uploader",
                "label" => "Manage upload forms",
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            "uploader" => [
                "label" => "Uploader",
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

                // Create a QR Code (as an inline image data URI from astring ($data)
                // See blocks/qrcode.block for an example
                "uploaderQRCode" => function ($data, $size = 300, $margin = 6) {
                    $writer = new PngWriter();
                    $qrCode = new QrCode(
                        data: $data,
                        encoding: new Encoding("UTF-8"),
                        errorCorrectionLevel: ErrorCorrectionLevel::Low,
                        size: $size,
                        margin: $margin,
                        roundBlockSizeMode: RoundBlockSizeMode::Margin,
                        foregroundColor: new Color(0, 0, 0),
                        backgroundColor: new Color(255, 255, 255)
                    );
                    $result = $writer->write($qrCode);
                    return $result->getDataUri();
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
        ];
    }

    // Frontend route handling all forms by {id} and {user}
    public function boot()
    {
        Route::group(["middleware" => ["web"]], function () {
            Route::get("/mercator/uploader/default/{id}/{userToken?}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "show",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");
            Route::post("/mercator/uploader/endpoint/{formToken}/{userToken}", [
                \Mercator\Uploader\Controllers\FrontendRoutes::class,
                "upload",
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