<?php

declare(strict_types=1);

namespace App\Docs;

use Prism\Embedding\OpenAi\OpenAiCompatibleProvider;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Les deux fournisseurs de la recherche documentaire, un même modèle : « default » pour les
 * sections (#[EmbeddedVector]), « query » pour les questions. Sans EMBEDDING_BASE_URL, seuls les
 * textes du snapshot ont un vecteur ; avec, Ollama calcule les autres.
 */
final readonly class DocsEmbeddings
{
    public function __construct(
        private HttpClientInterface $http,
        private string $dataDir,
        private string $baseUrl = '',
    ) {
    }

    public function documents(): SnapshotEmbeddingProvider
    {
        return new SnapshotEmbeddingProvider(fn (): array => Snapshot::load($this->dataDir)->documents, $this->ollama(Snapshot::DOCUMENT_PREFIX));
    }

    public function queries(): SnapshotEmbeddingProvider
    {
        return new SnapshotEmbeddingProvider(fn (): array => Snapshot::load($this->dataDir)->queries, $this->ollama(Snapshot::QUERY_PREFIX));
    }

    public function ollama(string $prefix): ?OpenAiCompatibleProvider
    {
        return '' === $this->baseUrl ? null : new OpenAiCompatibleProvider($this->http, $this->baseUrl, Snapshot::MODEL, Snapshot::DIMENSIONS, batchSize: 32, prefix: $prefix);
    }
}
