<?php

declare(strict_types=1);

namespace App\Tests\Docs;

use App\Docs\Questions;
use App\Docs\Section;
use App\Docs\Snapshot;
use PHPUnit\Framework\TestCase;

/**
 * Le snapshot de data/ est cohérent, sans réseau : un vecteur par section, chaque question avec
 * le sien et des sections existantes. Il est tiré des pages d'une version de Prism et refait à
 * chaque version mineure (make docs-snapshot PRISM=../prism, ADR 0023).
 */
final class SnapshotTest extends TestCase
{
    private const string DATA = __DIR__ . '/../../data';

    public function testEverySectionHasItsVector(): void
    {
        $snapshot = Snapshot::load(self::DATA);

        self::assertNotSame([], $snapshot->sections);
        foreach ($snapshot->sections as $section) {
            self::assertArrayHasKey($section->hash(), $snapshot->documents, $section->path);
            self::assertCount(Snapshot::DIMENSIONS, $snapshot->documents[$section->hash()]);
        }
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
