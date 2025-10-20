<?php

namespace App\Services\Api\V1\Embeddings;

use InvalidArgumentException;

class EmbeddingPipelineFactory
{
    /**
     * Create a pipeline instance for the given variant.
     *
     * @param string $variant
     * @return EmbeddingPipelineInterface
     * @throws InvalidArgumentException
     */
    public function make(string $variant): EmbeddingPipelineInterface
    {
        return match($variant) {
            'simple' => app(SimplePipeline::class),
            'complex' => app(ComplexPipeline::class),
            default => throw new InvalidArgumentException("Unknown embedding variant: {$variant}")
        };
    }
}

