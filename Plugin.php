<?php namespace Mercator\Uploader;

use System\Classes\PluginBase;
use Illuminate\Support\Facades\Route;

class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'Uploader',
            'description' => 'Managed file uploads via DB-defined forms + route-based frontend (UIKit + Uppy).',
            'author'      => 'Mercator',
            'icon'        => 'icon-upload'
        ];
    }

    public function register()
    {
        // Artisan command
        $this->registerConsoleCommand('uploader.seed', \Mercator\Uploader\Console\SeedUploaderForm::class);
    }

    public function registerPermissions()
    {
        return [
            'mercator.uploader.manage' => [
                'tab'   => 'Uploader',
                'label' => 'Manage upload forms'
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            'uploader' => [
                'label'       => 'Uploader',
                'url'         => \Backend::url('mercator/uploader/uploadforms'),
                'icon'        => 'icon-upload',
                'permissions' => ['mercator.uploader.manage'],
                'order'       => 500,
                'sideMenu'    => [
                    'forms' => [
                        'label'       => 'Upload Forms',
                        'icon'        => 'icon-list',
                        'url'         => \Backend::url('mercator/uploader/uploadforms'),
                        'permissions' => ['mercator.uploader.manage'],
                    ],
                ]
            ]
        ];
    }

    public function registerMarkupTags()
    {
        return [
            'functions' => [
                'qrCode' => function ($url) {
                    $data = \Mercator\Uploader\Classes\QrCode::pngData($url);
                    return 'data:image/png;base64,' . base64_encode($data);
                },
                // Usage in Twig: {% set form = uploader_form('AbCdEf1234') %}
                'uploader_form' => function ($formId) {
                    if (!is_string($formId) || $formId === '') {
                        return null;
                    }
                    return \Mercator\Uploader\Models\UploadForm::where('form_id', $formId)->first();
                },
            ]
        ];
    }

    // Register WinterCMS .blocks
    public function registerBlocks()
    {
        return [
            'qrcode' => base_path('plugins/mercator/uploader/blocks/qrcode.block'),
            'aaa_uploader' => base_path('plugins/mercator/uploader/blocks/upload.block'),
        ];
    }

    // One frontend route handling all forms by {id}
    public function boot()
    {
        Route::group(['middleware' => ['web']], function () {
            Route::get('/mercator/uploader/{id}', [\Mercator\Uploader\Http\Controllers\FrontendController::class, 'show'])
                ->where('id', '[A-Za-z0-9_-]{10,16}');
            Route::post('/mercator/uploader/{id}/upload', [\Mercator\Uploader\Http\Controllers\FrontendController::class, 'upload'])
                ->where('id', '[A-Za-z0-9_-]{10,16}');
        });
    }
}
