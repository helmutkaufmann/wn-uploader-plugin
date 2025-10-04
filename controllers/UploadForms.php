<?php namespace Mercator\Uploader\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use ApplicationException;

class UploadForms extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Mercator.Uploader', 'uploader', 'forms');
    }

    public function onBulkDelete()
    {
        if (!$this->user->hasAccess('mercator.uploader.manage')) {
            throw new ApplicationException('You do not have permission to delete forms.');
        }

        $checkedIds = post('checked');
        if (!is_array($checkedIds) || empty($checkedIds)) {
            Flash::warning('No forms selected.');
            return;
        }

        $count = 0;
        foreach ($checkedIds as $id) {
            if ($model = \Mercator\Uploader\Models\UploadForm::find($id)) {
                $model->delete();
                $count++;
            }
        }

        Flash::success($count . ' form(s) deleted.');
        return $this->listRefresh();
    }
}

