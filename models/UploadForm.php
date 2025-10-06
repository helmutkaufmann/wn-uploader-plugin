<?php namespace Mercator\Uploader\Models;

use Model;
use Winter\Storm\Database\Traits\Validation;
use Log;

class UploadForm extends Model
{
    use Validation;

    protected $table = "mercator_uploader_forms";

    // keep dates as plain strings
    protected $dates = [];

    protected $casts = [
        "client_resize_enabled" => "boolean",
        "auto_upload_enabled" => "boolean",
        "preserve_exif" => "boolean",
        "use_image_editor" => "boolean",
        "restricted" => "boolean",
        "client_resize_max_width" => "integer",
        "client_resize_max_height" => "integer",
        "max_file_size" => "integer",
        "max_total_file_size" => "integer",
        "client_resize_quality" => "float",
    ];

    public $attributeNames = [
        "start_date" => "Start Date/Time",
        "end_date" => "End Date/Time",
    ];

    public $fillable = [
        "form_id",
        "title",
        "description",
        "upload_dir",
        "allowed_types",
        "start_date",
        "end_date",
        "timezone",
        "client_resize_enabled",
        "client_resize_max_width",
        "client_resize_max_height",
        "client_resize_quality",
        "auto_upload_enabled",
        "preserve_exif",
        "max_file_size",
        "max_total_file_size",
        "use_image_editor",
        "restricted",
    ];

    public $attributes = [
        "client_resize_enabled" => false,
        "client_resize_max_width" => 1920,
        "client_resize_max_height" => 1080,
        "client_resize_quality" => 0.8,
        "auto_upload_enabled" => true,
        "preserve_exif" => true,
        "use_image_editor" => true,
        "max_file_size" => 0,
        "max_total_file_size" => 0,
        "restricted" => false,
    ];

    public $rules = [
        "title" => "required",
        "upload_dir" => "required",
        "allowed_types" => "required",
        "timezone" => "required",
        "start_date" => "required|date",
        "end_date" => "required|date",
        "client_resize_quality" => "nullable|numeric|min:0|max:1",
        "client_resize_max_width" => "nullable|integer|min:1|max:20000",
        "client_resize_max_height" => "nullable|integer|min:1|max:20000",
        "max_file_size" => "nullable|integer|min:0",
        "max_total_file_size" => "nullable|integer|min:0",
    ];

    public $hasMany = [
        "users" => [
            \Mercator\Uploader\Models\UploadUser::class,
            "key" => "upload_form_id",
            "otherKey" => "upload_form_id",
            "delete" => true,
        ],
    ];
    public function beforeCreate()
    {
        if (!$this->form_id) {
            $this->form_id = substr(bin2hex(random_bytes(8)), 0, 12);
        }
    }

    public function beforeValidate()
    {
        if (empty($this->start_date)) {
            $this->start_date = "2025-01-01 00:00:00";
        }
        if (empty($this->end_date)) {
            $this->end_date = "2099-12-31 23:59:59";
        }

        // normalize allowed_types to a clean csv (no spaces, lowercase)
        if (!empty($this->allowed_types)) {
            $parts = array_filter(array_map("trim", explode(",", strtolower($this->allowed_types))));
            $this->allowed_types = implode(",", array_unique($parts));
        }
    }

    public function beforeSave()
    {
        if ($this->start_date instanceof \Carbon\Carbon) {
            $this->start_date = $this->start_date->format("Y-m-d H:i:s");
        }
        if ($this->end_date instanceof \Carbon\Carbon) {
            $this->end_date = $this->end_date->format("Y-m-d H:i:s");
        }
    }

    public function getTimezoneOptions(): array
    {
        $tz = \DateTimeZone::listIdentifiers();
        sort($tz, SORT_NATURAL | SORT_FLAG_CASE);
        return array_combine($tz, $tz);
    }

    public function afterFetch()
    {
        Log::info("UploadForm loaded, has users count: " . $this->users()->count());
    }
}