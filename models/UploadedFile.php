<?php namespace Mercator\Uploader\Models;

use Model;
use Illuminate\Support\Facades\Storage;

class UploadedFile extends Model
{
    protected $table = "mercator_uploader_files";
    public $timestamps = true;

    protected $fillable = [
        "upload_form_id",
        "upload_user_id",
        "file_token",
        "disk",
        "path",
        "original_name",
        "mime_type",
        "size",
        "width",
        "height",
        "category_id",
    ];

    public $belongsTo = [
        "form" => [\Mercator\Uploader\Models\UploadForm::class, "key" => "upload_form_id"],
        "user" => [\Mercator\Uploader\Models\UploadUser::class, "key" => "upload_user_id"],
        "categoryRecord" => [\Mercator\Uploader\Models\UploadCategory::class, "key" => "category_id"],
    ];

    /**
     * Read-only convenience so every existing `$file->category` read (Twig views, JSON
     * responses) keeps working unchanged after the underlying storage moved from a free-text
     * column to a category_id foreign key. Callers that need to *set* the category resolve the
     * id explicitly (see FrontendRoutes::resolveCategoryId) rather than through a magic setter,
     * so a typo'd or unvalidated category name can never silently create a stray category row.
     */
    public function getCategoryAttribute(): ?string
    {
        return $this->categoryRecord?->name;
    }

    public function beforeCreate()
    {
        if (!$this->file_token) {
            $this->file_token = bin2hex(random_bytes(12));
        }
    }

    /**
     * Removes the file and its thumbnail from disk before the DB row goes away, so a deletion
     * never leaves an orphaned file behind — whether triggered by a single moderation delete or
     * cascaded from deleting the parent UploadForm (its "files" relation has "delete" => true,
     * which deletes each UploadedFile row via this same path).
     */
    public function beforeDelete()
    {
        $disk = Storage::disk($this->disk);

        if ($this->path && $disk->exists($this->path)) {
            $disk->delete($this->path);
        }

        $thumbPath = "thumbs/" . $this->file_token . ".jpg";
        if ($disk->exists($thumbPath)) {
            $disk->delete($thumbPath);
        }
    }

    public function isImage(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, "image/");
    }

    public function isVideo(): bool
    {
        return $this->mime_type && str_starts_with($this->mime_type, "video/");
    }
}
