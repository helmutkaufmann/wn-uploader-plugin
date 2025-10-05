<?php namespace Mercator\Uploader\Controllers;

use Backend\Classes\Controller;
use BackendMenu;
use Flash;
use ApplicationException;
use Mail;
use Log;

class UploadForms extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

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


    public function onSendInvites()
{
    // parent ID of the relation (current form)
    $formId = post('manage_id'); // RelationController posts this
    $form = \Mercator\Uploader\Models\UploadForm::find($formId);
    if (!$form) {
        \Flash::error('Upload Form not found.');
        return;
    }

    $checked = post('checked');
    if (!is_array($checked) || empty($checked)) {
        \Flash::warning('No users selected.');
        return;
    }

    $users = \Mercator\Uploader\Models\UploadUser::where('upload_form_id', $form->id)
        ->whereIn('id', $checked)->get();

    if ($users->isEmpty()) {
        \Flash::warning('No matching users.');
        return;
    }

    $baseUrl = url('/mercator/uploader/myUploader');
    $count = 0;

    foreach ($users as $user) {
        if (!$user->email) {
            continue;
        }
        $inviteUrl = $baseUrl . '?id=' . $form->form_id . '&user=' . $user->token;

        Mail::send('mercator.uploader::mail.invite', [
            'name' => $user->name ?: $user->email,
            'form' => $form,
            'url'  => $inviteUrl,
        ], function ($message) use ($user, $form) {
            $message->to($user->email, $user->name ?: null);
            $message->subject('Upload invitation: ' . $form->title);
        });

        $user->invited_at = now();
        $user->save();
        $count++;
    }

    \Flash::success($count . ' invite(s) sent.');
    // refresh relation list
    return $this->relationRefresh('users');
}

}

