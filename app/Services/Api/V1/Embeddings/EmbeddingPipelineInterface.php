<?php

namespace App\Services\Api\V1\Embeddings;

use App\Models\Dataset;
use App\Models\DatasetEmbedding;

interface EmbeddingPipelineInterface
{
    /**
     * Process the dataset and generate embeddings.
     *
     * @param Dataset $dataset
     * @param DatasetEmbedding $embedding
     * @param array $options
     * @return void
     */
    public function process(Dataset $dataset, DatasetEmbedding $embedding, array $options): void;
}

