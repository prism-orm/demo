<?php

declare(strict_types=1);

namespace App\Docs;

use Prism\Orm\Exception\EmbeddingException;
use Prism\Orm\Vector\EmbeddingProvider;

/**
 * Rend le vecteur précalculé d'un texte connu du snapshot, sans réseau ; un autre texte part au
 * fournisseur de repli (Ollama, si EMBEDDING_BASE_URL est donnée), sinon il est refusé. Le
 * snapshot n'est lu qu'au premier appel.
 */
final class SnapshotEmbeddingProvider implements EmbeddingProvider
{
    /** @var array<string, list<float>>|null */
    private ?array $vectors = null;

    /**
     * @param \Closure(): array<string, list<float>> $load hash du texte → vecteur
     */
    public function __construct(
        private readonly \Closure $load,
        private readonly ?EmbeddingProvider $fallback = null,
    ) {
    }

    public function model(): string
    {
        return Snapshot::MODEL;
    }

    public function dimensions(): int
    {
        return Snapshot::DIMENSIONS;
    }

    public function hasFallback(): bool
    {
        return null !== $this->fallback;
    }

    public function embed(array $texts): array
    {
        $this->vectors ??= ($this->load)();
        $vectors = [];
        $missing = [];
        foreach ($texts as $i => $text) {
            $vector = $this->vectors[sha1($text)] ?? null;
            if (null === $vector) {
                $missing[$i] = $text;
            } else {
                $vectors[$i] = $vector;
            }
        }
        if ([] !== $missing) {
            if (null === $this->fallback) {
                throw EmbeddingException::invalidResponse($this->model(), \sprintf('%d text(s) are not in the snapshot of demo/data, and no EMBEDDING_BASE_URL is set to compute them', \count($missing)));
            }
            foreach ($this->fallback->embed(array_values($missing)) as $j => $vector) {
                $vectors[array_keys($missing)[$j]] = $vector;
            }
        }
        ksort($vectors);

        return array_values($vectors);
    }
}
