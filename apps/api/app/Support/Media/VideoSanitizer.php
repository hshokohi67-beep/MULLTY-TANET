<?php

namespace App\Support\Media;

use RuntimeException;

/**
 * Checks that an upload really is an MP4/QuickTime video (by its bytes, not its name) and blanks
 * its metadata boxes before it goes public. Phones write the recording place (GPS), the device
 * and the owner's name into `udta`/`meta`; renaming those boxes to `free` hides them from every
 * player without moving a single byte, so offsets inside the file stay valid. No transcoding:
 * shared hosting has no ffmpeg.
 */
final class VideoSanitizer
{
    /** Boxes that may carry personal metadata. */
    private const PRIVATE_BOXES = ['udta', 'meta', 'uuid', 'XMP_'];

    /** Containers whose children are walked (movie, tracks, media). */
    private const CONTAINERS = ['moov', 'trak', 'mdia', 'minf', 'stbl', 'edts'];

    /**
     * Sanitises the file in place.
     *
     * @throws RuntimeException when the file isn't an MP4/QuickTime video
     */
    public function sanitize(string $path): void
    {
        $bytes = (string) file_get_contents($path);
        if (strlen($bytes) < 16 || substr($bytes, 4, 4) !== 'ftyp') {
            throw new RuntimeException('Not an MP4 file.');
        }

        $found = ['moov' => false];
        $this->walk($bytes, 0, strlen($bytes), $found, 0);
        if (! $found['moov']) {
            throw new RuntimeException('MP4 without a movie box.');
        }

        file_put_contents($path, $bytes);
    }

    /** @param  array{moov: bool}  $found */
    private function walk(string &$bytes, int $start, int $end, array &$found, int $depth): void
    {
        $offset = $start;
        while ($offset + 8 <= $end) {
            $size = unpack('N', substr($bytes, $offset, 4))[1] ?? 0;
            $type = substr($bytes, $offset + 4, 4);
            $header = 8;

            if ($size === 1) { // 64-bit size follows the type
                if ($offset + 16 > $end) {
                    throw new RuntimeException('Truncated box.');
                }
                $parts = unpack('Nhi/Nlo', substr($bytes, $offset + 8, 8));
                $size = (($parts['hi'] ?? 0) << 32) | ($parts['lo'] ?? 0);
                $header = 16;
            } elseif ($size === 0) { // box runs to the end of its parent
                $size = $end - $offset;
            }

            if ($size < $header || $offset + $size > $end) {
                throw new RuntimeException('Malformed box.');
            }

            if ($depth === 0 && $type === 'moov') {
                $found['moov'] = true;
            }

            if (in_array($type, self::PRIVATE_BOXES, true)) {
                $bytes = substr_replace($bytes, 'free', $offset + 4, 4);
            } elseif (in_array($type, self::CONTAINERS, true) && $depth < 6) {
                $this->walk($bytes, $offset + $header, $offset + $size, $found, $depth + 1);
            }

            $offset += $size;
        }
    }
}
