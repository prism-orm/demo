<?php

declare(strict_types=1);

namespace App\Tests\Docs;

use App\Docs\DocChunker;
use App\Docs\Questions;
use App\Docs\Section;
use App\Docs\Snapshot;
use PHPUnit\Framework\TestCase;

/**
 * Le snapshot de data/ correspond à la documentation et au jeu de questions actuels (ADR 0018) :
 * une page modifiée sans make embeddings-snapshot échoue ici, sans réseau.
 */
final class SnapshotTest extends TestCase
{
    private const string DATA = __DIR__ . '/../../data';

    public function testSectionsMatchTheDocumentation(): void
    {
        $snapshot = Snapshot::load(self::DATA);
        $live = DocChunker::chunk(\dirname(__DIR__, 3));
        $hash = static fn (Section $s): string => $s->path . ' ' . $s->hash();

        self::assertSame(array_map($hash, $live), array_map($hash, $snapshot->sections), 'the documentation changed: run make embeddings-snapshot');
        self::assertCount(\count($live), $snapshot->documents, 'one vector per distinct section');
    }

    public function testQuestionsHaveTheirVectorAndExistingSections(): void
    {
        $snapshot = Snapshot::load(self::DATA);
        $paths = array_map(static fn (Section $s): string => $s->path, $snapshot->sections);
        foreach (Questions::load(self::DATA) as ['question' => $question, 'relevant' => $relevant]) {
            self::assertArrayHasKey(sha1($question), $snapshot->queries, $question . ': run make embeddings-snapshot');
            self::assertCount(Snapshot::DIMENSIONS, $snapshot->queries[sha1($question)]);
            self::assertNotSame([], $relevant);
            self::assertSame([], array_diff($relevant, $paths), $question);
        }
    }

    public function testVectorsAreNormalized(): void
    {
        foreach (Snapshot::load(self::DATA)->documents as $vector) {
            self::assertEqualsWithDelta(1.0, sqrt(array_sum(array_map(static fn (float $x): float => $x * $x, $vector))), 1e-3);
        }
    }
}
