<?php

declare(strict_types=1);

namespace App\Tests\Docs;

use App\Docs\Evaluation;
use App\Docs\Questions;
use App\Tests\AppWebTestCase;
use Prism\Orm\EntityManagerInterface;
use Prism\Orm\Vector\EmbeddingProviderRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * La recherche documentaire de bout en bout, sur PostgreSQL (pgvector) et sans réseau : les
 * sections chargées sont périmées, db:embeddings:compute les calcule avec les vecteurs du
 * snapshot, puis hybridSearch() bat le vectoriel seul au rappel@10 (critère de sortie de la
 * phase 9).
 */
final class DocsSearchTest extends AppWebTestCase
{
    private static function command(string $name, array $input = []): string
    {
        $tester = new CommandTester(new Application(self::$kernel ?? throw new \LogicException('No kernel.'))->find($name));
        self::assertSame(0, $tester->execute($input), $tester->getDisplay());

        return $tester->getDisplay();
    }

    private static function load(): void
    {
        self::assertStringContainsString('313 section(s) inserted', self::command('app:docs:load'));
        self::assertStringContainsString('313 vector(s) computed', self::command('db:embeddings:compute', ['--em' => 'docs']));
    }

    public function testHybridSearchBeatsVectorSearchOnTheAnnotatedQuestions(): void
    {
        self::createClient();
        self::load();
        $container = static::getContainer();
        $em = $container->get('prism.entity_manager.docs');
        $providers = $container->get(EmbeddingProviderRegistry::class);
        \assert($em instanceof EntityManagerInterface && $providers instanceof EmbeddingProviderRegistry);

        $scores = new Evaluation($em, $providers->get('query'))->run(Questions::load(\dirname(__DIR__, 2) . '/data'));

        self::assertGreaterThan($scores['vector']['recall'], $scores['hybrid']['recall']);
        self::assertGreaterThan($scores['text']['recall'], $scores['hybrid']['recall']);
        self::assertGreaterThanOrEqual(0.9, $scores['hybrid']['recall']);
    }

    public function testSearchPage(): void
    {
        $client = self::createClient();
        self::load();

        $crawler = $client->request('GET', '/docs', ['q' => 'How do I avoid N+1 queries when I loop over orders and read their customer?']);
        self::assertResponseIsSuccessful();
        self::assertSame('Avoiding N+1 queries › Batch fetching', $crawler->filter('tbody tr')->first()->filter('strong')->text());
        self::assertCount(10, $crawler->filter('tbody tr'));

        // une question hors du jeu annoté, sans Ollama : pas de vecteur, la page le dit
        $crawler = $client->request('GET', '/docs', ['q' => 'Something nobody asked before']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('has no precomputed vector', $crawler->filter('.flash-error')->text());
    }

    // un texte modifié rend sa section périmée ; le recalcul ne touche qu'elle
    public function testAChangedSectionIsStaleUntilComputedAgain(): void
    {
        self::createClient();
        self::load();
        $status = self::command('db:embeddings:status', ['--em' => 'docs']);
        self::assertMatchesRegularExpression('/DocSection::\$embedding\s+313\s+0\s+nomic-embed-text:v1\.5: 313/', $status);
        $em = static::getContainer()->get('prism.entity_manager.docs');
        \assert($em instanceof EntityManagerInterface);

        self::assertSame(313, $em->getRepository(\App\Docs\Entity\DocSection::class)->markEmbeddingsStale('embedding'));
        self::assertMatchesRegularExpression('/DocSection::\$embedding\s+0\s+313/', self::command('db:embeddings:status', ['--em' => 'docs']));
        self::assertStringContainsString('313 vector(s) computed', self::command('db:embeddings:compute', ['--em' => 'docs']));
    }
}
