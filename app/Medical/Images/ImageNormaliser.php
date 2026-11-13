<?php

namespace App\Medical\Images;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Turns whatever a phone or a clinic sent into a JPEG the rest of the module can
 * work with.
 *
 * iPhones shoot HEIC, and the browsers that read these files cannot display it
 * while the OCR service will not accept it. Imagick reads most HEIC but chokes on
 * the 10-bit profile iPhones produce, so macOS gets a second attempt through
 * `sips`. On a host without either, the file is refused with a message rather
 * than stored in a shape nothing can read.
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

        $image = $this->read($path, $mimeType);

        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(self::QUALITY);
        $image->thumbnailImage(self::MAX_EDGE, self::MAX_EDGE, true);

        return new NormalisedImage(
            mimeType: 'image/jpeg',
            bytes: $image->getImageBlob(),
        );
    }

    /**
     * Build a small thumbnail for the document list, when the file is an image
     * at all. A PDF gets none: rasterising one needs Ghostscript, which is not
     * a dependency worth adding to show a preview of a page.
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

        $image = $this->read($path, $mimeType);
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(70);
        $image->thumbnailImage(400, 400, true);

        return ['mime_type' => 'image/jpeg', 'bytes' => $image->getImageBlob()];
    }

    /**
     * Read the file with Imagick, falling back to sips on macOS.
     */
    private function read(string $path, string $mimeType): \Imagick
    {
        if (! class_exists(\Imagick::class)) {
            throw new RuntimeException(__('This server cannot convert :type files.', ['type' => $mimeType]));
        }

        try {
            return new \Imagick($path);
        } catch (\ImagickException) {
            $converted = $this->convertWithSips($path);

            if ($converted === null) {
                throw new RuntimeException(
                    __('This photo could not be converted. Try taking the picture again, or send it as a PDF.')
                );
            }

            return $converted;
        }
    }

    /**
     * Convert through macOS `sips`, which reads the 10-bit HEIC profile Imagick
     * on this host cannot. Present on a Mac, absent on a Linux server.
     */
    private function convertWithSips(string $path): ?\Imagick
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            return null;
        }

        $target = tempnam(sys_get_temp_dir(), 'medical-normalise-');

        if ($target === false) {
            return null;
        }

        $output = [];
        $status = 0;

        exec(sprintf(
            'sips -s format jpeg -Z %d %s --out %s 2>&1',
            self::MAX_EDGE,
            escapeshellarg($path),
            escapeshellarg($target),
        ), $output, $status);

        if ($status !== 0 || ! is_file($target) || filesize($target) === 0) {
            @unlink($target);

            return null;
        }

        try {
            return new \Imagick($target);
        } catch (\ImagickException) {
            return null;
        } finally {
            @unlink($target);
        }
    }
}
