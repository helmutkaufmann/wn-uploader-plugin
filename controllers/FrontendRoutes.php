<?php namespace Mercator\Uploader\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Mercator\Uploader\Models\UploadForm;
use Mercator\Uploader\Models\UploadUser;
use Mercator\Uploader\Models\UploadedFile;
use Mercator\Uploader\Models\UploadCategory;
use Log;
use Redirect;

class FrontendRoutes
{
    public function showDefault()
    {
        $id = request()->query("id");
        $userToken = request()->query("user");

        if (!$id) {
            return Redirect::to("/404")->with("message", "Upload form not found.");
        }

        return $this->show($id, $userToken);
    }

    public function show($id, $userToken = null)
    {
        $form = UploadForm::where("form_id", $id)->first();
        if (!$form) {
            return Redirect::to("/404")->with("message", "Upload form not found.");
        }

        // If restricted, require a valid ?user=TOKEN belonging to this form and active
        if ($form->restricted) {
            $token = $userToken;
            $user = $token
                ? UploadUser::where("token", $token)
                    ->where("upload_form_id", $form->id)
                    ->where("is_active", true)
                    ->first()
                : null;

            if (!$user) {
                // Log::info("Mercator.Uploader: access denied (show) for form {$form->id} with token " . ($token ?: 'NULL'));
                return Redirect::to("/403")->with("message", "Access denied");
            }

            $user->last_accessed_at = now();
            $user->save();
        }

        return view("mercator.uploader::upload", [
            "csrf" => csrf_token(),
            "id" => $id,
            "user" => $userToken,
        ]);
    }

    protected function camelCase($string)
    {
        $string = str_replace(" ", "", ucwords(str_replace(["-", "_"], " ", $string)));

        return $string;
    }

    public function upload($formToken, $userToken)
    {
        // Check for form existance
        $form = UploadForm::where("form_id", $formToken)->first();
        if (!$form) {
            return response()->json(["error" => "Invalid upload form $formToken"], 404);
        }

        // If form is restricted, require a valid ?user=TOKEN belonging to this form and active
        $user = null;
        if ($form->restricted) {
            $token = $userToken;

            $user = $token
                ? UploadUser::where("token", $token)
                    ->where("upload_form_id", $form->id)
                    ->where("is_active", true)
                    ->first()
                : null;

            if (!$user) {
                Log::info(
                    "Mercator.Uploader: Upload access denied for form tolen $formToken with user token " .
                        ($userToken ?: "NULL")
                );
                return response()->json(
                    [
                        "status" => 403,
                        "body" => "Access denied",
                    ],
                    403
                );
            }

            $user->last_accessed_at = now();
            $user->save();
            $userName = "__" . self::camelCase($user->name) . "__";
        } else {
            $userName = "";
        }

        // --- Check upload window ---
        $tz = new \DateTimeZone($form->timezone ?: "UTC");
        $now = Carbon::now($tz);
        $start = Carbon::parse($form->start_date, $tz);
        $end = Carbon::parse($form->end_date, $tz);

        if (!$now->betweenIncluded($start, $end)) {
            return response()->json(["error" => "Uploads not allowed at this time."], 403);
        }

        // --- Validate file(s) ---
        $files = request()->file("file");
        if (!$files) {
            return response()->json(["error" => "No files uploaded."], 400);
        }
        $files = is_array($files) ? $files : [$files];

        $allowed = array_filter(array_map("trim", explode(",", strtolower($form->allowed_types))));
        $maxFileSize = $form->max_file_size && $form->max_file_size > 0 ? $form->max_file_size * 1024 * 1024 : null;
        $maxTotalFileSize =
            $form->max_total_file_size && $form->max_total_file_size > 0
                ? $form->max_total_file_size * 1024 * 1024
                : null;

        $totalSize = 0;
        $stored = [];

        foreach ($files as $file) {
            if (!$file->isValid()) {
                Log::info("Mercator.Uploader: Invalid file upload, error 400");
                return response()->json(["error" => "Invalid file upload."], 400);
            }

            $size = $file->getSize();
            $ext = strtolower($file->getClientOriginalExtension());

            // Check allowed types
            if (!in_array($ext, $allowed)) {
                Log::info("Mercator.Uploader: File type $ext not allowed, error 415");
                return response()->json(
                    [
                        "error" => "File type .$ext not allowed",
                    ],
                    415
                );
            }

            // Check file size
            if ($maxFileSize && $size > $maxFileSize) {
                Log::info("Mercator.Uploader: File exceeds maximum size ($size), error 413");
                return response()->json(
                    [
                        "error" => sprintf(
                            "File %s exceeds the maximum size of %.1f MB.",
                            $file->getClientOriginalName(),
                            $form->max_file_size
                        ),
                    ],
                    413
                );
            }

            $totalSize += $size;
        }

        // Check total size
        if ($maxTotalFileSize && $totalSize > $maxTotalFileSize) {
            Log::info("Mercator.Uploader: Files exceed total maximum size ($totalSize)");
            return response()->json(
                [
                    "error" => sprintf(
                        "Total upload size (%.1f MB) exceeds the maximum allowed of %.1f MB.",
                        $totalSize / 1024 / 1024,
                        $form->max_total_file_size
                    ),
                ],
                413
            );
        }

        // --- Save to /storage/app/media/<upload_dir> ---
        $dir = trim($form->upload_dir ?: "uploader", "/");
        $path = "media/" . $dir;

        if (!Storage::disk("local")->exists($path)) {
            Storage::disk("local")->makeDirectory($path, 0775, true);
        }

        $category = trim((string) request()->input("category", ""));
        if ($category !== "" && !empty($form->category_list) && !in_array($category, $form->category_list, true)) {
            $category = "";
        }
        $categoryId = $category !== "" ? $this->resolveCategoryId($form, $category) : null;

        foreach ($files as $file) {
            $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $ext = strtolower($file->getClientOriginalExtension());
            $finalName = uniqid() . "_" . $userName . $safeName . "." . $ext;
            $storedPath = $file->storeAs($path, $finalName, "local");
            $stored[] = $storedPath;

            $dims = null;
            $mime = $file->getMimeType();
            if ($mime && str_starts_with($mime, "image/")) {
                $dims = @getimagesize(Storage::disk("local")->path($storedPath));
            }

            UploadedFile::create([
                "upload_form_id" => $form->id,
                "upload_user_id" => $user->id ?? null,
                "disk" => "local",
                "path" => $storedPath,
                "original_name" => $file->getClientOriginalName(),
                "mime_type" => $mime,
                "size" => $file->getSize(),
                "width" => $dims[0] ?? null,
                "height" => $dims[1] ?? null,
                "category_id" => $categoryId,
            ]);
        }

        return response()->json(
            [
                "success" => true,
                "count" => count($stored),
                "files" => $stored,
            ],
            200
        );
    }

    protected function findForm(string $formToken): ?UploadForm
    {
        return UploadForm::where("form_id", $formToken)->first();
    }

    /**
     * Look up an existing category row by name for this form. Callers are expected to have
     * already validated $categoryName against $form->category_list, so this never creates a
     * category on the fly — an unrecognized name simply resolves to null.
     */
    protected function resolveCategoryId(UploadForm $form, string $categoryName): ?int
    {
        return UploadCategory::where("upload_form_id", $form->id)
            ->where("name", $categoryName)
            ->value("id");
    }

    /**
     * Link-only forms (restricted = false) are viewable by anyone with the form's link.
     * Restricted forms require a valid, active user token belonging to that form.
     */
    protected function isAuthorized(UploadForm $form, ?string $userToken): bool
    {
        if (!$form->restricted) {
            return true;
        }

        return (bool) UploadUser::where("token", $userToken)
            ->where("upload_form_id", $form->id)
            ->where("is_active", true)
            ->first();
    }

    public function gallery($formToken, $userToken = null)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isAuthorized($form, $userToken)) {
            return Redirect::to("/403")->with("message", "Access denied");
        }

        return view("mercator.uploader::gallery", [
            "id" => $formToken,
            "user" => $userToken,
        ]);
    }

    /**
     * The owner_token is a separate secret from guest/viewer access - it grants
     * delete rights over a form's uploads without needing a backend account.
     */
    protected function isOwner(UploadForm $form, ?string $ownerToken): bool
    {
        return !empty($form->owner_token) && $ownerToken && hash_equals($form->owner_token, $ownerToken);
    }

    public function moderate($formToken, $ownerToken)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isOwner($form, $ownerToken)) {
            return Redirect::to("/403")->with("message", "Access denied");
        }

        return view("mercator.uploader::moderate", [
            "id" => $formToken,
            "owner" => $ownerToken,
        ]);
    }

    public function deleteFile($formToken, $ownerToken, $fileToken)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isOwner($form, $ownerToken)) {
            return response()->json(["error" => "Access denied"], 403);
        }

        $file = UploadedFile::where("upload_form_id", $form->id)->where("file_token", $fileToken)->first();
        if (!$file) {
            return response()->json(["error" => "Not found"], 404);
        }

        // UploadedFile::beforeDelete() removes the file and its thumbnail from disk.
        $file->delete();

        return response()->json(["success" => true]);
    }

    public function updateFileCategory($formToken, $ownerToken, $fileToken)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isOwner($form, $ownerToken)) {
            return response()->json(["error" => "Access denied"], 403);
        }

        $file = UploadedFile::where("upload_form_id", $form->id)->where("file_token", $fileToken)->first();
        if (!$file) {
            return response()->json(["error" => "Not found"], 404);
        }

        $category = trim((string) request()->input("category", ""));
        if ($category !== "" && !empty($form->category_list) && !in_array($category, $form->category_list, true)) {
            return response()->json(["error" => "Unknown category"], 422);
        }

        $file->category_id = $category !== "" ? $this->resolveCategoryId($form, $category) : null;
        $file->save();

        return response()->json(["success" => true, "category" => $file->category]);
    }

    public function slideshow($formToken, $userToken = null)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isAuthorized($form, $userToken)) {
            return Redirect::to("/403")->with("message", "Access denied");
        }

        return view("mercator.uploader::slideshow", [
            "id" => $formToken,
            "user" => $userToken,
        ]);
    }

    public function slideshowFeed($formToken, $userToken = null)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isAuthorized($form, $userToken)) {
            return response()->json(["error" => "Access denied"], 403);
        }

        $query = UploadedFile::where("upload_form_id", $form->id)->orderBy("id", "desc");

        // "categories" (plural, comma-separated) restricts the feed to a set of sub-galleries —
        // e.g. a slideshow embedded on a page scoped to just "Ceremony, Reception". The older
        // singular "category" is still honored for a single sub-gallery.
        $categoriesParam = trim((string) request()->query("categories", ""));
        $categoryNames = $categoriesParam !== ""
            ? array_values(array_filter(array_map("trim", explode(",", $categoriesParam))))
            : array_values(array_filter([trim((string) request()->query("category", ""))]));

        if (!empty($categoryNames)) {
            $categoryIds = UploadCategory::where("upload_form_id", $form->id)
                ->whereIn("name", $categoryNames)
                ->pluck("id")
                ->all();
            // An empty list never matches a real id: unrecognized category name(s) should return
            // no files, not silently ignore the filter and return everything.
            $query->whereIn("category_id", empty($categoryIds) ? [-1] : $categoryIds);
        }

        $since = request()->query("since");
        if ($since) {
            $query->where("created_at", ">", Carbon::parse($since));
        }

        $files = $query
            ->limit(30)
            ->get()
            ->map(function (UploadedFile $f) use ($formToken, $userToken) {
                $token = $userToken ?: "NONE";
                return [
                    "id" => $f->file_token,
                    "url" => url("/mercator/uploader/media/{$formToken}/{$f->file_token}/{$token}"),
                    "thumb" => url("/mercator/uploader/thumb/{$formToken}/{$f->file_token}/{$token}"),
                    "isVideo" => $f->isVideo(),
                    "category" => $f->category,
                ];
            });

        return response()->json([
            "files" => $files,
            "serverTime" => Carbon::now()->toIso8601String(),
        ]);
    }

    public function media($formToken, $fileToken, $userToken = null)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isAuthorized($form, $userToken)) {
            abort(403);
        }

        $file = UploadedFile::where("upload_form_id", $form->id)->where("file_token", $fileToken)->first();
        if (!$file) {
            abort(404);
        }

        $disk = Storage::disk($file->disk);
        if (!$disk->exists($file->path)) {
            abort(404);
        }

        return response()->file($disk->path($file->path), [
            "Content-Type" => $file->mime_type ?: "application/octet-stream",
        ]);
    }

    public function thumb($formToken, $fileToken, $userToken = null)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isAuthorized($form, $userToken)) {
            abort(403);
        }

        $file = UploadedFile::where("upload_form_id", $form->id)->where("file_token", $fileToken)->first();
        if (!$file) {
            abort(404);
        }

        $ext = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));
        if (!$file->isImage() || in_array($ext, ["heic", "heif"])) {
            return $this->genericThumbIcon($file->isVideo() ? "video" : "file");
        }

        $disk = Storage::disk($file->disk);
        if (!$disk->exists($file->path)) {
            abort(404);
        }

        $thumbPath = "thumbs/" . $file->file_token . ".jpg";

        if (!$disk->exists($thumbPath)) {
            $data = $this->makeThumbnail($disk->path($file->path));
            if ($data === null) {
                return $this->genericThumbIcon("file");
            }
            $disk->put($thumbPath, $data);
        }

        return response($disk->get($thumbPath), 200, [
            "Content-Type" => "image/jpeg",
            "Cache-Control" => "public, max-age=604800",
        ]);
    }

    public function download($formToken, $userToken = null)
    {
        $form = $this->findForm($formToken);
        if (!$form || !$this->isAuthorized($form, $userToken)) {
            abort(403);
        }

        $files = UploadedFile::where("upload_form_id", $form->id)->get();
        if ($files->isEmpty()) {
            return Redirect::back()->with("message", "No files yet.");
        }

        $tmpZip = tempnam(sys_get_temp_dir(), "uploader_") . ".zip";
        $zip = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($files as $f) {
            $disk = Storage::disk($f->disk);
            if (!$disk->exists($f->path)) {
                continue;
            }
            $entryName = $f->id . "_" . ($f->original_name ?: basename($f->path));
            $zip->addFile($disk->path($f->path), $entryName);
        }

        $zip->close();

        $zipName = Str::slug($form->title ?: "uploads") . ".zip";

        return response()->download($tmpZip, $zipName)->deleteFileAfterSend(true);
    }

    protected function makeThumbnail(string $absolutePath, int $maxDim = 480): ?string
    {
        $info = @getimagesize($absolutePath);
        if (!$info) {
            return null;
        }

        [$width, $height, $type] = $info;

        $src = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG => @imagecreatefrompng($absolutePath),
            IMAGETYPE_GIF => @imagecreatefromgif($absolutePath),
            IMAGETYPE_WEBP => function_exists("imagecreatefromwebp") ? @imagecreatefromwebp($absolutePath) : null,
            default => null,
        };

        if (!$src) {
            return null;
        }

        $ratio = min($maxDim / $width, $maxDim / $height, 1);
        $newW = max(1, (int) round($width * $ratio));
        $newH = max(1, (int) round($height * $ratio));

        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        ob_start();
        imagejpeg($dst, null, 80);
        $data = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $data ?: null;
    }

    protected function genericThumbIcon(string $kind)
    {
        $svg =
            $kind === "video"
                ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#2c3e50"/><polygon points="40,30 40,70 72,50" fill="#ffffff"/></svg>'
                : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#bdc3c7"/><text x="50" y="62" font-size="40" text-anchor="middle" fill="#ffffff">?</text></svg>';

        return response($svg, 200, [
            "Content-Type" => "image/svg+xml",
            "Cache-Control" => "public, max-age=604800",
        ]);
    }
}