<?php

namespace App\Medical\Images;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Turns whatever a phone or a clinic sent into a JPEG the rest of the module can
 * work with.
 *
 * iPhones shoot HEIC, which browsers cannot display and the OCR service will not
 * accept. Converting needs an image tool, and which one exists depends on the
 * host: this Mac has the Imagick extension, a Laravel Cloud app has the `magick`
 * command instead, and a bare container may have neither. Each is tried in turn,
 * and a file nothing can convert is refused with a message rather than stored in
 * a shape nothing can read.
 */
final class ImageNormaliser
{
    /**
     * The longest edge kept. Bigger than this is OCR overhead, not detail.
     */
    private const int MAX_EDGE = 2500;

    /**
     * JPEG quality for the normalised copy.
     */
    private const int QUALITY = 85;

    /**
     * Types that need converting to a JPEG the OCR service and browsers accept.
     *
     * @var list<string>
     */
    private const array CONVERTIBLE = ['image/heic', 'image/heif', 'image/tiff', 'image/webp'];

    /**
     * Normalise an upload, returning null when it needs no conversion.
     *
     * A PDF and an ordinary JPEG are stored exactly as they arrived; a HEIC, TIFF
     * or WEBP photo comes back as a JPEG.
     */
    public function normalise(UploadedFile $file): ?NormalisedImage
    {
        $mimeType = (string) $file->getMimeType();

        if (! in_array($mimeType, self::CONVERTIBLE, true)) {
            return null;
        }

        $path = $file->getRealPath();

        if ($path === false) {
            throw new RuntimeException(__('The upload could not be read.'));
        }

        return new NormalisedImage(
            mimeType: 'image/jpeg',
            bytes: $this->toJpeg($path, $mimeType, self::MAX_EDGE, self::QUALITY),
        );
    }

    /**
     * Build a small thumbnail for the document list, when the file is an image
     * at all.
     *
     * A thumbnail is a convenience, never a reason to lose a scan: if the host
     * cannot make one, the document is stored without it.
     *
     * @return array{mime_type: string, bytes: string}|null
     */
    public function thumbnail(UploadedFile $file): ?array
    {
        $mimeType = (string) $file->getMimeType();
        $path = $file->getRealPath();

        if (! str_starts_with($mimeType, 'image/') || $path === false) {
            return null;
        }

        try {
            return [
                'mime_type' => 'image/jpeg',
                'bytes' => $this->toJpeg($path, $mimeType, 400, 70),
            ];
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Convert a file to JPEG bytes, trying every tool the host might have.
     */
    private function toJpeg(string $path, string $mimeType, int $maxEdge, int $quality): string
    {
        $converter = $this->imagickConverter()
            ?? $this->commandConverter('magick')
            ?? $this->commandConverter('convert')
            ?? $this->sipsConverter();

        if ($converter === null) {
            throw new RuntimeException(
                __('This server has no image converter, so :type files cannot be accepted.', ['type' => $mimeType])
            );
        }

        $bytes = $converter($path, $maxEdge, $quality);

        if ($bytes === null) {
            throw new RuntimeException(
                __('This photo could not be converted. Try taking the picture again, or send it as a PDF.')
            );
        }

        return $bytes;
    }

    /**
     * The Imagick extension, when the host has it.
     *
     * @return (\Closure(string, int, int): (string|null))|null
     */
    private function imagickConverter(): ?\Closure
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        return function (string $path, int $maxEdge, int $quality): ?string {
            try {
                $image = new \Imagick($path);
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality($quality);
                $image->thumbnailImage($maxEdge, $maxEdge, true);

                return $image->getImageBlob();
            } catch (\ImagickException) {
                return null;
            }
        };
    }

    /**
     * The ImageMagick command line, which a Laravel Cloud PHP image ships even
     * when the `imagick` extension is not installed.
     *
     * @return (\Closure(string, int, int): (string|null))|null
     */
    private function commandConverter(string $binary): ?\Closure
    {
        if (! $this->commandExists($binary)) {
            return null;
        }

        return function (string $path, int $maxEdge, int $quality) use ($binary): ?string {
            $target = tempnam(sys_get_temp_dir(), 'medical-image-');

            if ($target === false) {
                return null;
            }

            // "2500x2500>" shrinks only, and the quotes stop the shell reading
            // the angle bracket as a redirect.
            $command = sprintf(
                '%s %s -auto-orient -resize %s -quality %d %s 2>/dev/null',
                $binary,
                escapeshellarg($path),
                escapeshellarg($maxEdge.'x'.$maxEdge.'>'),
                $quality,
                escapeshellarg($target),
            );

            exec($command, $output, $status);

            $bytes = is_file($target) && filesize($target) > 0 ? (string) file_get_contents($target) : null;

            @unlink($target);

            return $status === 0 ? $bytes : null;
        };
    }

    /**
     * macOS `sips`, which reads the 10-bit HEIC profile Imagick sometimes cannot.
     *
     * @return (\Closure(string, int, int): (string|null))|null
     */
    private function sipsConverter(): ?\Closure
    {
        if (PHP_OS_FAMILY !== 'Darwin' || ! $this->commandExists('sips')) {
            return null;
        }

        return function (string $path, int $maxEdge, int $quality): ?string {
            $target = tempnam(sys_get_temp_dir(), 'medical-sips-');

            if ($target === false) {
                return null;
            }

            $command = sprintf(
                'sips -s format jpeg -Z %d %s --out %s 2>&1',
                $maxEdge,
                escapeshellarg($path),
                escapeshellarg($target),
            );

            exec($command, $output, $status);

            $bytes = $status === 0 && is_file($target) && filesize($target) > 0
                ? (string) file_get_contents($target)
                : null;

            @unlink($target);

            return $bytes;
        };
    }

    /**
     * Whether a command is on the PATH.
     */
    private function commandExists(string $binary): bool
    {
        $output = [];
        $status = 0;

        exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($binary)), $output, $status);

        return $status === 0;
    }
}
