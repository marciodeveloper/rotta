<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SafeImageProcessor
{
    /** @return array{bytes:string,thumbnail:string,mime:string,extension:string,width:int,height:int,checksum:string} */
    public function process(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->reject();
        }

        $bytes = $file->get();
        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? ($info['mime'] ?? null) : null;
        if (! is_array($info) || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->reject();
        }
        $matchingExtensions = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp']];
        if (! in_array($extension, $matchingExtensions[$mime], true)) {
            $this->reject('A extensão do arquivo não corresponde ao conteúdo da imagem.');
        }

        [$width, $height] = $info;
        if ($width < 1 || $height < 1 || $width > config('prelaunch.media.max_dimension') || $height > config('prelaunch.media.max_dimension') || $width * $height > config('prelaunch.media.max_pixels')) {
            $this->reject('A imagem excede os limites seguros de dimensões.');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            $this->reject();
        }

        try {
            $optimized = $this->resize($image, $width, $height, (int) config('prelaunch.media.optimized_max_dimension'));
            $thumbnail = $this->resize($image, $width, $height, 480);
            $canonicalExtension = match ($mime) {
                'image/jpeg' => 'jpg', 'image/png' => 'png', default => 'webp'
            };
            $optimizedBytes = $this->encode($optimized, $mime);
            $thumbnailBytes = $this->encode($thumbnail, $mime);
            if (strlen($optimizedBytes) > (int) config('prelaunch.media.max_image_bytes')) {
                $this->reject('A imagem otimizada excede o limite de 5 MB.');
            }
            $optimizedWidth = imagesx($optimized);
            $optimizedHeight = imagesy($optimized);
            imagedestroy($optimized);
            imagedestroy($thumbnail);
        } finally {
            imagedestroy($image);
        }

        return ['bytes' => $optimizedBytes, 'thumbnail' => $thumbnailBytes, 'mime' => $mime, 'extension' => $canonicalExtension, 'width' => $optimizedWidth, 'height' => $optimizedHeight, 'checksum' => hash('sha256', $optimizedBytes)];
    }

    private function resize(\GdImage $source, int $width, int $height, int $maximum): \GdImage
    {
        $ratio = min(1, $maximum / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function encode(\GdImage $image, string $mime): string
    {
        ob_start();
        $written = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 85),
            'image/png' => imagepng($image, null, 8),
            'image/webp' => imagewebp($image, null, 82),
            default => false,
        };
        $bytes = ob_get_clean();
        if (! $written || ! is_string($bytes) || $bytes === '') {
            $this->reject('Não foi possível otimizar a imagem.');
        }

        return $bytes;
    }

    private function reject(string $message = 'O arquivo não é uma imagem JPEG, PNG ou WebP íntegra.'): never
    {
        throw ValidationException::withMessages(['media' => $message]);
    }
}
