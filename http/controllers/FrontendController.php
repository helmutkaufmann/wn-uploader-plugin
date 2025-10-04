<?php namespace Mercator\Uploader\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mercator\Uploader\Models\UploadForm;

class FrontendController
{
    public function show($id)
    {
        $form = UploadForm::where('form_id', $id)->first();
        if (!$form) {
            return response("Upload form not found.", 404);
        }

        return view('mercator.uploader::upload', [
            'form' => $form,
            'csrf' => csrf_token(),
        ]);
    }

    public function upload($id)
    {
        $form = UploadForm::where('form_id', $id)->first();
        if (!$form) {
            return response()->json(['error' => 'Invalid form'], 404);
        }

        $tz = new \DateTimeZone($form->timezone ?: 'UTC');
        $now = Carbon::now($tz);
        $start = Carbon::parse($form->start_date, $tz);
        $end   = Carbon::parse($form->end_date, $tz);
        if (!$now->betweenIncluded($start, $end)) {
            return response()->json(['error' => 'Uploads not allowed at this time.'], 403);
        }

        $file = request()->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json(['error' => 'No file uploaded or invalid upload'], 400);
        }

        $allowed = array_filter(array_map('trim', explode(',', strtolower($form->allowed_types))));
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, $allowed)) {
            return response()->json(['error' => 'File type not allowed'], 400);
        }

        $safeName  = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $finalName = uniqid() . '_' . $safeName . '.' . $ext;

        $dir = trim($form->upload_dir ?: 'uploader', '/');
        $path = 'media/' . $dir;

        // Ensure directory exists and store under storage/app/media/<dir>
        if (!Storage::disk('local')->exists($path)) {
            Storage::disk('local')->makeDirectory($path);
        }
        $storedPath = $file->storeAs($path, $finalName, 'local');

        return response()->json(['success' => true, 'path' => $storedPath]);
    }
}