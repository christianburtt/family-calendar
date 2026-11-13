<?php

namespace App\Medical\Providers;

use App\Medical\Images\ImageNormaliser;
use App\Medical\Metadata\MetadataManager;
use App\Medical\Models\Dosar;
use App\Medical\Models\MedicalDocument;
use App\Medical\Ocr\OcrManager;
use App\Medical\Policies\DosarPolicy;
use App\Medical\Policies\MedicalDocumentPolicy;
use App\Medical\Services\ActivityLog;
use App\Medical\Services\DocumentStorage;
use App\Medical\Services\Search\DocumentSearcher;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the medical module into the application without the application
 * needing to know what is inside it.
 */
class MedicalServiceProvider extends ServiceProvider
{
    /**
     * Register the module's services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('medical.php'), 'medical');

        $this->app->singleton(DocumentSearcher::class);

        $this->app->singleton(OcrManager::class, fn ($app): OcrManager => new OcrManager($app));

        $this->app->singleton(MetadataManager::class, fn ($app): MetadataManager => new MetadataManager($app));

        $this->app->singleton(ImageNormaliser::class);
        $this->app->singleton(DocumentStorage::class);
        $this->app->singleton(ActivityLog::class);
    }

    /**
     * Bootstrap the module.
     */
    public function boot(): void
    {
        Gate::policy(Dosar::class, DosarPolicy::class);
        Gate::policy(MedicalDocument::class, MedicalDocumentPolicy::class);
    }
}
