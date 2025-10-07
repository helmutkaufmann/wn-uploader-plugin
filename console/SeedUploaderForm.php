<?php namespace Mercator\Uploader\Console;

use Illuminate\Console\Command;
use Mercator\Uploader\Models\UploadForm;

class SeedUploaderForm extends Command
{
    protected $name = 'uploader:seed';
    protected $description = 'Creates a new test upload form and prints the form_id';

    public function handle()
    {
        $form = new UploadForm();
        $form->title = "CLI Test Upload";
        $form->description = "Generated via uploader:seed";
        $form->upload_dir = "uploader/cli-test";
        $form->allowed_types = "jpg,png,pdf";
        $form->start_date = "2025-01-01 00:00:00";
        $form->end_date   = "2099-12-31 23:59:59";
        $form->timezone   = "Europe/Zurich";
        $form->save();

        $this->info("Created Upload Form with ID: {$form->form_id}");
        $this->info("Open: /mercator/uploader/{$form->form_id}");
    }
}