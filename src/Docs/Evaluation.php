<?php

declare(strict_types=1);

namespace App\Docs;

use App\Docs\Entity\DocSection;
use Prism\Orm\EntityManagerInterface;
use Prism\Orm\Vector\EmbeddingProvider;
use Prism\Orm\Vector\HybridOptions;
use Prism\Orm\Vector\HybridResult;

/**
 * Mesure la recherche sur le jeu annoté (critère de sortie de la phase 9) : pour chaque question,
 * les 10 premières sections de hybridSearch(), du classement vectoriel seul et du plein texte
 * seul. Rappel@10 : la part des sections pertinentes parmi les 10 ; MRR : l'inverse du rang de la
 * première pertinente. Les deux seuls sont hybridSearch() avec un poids nul pour l'autre liste.
 */
final readonly class Evaluation
{
    public const int K = 10;

    public const array MODES = [
        'hybrid' => [1.0, 1.0],
        'vector' => [0.0, 1.0],
        'text' => [1.0, 0.0],
    ];

    public function __construct(private EntityManagerInterface $em, private EmbeddingProvider $queries)
    {
    }

    /**
     * @param list<array{question: string, relevant: list<string>}> $questions
     *
     * @return array<key-of<self::MODES>, array{recall: float, mrr: float}>
     */
    public function run(array $questions): array
    {
        $repository = $this->em->getRepository(DocSection::class);
        $vectors = $this->queries->embed(array_column($questions, 'question'));
        $totals = array_fill_keys(array_keys(self::MODES), ['recall' => 0.0, 'mrr' => 0.0]);
        foreach ($questions as $i => ['question' => $question, 'relevant' => $relevant]) {
            foreach (self::MODES as $mode => [$textWeight, $vectorWeight]) {
                $results = $repository->hybridSearch($question, $vectors[$i], limit: self::K, options: new HybridOptions(textWeight: $textWeight, vectorWeight: $vectorWeight));
                // sans poids, une liste ne compte pas : seuls les résultats de l'autre restent
                $results = array_values(array_filter($results, static fn (HybridResult $r): bool => 'text' !== $mode || null !== $r->textRank));
                $paths = array_map(static fn (HybridResult $r): string => $r->entity->path, $results);
                $totals[$mode]['recall'] += \count(array_intersect($relevant, $paths)) / \count($relevant);
                foreach ($paths as $rank => $path) {
                    if (\in_array($path, $relevant, true)) {
                        $totals[$mode]['mrr'] += 1 / ($rank + 1);
                        break;
                    }
                }
            }
        }

        return array_map(static fn (array $t): array => ['recall' => $t['recall'] / \count($questions), 'mrr' => $t['mrr'] / \count($questions)], $totals);
    }
}
