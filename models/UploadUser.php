<?php namespace Mercator\Uploader\Models;

use Model;

class UploadUser extends Model
{
    protected $table = "mercator_uploader_users";
    public $timestamps = true;
    protected $fillable = ["upload_form_id", "token", "name", "email", "is_active", "invited_at", "last_accessed_at"];

    public $belongsTo = [
        "form" => [\Mercator\Uploader\Models\UploadForm::class, "key" => "upload_form_id"],
    ];

    public function beforeCreate()
    {
        if (!$this->token) {
            $this->token = bin2hex(random_bytes(16));
        }
    }
}