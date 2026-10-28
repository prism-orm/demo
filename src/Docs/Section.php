<?php

declare(strict_types=1);

namespace App\Docs;

/**
 * Une section de la documentation : une page du site, un titre de niveau 2 ou 3, son texte. Le
 * chemin « page#ancre » l'identifie dans le jeu de questions annoté.
 */
final readonly class Section
{
    public function __construct(
        public string $path,
        public string $title,
        public string $content,
    ) {
    }

    /**
     * Le texte que l'EmbeddingUpdater envoie au fournisseur pour #[EmbeddedVector(source:
     * ['title', 'content'])] : les sources non vides, nettoyées, séparées par une ligne vide.
     */
    public function embeddingText(): string
    {
        return implode("\n\n", array_filter([trim($this->title), trim($this->content)], static fn (string $s): bool => '' !== $s));
    }

    public function hash(): string
    {
        return sha1($this->embeddingText());
    }
}
