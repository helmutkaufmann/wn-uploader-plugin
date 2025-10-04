<?php namespace Mercator\Uploader\Models;

use Model;
use Winter\Storm\Database\Traits\Validation;

class UploadForm extends Model
{
    use Validation;

    protected $table = 'mercator_uploader_forms';

    // don’t let Winter auto-shift Carbon times unless you want it
    protected $dates = []; // disable automatic timezone conversions

    public $attributeNames = [
        'start_date' => 'Start Date/Time',
        'end_date'   => 'End Date/Time',
    ];

    public $fillable = [
    'form_id','title','description','upload_dir','allowed_types',
    'start_date','end_date','timezone',
    'client_resize_enabled','client_resize_max_width','client_resize_max_height','client_resize_quality','auto_upload_enabled'
];

public $attributes = [
    'client_resize_enabled'   => false,
    'client_resize_max_width' => 1920,
    'client_resize_max_height'=> 1080,
    'client_resize_quality'   => 0.80,
    'auto_upload_enabled' => true
];

public $rules = [
    'title' => 'required',
    'upload_dir' => 'required',
    'allowed_types' => 'required',
    'timezone' => 'required',
    'start_date' => 'required|date',
    'end_date'   => 'required|date',
    'client_resize_quality' => 'nullable|numeric|min:0|max:1',
    'client_resize_max_width'  => 'nullable|integer|min:1|max:10000',
    'client_resize_max_height' => 'nullable|integer|min:1|max:10000',
];

    public function beforeCreate()
    {
        if (!$this->form_id) {
            $this->form_id = substr(bin2hex(random_bytes(8)), 0, 12);
        }
    }

    public function beforeValidate()
    {
        // Normalize empty strings to defaults
        if (empty($this->start_date)) {
            $this->start_date = '2025-01-01 00:00:00';
        }
        if (empty($this->end_date)) {
            $this->end_date = '2099-12-31 23:59:59';
        }
    }

    public function beforeSave()
    {
        // Ensure string format if widgets passed Carbon
        if ($this->start_date instanceof \Carbon\Carbon) {
            $this->start_date = $this->start_date->format('Y-m-d H:i:s');
        }
        if ($this->end_date instanceof \Carbon\Carbon) {
            $this->end_date = $this->end_date->format('Y-m-d H:i:s');
        }
    }

    // Timezone dropdown options (already used by your form/list)
    public function getTimezoneOptions(): array
    {
        $tz = \DateTimeZone::listIdentifiers();
        sort($tz, SORT_NATURAL | SORT_FLAG_CASE);
        return array_combine($tz, $tz);
    }
}

