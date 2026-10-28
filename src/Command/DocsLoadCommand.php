<?php

declare(strict_types=1);

namespace App\Command;

use App\Docs\Entity\DocSection;
use App\Docs\Entity\DocSection_;
use App\Docs\Snapshot;
use Prism\Orm\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Met la base documentaire au niveau des sections du snapshot : une section nouvelle est insérée,
 * une section dont le texte a changé est mise à jour, une section disparue est supprimée. Les
 * deux premières ont un vecteur périmé (#[EmbeddedVector]) : db:embeddings:compute --em=docs les
 * calcule ensuite.
 */
#[AsCommand('app:docs:load', 'Load the documentation sections of data/ into the docs database')]
final readonly class DocsLoadCommand
{
    public function __construct(
        private EntityManagerInterface $docsEntityManager,
        #[Autowire('%kernel.project_dir%/data')]
        private string $dataDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $em = $this->docsEntityManager;
        $d = DocSection_::as('d');
        $existing = [];
        foreach ($em->query($d)->getResult() as $section) {
            $existing[$section->path] = $section;
        }
        [$inserted, $updated] = [0, 0];
        foreach (Snapshot::load($this->dataDir)->sections as $section) {
            $row = $existing[$section->path] ?? null;
            unset($existing[$section->path]);
            if (null === $row) {
                $em->persist(new DocSection($section->path, $section->title, $section->content));
                ++$inserted;
            } elseif ($row->title !== $section->title || $row->content !== $section->content) {
                $row->title = $section->title;
                $row->content = $section->content;
                ++$updated;
            }
        }
        foreach ($existing as $gone) {
            $em->remove($gone);
        }
        $em->flush();
        $io->success(\sprintf('%d section(s) inserted, %d updated, %d deleted. Their vectors are stale: run db:embeddings:compute --em=docs.', $inserted, $updated, \count($existing)));

        return 0;
    }
}
