<?php

namespace App\Medical\Services;

use App\Medical\Images\ImageNormaliser;
use App\Medical\Models\MedicalDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files an upload into the archive.
 *
 * This runs in the request rather than on the queue: the upload only exists at
 * that moment. Storing it is also what makes a document real, and the expensive
 * part, reading it, is a separate job that can fail and be retried without ever
 * losing the scan.
 */
final class DocumentStorage
{
    public function __construct(private readonly ImageNormaliser $normaliser)
    {
        //
    }

    /**
     * Store the upload and point the document at it.
     *
     * HEIC and TIFF photos are converted to JPEG on the way in, because the rest
     * of the module (the browser, the OCR service) only understands JPEG.
     */
    public function store(MedicalDocument $document, UploadedFile $file): MedicalDocument
    {
        $normalised = $this->normaliser->normalise($file);
        $thumbnail = $this->normaliser->thumbnail($file);
        $disk = Storage::disk($document->disk);
        $directory = 'medical/'.$document->calendar_id.'/'.Str::uuid();

        $extension = $normalised?->extension()
            ?? mb_strtolower($file->getClientOriginalExtension() ?: 'bin');

        if ($normalised !== null) {
            $path = $directory.'/original.'.$extension;
            $disk->put($path, $normalised->bytes);
            $bytes = $normalised->bytes;
            $mimeType = $normalised->mimeType;
        } else {
            $path = $file->storeAs($directory, 'original.'.$extension, $document->disk);

            if ($path === false) {
                throw new \RuntimeException(__('The file could not be stored.'));
            }

            $bytes = (string) $disk->get($path);
            $mimeType = (string) $file->getMimeType();
        }

        $thumbnailPath = null;

        if ($thumbnail !== null) {
            $thumbnailPath = $directory.'/thumb.jpg';
            $disk->put($thumbnailPath, $thumbnail['bytes']);
        }

        $document->forceFill([
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
        ])->save();

        return $document;
    }

    /**
     * Remove a document's files from storage.
     */
    public function delete(MedicalDocument $document): void
    {
        $disk = Storage::disk($document->disk);

        if ($disk->exists($document->path)) {
            $disk->delete($document->path);
        }

        if ($document->thumbnail_path !== null && $disk->exists($document->thumbnail_path)) {
            $disk->delete($document->thumbnail_path);
        }
    }
}
