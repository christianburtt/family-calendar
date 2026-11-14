<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Where medical files are kept. Null means the environment's default disk,
    | which is what Laravel Cloud wants: attach a private bucket as the default
    | and Cloud injects the credentials, so nothing here names a disk that only
    | exists on one host. A self-hosted install can name one instead.
    |
    */

    'disk' => env('MEDICAL_DISK') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | A twelve-page referral from a clinic is well over the default limits, so
    | take megabytes are capped here as well as in the validation rules. The PHP
    | ini still needs upload_max_filesize and post_max_size raised above this.
    |
    */

    'max_upload_mb' => (int) env('MEDICAL_MAX_UPLOAD_MB', 25),

    'max_files_per_upload' => 20,

    'accepted_mime_types' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/heif',
        'image/tiff',
        'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | OCR
    |--------------------------------------------------------------------------
    |
    | The engine is chosen by driver so the application has no opinion about
    | who reads the scans. "null" is a working engine that reads nothing, which
    | keeps the whole module usable and testable without a key.
    |
    */

    'ocr' => [
        'driver' => env('MEDICAL_OCR_DRIVER', 'null'),

        'base_url' => env('MEDICAL_OCR_BASE_URL', 'https://api.opper.ai'),

        'api_key' => env('OPPER_API_KEY'),

        'model' => env('MEDICAL_OCR_MODEL', 'mistral/mistral-ocr-latest'),

        'timeout' => (int) env('MEDICAL_OCR_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    |
    | A second, cheap model reads the extracted text and suggests a title, a
    | category, a date and the clinic. Its suggestions are never trusted
    | blindly: they land as suggestions until somebody accepts them.
    |
    */

    'metadata' => [
        'enabled' => (bool) env('MEDICAL_METADATA_ENABLED', true),

        'base_url' => env('MEDICAL_OCR_BASE_URL', 'https://api.opper.ai'),

        'api_key' => env('OPPER_API_KEY'),

        'model' => env('MEDICAL_METADATA_MODEL', 'deepseek-v4.1-flash'),

        'timeout' => (int) env('MEDICAL_METADATA_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Deleted documents stay restorable for this many days before the file and
    | its text are removed for good.
    |
    */

    'restore_days' => (int) env('MEDICAL_RESTORE_DAYS', 30),

];
