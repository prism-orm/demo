<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * Les vecteurs précalculés de la démo RAG (ADR 0018) : les sections de la documentation et les
 * questions du jeu annoté, calculés une fois avec nomic-embed-text par app:docs:snapshot, puis
 * versionnés dans data/. docs.json porte les textes et leurs hash, vectors.f32 les vecteurs en
 * float32, sections puis questions, dans l'ordre.
 */
final readonly class Snapshot
{
    public const string MODEL = 'nomic-embed-text:v1.5';

    public const int DIMENSIONS = 768;

    public const string DOCUMENT_PREFIX = 'search_document: ';

    public const string QUERY_PREFIX = 'search_query: ';

    /**
     * @param list<Section>              $sections
     * @param array<string, list<float>> $documents hash du texte d'une section → vecteur
     * @param array<string, list<float>> $queries   hash d'une question → vecteur
     */
    public function __construct(
        public array $sections,
        public array $documents,
        public array $queries,
    ) {
    }

    public static function load(string $dir): self
    {
        $data = json_decode((string) file_get_contents($dir . '/docs.json'), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($data) && \is_array($data['sections']) && \is_array($data['questions']));
        $vectors = self::unpack((string) file_get_contents($dir . '/vectors.f32'));
        $sections = [];
        $documents = [];
        foreach (array_values($data['sections']) as $i => $row) {
            \assert(\is_array($row) && \is_string($row['path']) && \is_string($row['title']) && \is_string($row['content']) && \is_string($row['hash']));
            $sections[] = new Section($row['path'], $row['title'], $row['content']);
            $documents[$row['hash']] = $vectors[$i];
        }
        $queries = [];
        foreach (array_values($data['questions']) as $i => $hash) {
            \assert(\is_string($hash));
            $queries[$hash] = $vectors[\count($sections) + $i];
        }

        return new self($sections, $documents, $queries);
    }

    /**
     * @param list<Section>     $sections
     * @param list<list<float>> $sectionVectors
     * @param list<string>      $questions
     * @param list<list<float>> $questionVectors
     */
    public static function write(string $dir, array $sections, array $sectionVectors, array $questions, array $questionVectors): void
    {
        $json = [
            'model' => self::MODEL,
            'dimensions' => self::DIMENSIONS,
            'prefixes' => ['document' => self::DOCUMENT_PREFIX, 'query' => self::QUERY_PREFIX],
            'sections' => array_map(static fn (Section $s): array => ['path' => $s->path, 'title' => $s->title, 'content' => $s->content, 'hash' => $s->hash()], $sections),
            'questions' => array_map(sha1(...), $questions),
        ];
        file_put_contents($dir . '/docs.json', json_encode($json, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR) . "\n");
        file_put_contents($dir . '/vectors.f32', implode('', array_map(static fn (array $v): string => pack('g*', ...$v), [...$sectionVectors, ...$questionVectors])));
    }

    /**
     * @return list<list<float>>
     */
    private static function unpack(string $binary): array
    {
        $vectors = [];
        foreach (str_split($binary, self::DIMENSIONS * 4) as $chunk) {
            $values = unpack('g*', $chunk);
            \assert(\is_array($values));
            $vectors[] = array_values(array_map(static fn (mixed $x): float => (float) $x, $values));
        }

        return $vectors;
    }
}
