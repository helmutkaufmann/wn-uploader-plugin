<?php namespace Mercator\Uploader\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Mercator\Uploader\Models\UploadForm;
use Mercator\Uploader\Models\UploadUser;
use Log;

class FrontendController
{
    public function show($id)
    {
        $form = UploadForm::where('form_id', $id)->first();
        if (!$form) {
            return response("Upload form not found.", 404);
        }

        // If restricted, require a valid ?user=TOKEN belonging to this form and active
        if ($form->restricted) {
            $token = request()->query('user');
            $user = $token
                ? UploadUser::where('token', $token)
                    ->where('upload_form_id', $form->id)
                    ->where('is_active', true)
                    ->first()
                : null;

            if (!$user) {
                Log::info("Mercator.Uploader: access denied (show) for form {$form->id} with token " . ($token ?: 'NULL'));
                return response("Access denied.", 403);
            }

            $user->last_accessed_at = now();
            $user->save();
        }

        return view('mercator.uploader::upload', [
            'form' => $form,
            'csrf' => csrf_token(),
        ]);
    }
    
    protected function camelCase($string) {
        $string = str_replace(' ', '', 
            ucwords(str_replace(['-', '_'], 
            ' ', $string))
        );

        return $string;
    }   

    public function upload($formToken, $userToken)
    {
        
        // Check for form existance
        $form = UploadForm::where('form_id', $formToken)->first();
        if (!$form) {
            return response()->json(['error' => "Invalid upload form $formToken"], 404);
        }

        // If form is restricted, require a valid ?user=TOKEN belonging to this form and active
        if ($form->restricted) {
            $token = $userToken;
        
            $user = $token
                ? UploadUser::where('token', $token)
                    ->where('upload_form_id', $form->id)
                    ->where('is_active', true)
                    ->first()
                : null;
            
            if (!$user) {
                Log::info("Mercator.Uploader: Upload access denied for form tolen $formToken with user token " . ($userToken ?: 'NULL'));
                return response()->json([
                    'status' => 403,
                    'body' => 'Access denied'], 403);
            }

            $user->last_accessed_at = now();
            $user->save();
            $userName = "__" . self::camelCase($user->name) . "__";
        }
        else  
            $userName = "";

        // --- Check upload window ---
        $tz = new \DateTimeZone($form->timezone ?: 'UTC');
        $now = Carbon::now($tz);
        $start = Carbon::parse($form->start_date, $tz);
        $end   = Carbon::parse($form->end_date, $tz);

        if (!$now->betweenIncluded($start, $end)) {
            return response()->json(['error' => 'Uploads not allowed at this time.'], 403);
        }

        // --- Validate file(s) ---
        $files = request()->file('file');
        if (!$files) {
            return response()->json(['error' => 'No files uploaded.'], 400);
        }
        $files = is_array($files) ? $files : [$files];

        $allowed = array_filter(array_map('trim', explode(',', strtolower($form->allowed_types))));
        $maxFileSize      = ($form->max_file_size && $form->max_file_size > 0)
            ? $form->max_file_size * 1024 * 1024 : null;
        $maxTotalFileSize = ($form->max_total_file_size && $form->max_total_file_size > 0)
            ? $form->max_total_file_size * 1024 * 1024 : null;

        $totalSize = 0;
        $stored = [];

        foreach ($files as $file) {
            if (!$file->isValid()) {
                Log::info("Mercator.Uploader: Invalid file upload, error 400");
                return response()->json(['error' => 'Invalid file upload.'], 400);
            }

            $size = $file->getSize();
            $ext = strtolower($file->getClientOriginalExtension());

            // Check allowed types
            if (!in_array($ext, $allowed)) {
                Log::info("Mercator.Uploader: File type $ext not allowed, error 415");
                return response()->json([
                    'error' => "File type .$ext not allowed"
                ], 415);
            }

            // Check file size
            if ($maxFileSize && $size > $maxFileSize) {
                Log::info("Mercator.Uploader: File exceeds maximum size ($size), error 413");
                return response()->json([
                    'error' => sprintf(
                        "File %s exceeds the maximum size of %.1f MB.",
                        $file->getClientOriginalName(),
                        $form->max_file_size
                    )
                ], 413);
            }

            $totalSize += $size;
        }

        // Check total size
        if ($maxTotalFileSize && $totalSize > $maxTotalFileSize) {
            Log::info("Mercator.Uploader: Files exceed total maximum size ($totalSize)");
            return response()->json([
                'error' => sprintf(
                    "Total upload size (%.1f MB) exceeds the maximum allowed of %.1f MB.",
                    $totalSize / 1024 / 1024,
                    $form->max_total_file_size
                )
            ], 413);
        }

        // --- Save to /storage/app/media/<upload_dir> ---
        $dir = trim($form->upload_dir ?: 'uploader', '/');
        $path = 'media/' . $dir;

        if (!Storage::disk('local')->exists($path)) {
            Storage::disk('local')->makeDirectory($path, 0775, true);
        }

        foreach ($files as $file) {
            $safeName  = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $ext       = strtolower($file->getClientOriginalExtension());
            $finalName = uniqid() . '_' . $userName . $safeName . '.' . $ext;
            $storedPath = $file->storeAs($path, $finalName, 'local');
            $stored[] = $storedPath;
        }

        return response()->json([
            'success' => true,
            'count'   => count($stored),
            'files'   => $stored,
        ]);
    }
}