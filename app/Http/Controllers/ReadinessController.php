<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::selectOne('select 1');
            $diskName = (string) config('prelaunch.media_disk');
            $disk = Storage::disk($diskName);
            if (config("filesystems.disks.{$diskName}.driver") !== 'local' || ! is_dir($disk->path('')) || ! is_writable($disk->path(''))) {
                throw new RuntimeException('Private media storage is unavailable.');
            }

            return response()->json(['status' => 'ready']);
        } catch (Throwable) {
            return response()->json(['status' => 'unavailable'], 503);
        }
    }
}
