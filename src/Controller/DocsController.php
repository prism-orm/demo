<?php

declare(strict_types=1);

namespace App\Controller;

use App\Docs\Entity\DocSection;
use App\Docs\Questions;
use Prism\Orm\EntityManagerInterface;
use Prism\Orm\Exception\EmbeddingException;
use Prism\Orm\Vector\EmbeddingProviderRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recherche dans la documentation de Prism (pilier 4) : hybridSearch() fusionne le plein texte et
 * la proximité des vecteurs. Les questions du jeu annoté ont leur vecteur dans le snapshot ; une
 * autre question demande Ollama (EMBEDDING_BASE_URL).
 */
final class DocsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $docsEntityManager,
        private readonly EmbeddingProviderRegistry $providers,
        #[Autowire('%kernel.project_dir%/data')]
        private readonly string $dataDir,
    ) {
    }

    #[Route('/docs', name: 'docs_search')]
    public function search(Request $request): Response
    {
        $query = trim($request->query->getString('q'));
        $results = [];
        $error = null;
        if ('' !== $query) {
            try {
                $vector = $this->providers->get('query')->embed([$query])[0];
                $results = $this->docsEntityManager->getRepository(DocSection::class)->hybridSearch($query, $vector, limit: 10);
            } catch (EmbeddingException) {
                $error = 'This question has no precomputed vector. Pick one of the sample questions, or set EMBEDDING_BASE_URL to an Ollama with nomic-embed-text to ask your own.';
            }
        }

        return $this->render('docs/search.html.twig', [
            'query' => $query,
            'results' => $results,
            'error' => $error,
            'samples' => \array_slice(array_column(Questions::load($this->dataDir), 'question'), 0, 12),
        ]);
    }
}
