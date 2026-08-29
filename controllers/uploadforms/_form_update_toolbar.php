<?php
    $modelName = $formConfig->name ?? '';
?>
<div class="loading-indicator-container">
    <button
        type="button"
        data-request="onSave"
        data-browser-validate
        data-load-indicator="<?= e(trans('backend::lang.form.saving')) ?>"
        data-request-before-update="$el.trigger('unchange.oc.changeMonitor')"
        data-request-data="redirect:0"
        data-hotkey="ctrl+s, cmd+s"
        class="btn btn-primary wn-icon-save"
    >
        <?= e(trans('backend::lang.form.save')); ?>
    </button>
    <button
        type="button"
        data-request="onSave"
        data-browser-validate
        data-request-data="close:1"
        data-hotkey="ctrl+enter, cmd+enter"
        data-load-indicator="<?= e(trans('backend::lang.form.saving')); ?>"
        data-request-before-update="$el.trigger('unchange.oc.changeMonitor')"
        class="btn btn-default wn-icon-check"
    >
        <?= e(trans('backend::lang.form.save_and_close')); ?>
    </button>
    <!--
        Delete: overridden from the core standard-layout toolbar (formcontroller/partials/_toolbar.php)
        to offer a choice between deleting the uploaded files from disk along with the form, or
        keeping them (see UploadForms::onDelete()). A plain data-request-confirm can only ask one
        yes/no question, so this asks a second one via JS and fires the request manually instead.
    -->
    <button
        type="button"
        class="wn-icon-trash-o btn-icon danger pull-right"
        onclick="
            if (!confirm('Delete this Upload Form? This cannot be undone.')) return;
            var keepFiles = !confirm('Also permanently delete the uploaded files from disk?\n\nOK = delete the files\nCancel = keep the files, only delete the form');
            var btn = this;
            $(btn).request('onDelete', {
                data: { keepFiles: keepFiles ? 1 : 0 },
                beforeUpdate: function () { $(btn).trigger('unchange.oc.changeMonitor') }
            })
        "
    >
    </button>
    <span class="btn-text">
        <?= e(trans('backend::lang.form.or')) ?> <a href="<?= Backend::url($formConfig->defaultRedirect) ?>"><?= e(trans('backend::lang.form.cancel')); ?></a>
    </span>
</div>
