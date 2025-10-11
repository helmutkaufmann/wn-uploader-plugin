<?php namespace Mercator\Uploader\Components;

use Cms\Classes\ComponentBase;
use Mercator\Uploader\Models\UploadForm;
use Log;

class Uploader extends ComponentBase
{
    public $form;
    public $formId;
    public $userId;

    public function componentDetails()
    {
        return [
            'name'        => 'Uploader',
            'description' => 'Displays a frontend upload form.'
        ];
    }

    public function defineProperties()
    {
        return [
            'formId' => [
                'title'             => 'Form ID',
                'description'       => 'The upload form token (form_id).',
                'type'              => 'string',
                'validationPattern' => '^[A-Za-z0-9]+$',
                'validationMessage' => 'Form ID must be alphanumeric.',
            ],
            'userId' => [
                'title'       => 'User ID (optional)',
                'description' => 'Optional user token to link uploads to a specific user.',
                'type'        => 'string',
                'default'     => ''
            ]
        ];
    }

    public function onRun()
    {
        $this->formId = $this->property('formId');
        $this->userId = $this->property('userId') ?: null;
        $this->form   = $this->loadForm($this->formId);

        // expose to Twig
        $this->page['form']   = $this->form;
        $this->page['formId'] = $this->formId;
        $this->page['userId'] = $this->userId;
    }

    protected function loadForm(string $formId)
    {
        if (!$formId) {
            Log::warning('Uploader: missing formId.');
            return null;
        }

        $form = UploadForm::where('form_id', $formId)->first();

        if (!$form) {
            Log::warning("Uploader: no form found for ID {$formId}");
        } else {
            Log::info("Uploader loaded form {$formId}");
        }

        return $form;
    }
}
