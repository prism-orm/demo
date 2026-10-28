<?php

declare(strict_types=1);

namespace App\Docs\Entity;

use Prism\Orm\Mapping as Prism;
use Prism\Orm\Vector\Distance;
use Prism\Orm\Vector\Vector;

/**
 * Une section de la documentation de Prism, cherchée par hybridSearch() (pilier 4) : son titre
 * et son texte en plein texte, son vecteur calculé par le fournisseur « default » quand ils
 * changent (#[EmbeddedVector]), jamais lu dans une liste (paresseux).
 */
#[Prism\Entity]
#[Prism\FullTextIndex(fields: ['title', 'content'], language: 'english')]
final class DocSection
{
    #[Prism\Id, Prism\GeneratedValue(Prism\GenerationStrategy::Identity)]
    public ?int $id = null;

    #[Prism\Column(dimensions: 768)]
    #[Prism\VectorIndex(distance: Distance::Cosine)]
    #[Prism\EmbeddedVector(source: ['title', 'content'])]
    public ?Vector $embedding = null;

    public function __construct(
        // « page#ancre » sur le site : bundle/symfony#embeddings
        #[Prism\Column(length: 200, unique: true)]
        public string $path,
        #[Prism\Column(length: 255)]
        public string $title,
        #[Prism\Column(type: 'text')]
        public string $content,
    ) {
    }
}
