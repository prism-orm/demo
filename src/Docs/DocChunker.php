<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * Découpe la documentation de Prism en sections (ADR 0018) : chaque page publiée par le site
 * (website/ et packages/*\/docs, mêmes URL que VitePress), coupée à ses titres de niveau 2 et 3 ;
 * le texte avant le premier titre forme l'introduction de la page. Une section trop longue pour
 * le modèle est coupée entre deux paragraphes, en parties numérotées.
 */
final class DocChunker
{
    /** caractères au plus par section : nomic-embed-text lit 2 048 jetons */
    private const int MAX_LENGTH = 3000;

    /**
     * @return list<Section> dans l'ordre des pages, puis du texte
     */
    public static function chunk(string $monorepo): array
    {
        $sections = [];
        foreach (self::pages($monorepo) as $page => $file) {
            array_push($sections, ...self::sections($page, (string) file_get_contents($file)));
        }

        return $sections;
    }

    /**
     * @return array<string, string> URL de la page → fichier, triés
     */
    private static function pages(string $monorepo): array
    {
        $pages = [];
        foreach ([...self::find($monorepo . '/website'), ...self::find($monorepo . '/packages')] as $file) {
            $relative = substr($file, \strlen($monorepo) + 1);
            $page = match (true) {
                1 === preg_match('#^website/(?!tests/)(.+)\.md$#', $relative, $m) => $m[1],
                1 === preg_match('#^packages/[^/]+/docs/reference/(.+)\.md$#', $relative, $m) => 'reference/' . $m[1],
                1 === preg_match('#^packages/([^/]+)/docs/(.+)\.md$#', $relative, $m) => $m[1] . '/' . $m[2],
                default => null,
            };
            if (null !== $page && 'index' !== $page) {
                $pages[$page] = $file;
            }
        }
        ksort($pages);

        return $pages;
    }

    /**
     * @return list<string>
     */
    private static function find(string $dir): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            static fn (\SplFileInfo $f): bool => !\in_array($f->getFilename(), ['node_modules', 'vendor', 'tests', '.vitepress', 'public'], true),
        ));
        foreach ($iterator as $file) {
            \assert($file instanceof \SplFileInfo);
            if ('md' === $file->getExtension() && !\in_array($file->getFilename(), ['README.md', 'CHANGELOG.md'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<Section>
     */
    private static function sections(string $page, string $markdown): array
    {
        $markdown = (string) preg_replace('/\A---\n.*?\n---\n/s', '', str_replace("\r\n", "\n", $markdown));
        $pageTitle = $page;
        $h2 = null;
        $current = ['', null];
        $lines = [];
        $sections = [];
        $fence = false;
        foreach (explode("\n", $markdown) as $line) {
            if (str_starts_with($line, '```')) {
                $fence = !$fence;
            }
            if (!$fence && 1 === preg_match('/^(#{1,3}) +(.+?) *$/', $line, $m)) {
                $text = self::plain($m[2]);
                if ('#' === $m[1]) {
                    $pageTitle = $text;
                    continue;
                }
                array_push($sections, ...self::flush($page, $pageTitle, $current, $lines));
                $lines = [];
                if ('##' === $m[1]) {
                    $h2 = $text;
                    $current = [$text, self::slug($text)];
                } else {
                    $current = [(null === $h2 ? '' : $h2 . ' › ') . $text, self::slug($text)];
                }
                continue;
            }
            // les conteneurs VitePress (::: tip) : leur texte seul
            if (1 === preg_match('/^:::/', $line)) {
                continue;
            }
            $lines[] = $line;
        }
        array_push($sections, ...self::flush($page, $pageTitle, $current, $lines));

        return $sections;
    }

    /**
     * @param array{string, ?string} $heading titre (h2 › h3) et ancre ; null : introduction
     * @param list<string>           $lines
     *
     * @return list<Section>
     */
    private static function flush(string $page, string $pageTitle, array $heading, array $lines): array
    {
        $content = trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
        if ('' === $content) {
            return [];
        }
        [$title, $anchor] = $heading;
        $path = $page . (null === $anchor ? '' : '#' . $anchor);
        $title = '' === $title ? $pageTitle : $pageTitle . ' › ' . $title;
        $parts = self::split($content);
        $sections = [];
        foreach ($parts as $i => $part) {
            $sections[] = new Section(0 === $i ? $path : $path . '~' . ($i + 1), $title, $part);
        }

        return $sections;
    }

    /**
     * @return list<string>
     */
    private static function split(string $content): array
    {
        if (mb_strlen($content) <= self::MAX_LENGTH) {
            return [$content];
        }
        $parts = [];
        $part = '';
        foreach (explode("\n\n", $content) as $paragraph) {
            if ('' !== $part && mb_strlen($part) + mb_strlen($paragraph) + 2 > self::MAX_LENGTH) {
                $parts[] = $part;
                $part = '';
            }
            $part = '' === $part ? $paragraph : $part . "\n\n" . $paragraph;
        }
        $parts[] = $part;

        return $parts;
    }

    // le titre sans sa mise en forme Markdown
    private static function plain(string $heading): string
    {
        return trim((string) preg_replace('/\{#[^}]*\}\s*$/', '', $heading));
    }

    /**
     * L'ancre que VitePress donne à un titre : minuscules, ponctuation remplacée par des tirets.
     */
    public static function slug(string $heading): string
    {
        $slug = (string) preg_replace('/[\s~`!@#$%^&*()\-_+=\[\]{}|\\\;:"\'“”‘’<>,.?\/]+/u', '-', mb_strtolower(trim($heading)));
        $slug = trim($slug, '-');

        return 1 === preg_match('/^\d/', $slug) ? '_' . $slug : $slug;
    }
}
