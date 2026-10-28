<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * Le jeu de questions annoté (data/questions.json), écrit à la main : pour chaque question, les
 * sections de la documentation qui y répondent.
 */
final class Questions
{
    private function __construct()
    {
    }

    /**
     * @return list<array{question: string, relevant: list<string>}>
     */
    public static function load(string $dataDir): array
    {
        $questions = json_decode((string) file_get_contents($dataDir . '/questions.json'), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($questions));
        $list = [];
        foreach ($questions as $row) {
            \assert(\is_array($row) && \is_string($row['question']) && \is_array($row['relevant']));
            $list[] = ['question' => $row['question'], 'relevant' => array_values(array_filter($row['relevant'], \is_string(...)))];
        }

        return $list;
    }
}
