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

    public $listConfig = "config_list.yaml";
    public $formConfig = "config_form.yaml";
    public $relationConfig = "config_relation.yaml";

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext("Mercator.Uploader", "uploader", "forms");
    }

    public function onBulkDelete()
    {

        if (!$this->user->hasAccess("mercator.uploader.manage")) {
            throw new ApplicationException("You do not have permission to delete forms.");
        }

        $checkedIds = post("checked");
        if (!is_array($checkedIds) || empty($checkedIds)) {
            Flash::warning("No forms selected.");
            return;
        }

        $keepFiles = post("keepFiles") == "1";

        $count = 0;
        \Mercator\Uploader\Models\UploadedFile::$keepFilesOnDisk = $keepFiles;
        foreach ($checkedIds as $id) {
            if ($model = \Mercator\Uploader\Models\UploadForm::find($id)) {
                $model->delete();
                $count++;
            }
        }
        \Mercator\Uploader\Models\UploadedFile::$keepFilesOnDisk = false;

        Flash::success($count . " form(s) deleted.");
        return $this->listRefresh();
    }

    /**
     * Overrides the default toolbar's delete handler so the "keep files on disk" choice
     * (offered by our custom controllers/uploadforms/_form_update_toolbar.php) can be honored
     * before the form (and its cascading "files" relation) is deleted.
     *
     * Must be named update_onDelete, not onDelete: Controller::runAjaxHandler() checks the
     * page-specific "{action}_{handler}" name (update_onDelete, provided by the FormController
     * behavior) before it ever checks a plain "onDelete" — so a same-named override is the only
     * way to actually intercept this on an update-context page.
     */
    public function update_onDelete($recordId = null)
    {
        if (!$this->user->hasAccess("mercator.uploader.manage")) {
            throw new ApplicationException("You do not have permission to delete forms.");
        }

        \Mercator\Uploader\Models\UploadedFile::$keepFilesOnDisk = post("keepFiles") == "1";
        $result = $this->asExtension("FormController")->update_onDelete($recordId);
        \Mercator\Uploader\Models\UploadedFile::$keepFilesOnDisk = false;

        return $result;
    }

    public function onSendInvites()
    { 
        $checked = post("checked");
        if (!is_array($checked) || empty($checked)) {
            \Flash::warning("No users selected.");
            return;
        }
        
        $users = \Mercator\Uploader\Models\UploadUser::where("id", $checked)
            ->get();

        if ($users->isEmpty()) {
            \Flash::warning("No matching users.");
            return;
        }

        // $baseUrl = url("/mercator/uploader/default");
        $baseUrl = url("/mercator/uploader/default");
        $count = 0;
        
        $form = $users->first()->form;

        if (!$form) {
            \Flash::warning("Could not resolve the parent upload form.");
            return;
        }

        foreach ($users as $user) {
            if (!$user->email) {
                continue;
            }
            $inviteUrl = $baseUrl . "/" . $form->form_id . "/" . $user->token;

            Mail::send(
                "mercator.uploader::mail.invite",
                [
                    "name" => $user->name ?: $user->email,
                    "form" => $form,
                    "url" => $inviteUrl,
                ],
                function ($message) use ($user, $form) {
                    $message->to($user->email, $user->name ?: null);
                    $message->subject("Upload invitation: " . $form->title);
                }
            );

            $user->invited_at = now();
            $user->save();
            $count++;
        }

        \Flash::success($count . " invite(s) sent.");
        $this->initForm($form);
        $this->initRelation($form);

        // Refresh the users relation list in the current form context.
        return $this->relationRefresh("users");
    }
}