<?php

namespace App\Support\Media;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Re-encodes an uploaded photo to WebP with GD: applies the EXIF orientation, fits it within a box
 * and writes it under the given directory with a random name. Re-encoding drops all metadata
 * (a phone photo's GPS never goes public) and turns any polyglot file into plain pixels.
 */
final class ImageProcessor
{
    /** Refuse to decode anything bigger (GD needs ~4 bytes per pixel). */
    public const MAX_PIXELS = 30_000_000;

    /**
     * @param  array<string, array{fit: int, square?: bool, quality?: int}>  $variants  name => box (long side), or a centre-cropped square
     * @return array<string, array{path: string, width: int, height: int}>
     */
    public function store(UploadedFile $file, string $directory, array $variants): array
    {
        $source = $this->load($file);
        $out = [];
        $base = (string) Str::ulid();

        try {
            foreach ($variants as $name => $spec) {
                $image = ($spec['square'] ?? false) ? $this->square($source, $spec['fit']) : $this->fit($source, $spec['fit']);
                ob_start();
                imagewebp($image, null, $spec['quality'] ?? 82);
                $bytes = (string) ob_get_clean();
                $path = sprintf('%s/%s-%s.webp', $directory, $base, $name);
                Storage::disk(config('filesystems.media_disk'))->put($path, $bytes, ['visibility' => 'public']);
                $out[$name] = ['path' => $path, 'width' => imagesx($image), 'height' => imagesy($image)];
                if ($image !== $source) {
                    imagedestroy($image);
                }
            }
        } finally {
            imagedestroy($source);
        }

        return $out;
    }

    private function load(UploadedFile $file): GdImage
    {
        $path = (string) $file->getRealPath();
        $info = @getimagesize($path);

        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Image too large or unreadable.');
        }

        $this->ensureMemory($info[0] * $info[1]);

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unsupported image.');
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $rotated = match ($orientation) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => null,
            };
            if ($rotated instanceof GdImage) {
                imagedestroy($image);
                $image = $rotated;
            }
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * A decoded photo costs ~5 bytes per pixel, plus the resized copies. Phone photos (12–24 MP)
     * exceed the usual 128 MB limit, so raise it for this request when the host allows it.
     */
    private function ensureMemory(int $pixels): void
    {
        $needed = memory_get_usage(true) + $pixels * 10 + 32 * 1024 * 1024;
        $limit = $this->bytes((string) ini_get('memory_limit'));

        if ($limit !== -1 && $needed > $limit && ini_set('memory_limit', (string) $needed) === false) {
            throw new RuntimeException('Not enough memory to process this image.');
        }
    }

    private function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '-1') {
            return -1;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function fit(GdImage $source, int $box): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1, $box / max($w, $h));

        if ($scale >= 1) {
            return $source;
        }

        return $this->resample($source, 0, 0, $w, $h, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
    }

    private function square(GdImage $source, int $size): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);

        return $this->resample($source, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $side, $side, min($size, $side), min($size, $side));
    }

    private function resample(GdImage $source, int $sx, int $sy, int $sw, int $sh, int $dw, int $dh): GdImage
    {
        $target = imagecreatetruecolor(max(1, $dw), max(1, $dh));
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);

        return $target;
    }
}
