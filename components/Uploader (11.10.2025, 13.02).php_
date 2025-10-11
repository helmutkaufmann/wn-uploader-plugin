<?php namespace Mercator\Uploader\Components;

use Cms\Classes\ComponentBase;
use Mercator\Uploader\Models\UploadForm;

class Uploader extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Uploader',
            'description' => 'Displays a file upload form by token'
        ];
    }

    public function defineProperties()
    {
        return [
            'formId' => [
                'title' => 'Form ID',
                'description' => 'Uploader form token (form_id)',
                'type' => 'string',
            ],
        ];
    }

    public function onRun()
    {
        $this->page['form'] = $this->loadForm();
    }

    protected function loadForm()
    {
        $id = $this->property('formId');
        return UploadForm::where('form_id', $id)->first();
    }

    public function onRender()
    {
        $formId = $this->property('formId');

        // Instantiate the component manually
        $component = new Uploader($this->controller, []);
        $component->setProperty('formId', $formId);
        $component->onRun();

        // Expose component and its variables to Twig
        $this->page['uploader'] = $component;
        $this->page['form'] = $component->property('formId')
            ? $component->loadForm()
            : null;
    }
}

