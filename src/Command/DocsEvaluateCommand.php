<?php

declare(strict_types=1);

namespace App\Command;

use App\Docs\Evaluation;
use App\Docs\Questions;
use Prism\Orm\EntityManagerInterface;
use Prism\Orm\Vector\EmbeddingProviderRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Le rappel@10 et le MRR de hybridSearch(), du vectoriel seul et du plein texte seul sur le jeu
 * annoté (data/questions.json), avec les vecteurs du snapshot : sans réseau, reproductible.
 */
#[AsCommand('app:docs:evaluate', 'Measure recall@10 of hybrid, vector and full-text search on the annotated questions')]
final readonly class DocsEvaluateCommand
{
    public function __construct(
        private EntityManagerInterface $docsEntityManager,
        private EmbeddingProviderRegistry $providers,
        #[Autowire('%kernel.project_dir%/data')]
        private string $dataDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $questions = Questions::load($this->dataDir);
        $scores = new Evaluation($this->docsEntityManager, $this->providers->get('query'))->run($questions);
        $io->table(['Search', 'Recall@10', 'MRR@10'], array_map(
            static fn (string $mode, array $s): array => [$mode, number_format($s['recall'], 3), number_format($s['mrr'], 3)],
            array_keys($scores),
            $scores,
        ));
        $io->writeln(\sprintf('%d questions, %d relevant sections in all.', \count($questions), array_sum(array_map(static fn (array $q): int => \count($q['relevant']), $questions))));

        return 0;
    }
}
