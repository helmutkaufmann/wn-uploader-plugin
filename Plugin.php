<?php namespace Mercator\Uploader;

use System\Classes\PluginBase;
use Illuminate\Support\Facades\Route;
use Mercator\Uploader\Models\UploadForm;
use Mercator\Uploader\Models\UploadUser;

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
    public function pluginDetails()
    {
        return [
            "name" => "Uploader",
            "description" => "Managed file uploads via DB-defined forms + route-based frontend (UIKit + Uppy).",
            "author" => "Helmut Kaufmann, Kuessnacht am Rigi, Switzerland",
            "icon" => "icon-upload",
        ];
    }

    public function register()
    {
        return;
        $this->registerConsoleCommand("uploader.seed", \Mercator\Uploader\Console\SeedUploaderForm::class);
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
                "sideMenu" => [
                    "forms" => [
                        "label" => "Upload Forms",
                        "icon" => "icon-list",
                        "url" => \Backend::url("mercator/uploader/uploadforms"),
                        "permissions" => ["mercator.uploader.manage"],
                    ],
                ],
            ],
        ];
    }

    public function registerMarkupTags()
    {
        return [
            "functions" => [
                // Usage in Twig: {% set form = uploader_form('AbCdEf1234') %}
                "uploaderForm" => function ($formId) {
                    if (!is_string($formId) || $formId === "") {
                        return null;
                    }
                    return \Mercator\Uploader\Models\UploadForm::where("form_id", $formId)->first();
                },
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
                        return $form->users->where("is_active", true)->whereStrict("token", $user)->first() ? true : false;
                    }
                },
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
        Log::info("QRCode registered");
        return [
            "mercator_uploader_qrcode" => '$/mercator/uploader/blocks/qrcode.block',
            'mercator_uploader_qrcode_bootstrap' => '$/mercator/uploader/blocks/qrcode_bootstrap.block',
            "mercator_uploader_uploader" => '$/mercator/uploader/blocks/upload.block',
            'mercator_uploader_uploader_bootstrap' => '$/mercator/uploader/blocks/upload_bootstrap.block',
        ];
    }

    // Frontend route handling all forms by {id} and {user}
    public function boot()
    {
        Route::group(["middleware" => ["web"]], function () {
            Route::get("/mercator/uploader/default/{id}/{userToken?}", [
                \Mercator\Uploader\Http\Controllers\FrontendController::class,
                "show",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");
            Route::post("/mercator/uploader/endpoint/{formToken}/{userToken}", [
                \Mercator\Uploader\Http\Controllers\FrontendController::class,
                "upload",
            ])->where("id", "[A-Za-z0-9_-]{10,16}");
        });
    }
}
