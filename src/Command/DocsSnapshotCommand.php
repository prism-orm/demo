<?php

declare(strict_types=1);

namespace App\Command;

use App\Docs\DocChunker;
use App\Docs\DocsEmbeddings;
use App\Docs\Questions;
use App\Docs\Section;
use App\Docs\Snapshot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Recalcule le snapshot de data/ (ADR 0018) : la documentation découpée en sections, et les
 * vecteurs des sections et des questions, par Ollama (nomic-embed-text). Seuls les textes changés
 * depuis le snapshot précédent partent au modèle. Lancé par make embeddings-snapshot.
 */
#[AsCommand('app:docs:snapshot', 'Compute the vectors of the documentation and of the annotated questions with Ollama, into data/')]
final readonly class DocsSnapshotCommand
{
    public function __construct(
        private DocsEmbeddings $embeddings,
        #[Autowire('%kernel.project_dir%/data')]
        private string $dataDir,
        #[Autowire('%kernel.project_dir%/..')]
        private string $monorepo,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Option('Recompute every vector, not only the changed texts')] bool $all = false): int
    {
        $documents = $this->embeddings->ollama(Snapshot::DOCUMENT_PREFIX);
        $queries = $this->embeddings->ollama(Snapshot::QUERY_PREFIX);
        if (null === $documents || null === $queries) {
            $io->error('Set EMBEDDING_BASE_URL to an Ollama with nomic-embed-text:v1.5, for example: make embeddings-snapshot OLLAMA_URL=http://192.168.1.10:11434/v1');

            return 1;
        }
        $sections = DocChunker::chunk($this->monorepo);
        $questions = array_column(Questions::load($this->dataDir), 'question');
        $paths = array_flip(array_map(static fn (Section $s): string => $s->path, $sections));
        $unknown = array_diff(array_merge(...array_column(Questions::load($this->dataDir), 'relevant')), array_keys($paths));
        if ([] !== $unknown) {
            $io->error('questions.json names sections that do not exist: ' . implode(', ', array_unique($unknown)));

            return 1;
        }
        $previous = !$all && is_file($this->dataDir . '/docs.json') ? Snapshot::load($this->dataDir) : null;

        $sectionVectors = $this->vectors($io, 'sections', array_map(static fn (Section $s): string => $s->embeddingText(), $sections), $previous->documents ?? [], $documents->embed(...));
        $questionVectors = $this->vectors($io, 'questions', $questions, $previous->queries ?? [], $queries->embed(...));
        Snapshot::write($this->dataDir, $sections, $sectionVectors, $questions, $questionVectors);
        $io->success(\sprintf('%d sections and %d questions in data/ (%s, %d dimensions).', \count($sections), \count($questions), Snapshot::MODEL, Snapshot::DIMENSIONS));

        return 0;
    }

    /**
     * @param list<string>                                       $texts
     * @param array<string, list<float>>                         $known
     * @param \Closure(non-empty-list<string>): list<list<float>> $embed
     *
     * @return list<list<float>>
     */
    private function vectors(SymfonyStyle $io, string $what, array $texts, array $known, \Closure $embed): array
    {
        $missing = array_values(array_unique(array_filter($texts, static fn (string $t): bool => !isset($known[sha1($t)]))));
        $io->writeln(\sprintf('%s: %d unchanged, %d to compute', $what, \count($texts) - \count($missing), \count($missing)));
        foreach (array_chunk($missing, 32) as $batch) {
            foreach ($embed($batch) as $i => $vector) {
                $known[sha1($batch[$i])] = $vector;
            }
        }

        return array_map(static fn (string $t): array => $known[sha1($t)], $texts);
    }
}
