<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PreRegistration;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(PreRegistration $preRegistration, string $media): StreamedResponse
    {
        Gate::authorize('view-pre-registration', $preRegistration);
        $image = $preRegistration->media()->findOrFail($media);
        abort_unless($image->storage_disk === config('prelaunch.media_disk'), 404);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$image->mime_type] ?? null;
        abort_if($extension === null, 404);
        $disk = Storage::disk($image->storage_disk);
        abort_unless($disk->exists($image->storage_key), 404);

        return $disk->response($image->storage_key, $image->id.'.'.$extension, [
            'Content-Type' => $image->mime_type,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
