<?php

namespace App\Medical\Metadata;

use Illuminate\Support\Manager;

/**
 * Builds the metadata extractor configuration asks for.
 */
class MetadataManager extends Manager
{
    /**
     * The extractor to use when none is configured.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('medical.metadata.enabled', true) ? 'opper' : 'null';
    }

    /**
     * An extractor that suggests nothing.
     */
    public function createNullDriver(): MetadataExtractor
    {
        return new NullMetadataExtractor;
    }

    /**
     * The Opper gateway, in front of whichever chat model is configured.
     */
    public function createOpperDriver(): MetadataExtractor
    {
        /** @var array{base_url: string, api_key: string|null, model: string, timeout: int} $config */
        $config = $this->config->get('medical.metadata');

        return new OpperMetadataExtractor($config);
    }
}
