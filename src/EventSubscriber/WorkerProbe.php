<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Prism\Dbal\Connection;
use Prism\Orm\EntityManagerInterface;
use Prism\Orm\UnitOfWork\UnitOfWork;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Mesure du worker FrankenPHP (make worker-test), seulement avec PRISM_WORKER_PROBE=1 : la
 * mémoire du processus, le nombre de requêtes qu'il a servies, et l'état que la requête trouve
 * en arrivant (entités suivies, transaction ouverte), qui doit être vide (RG-07.5).
 */
final class WorkerProbe
{
    private int $requests = 0;

    private int $managedAtStart = 0;

    private int $transactionAtStart = 0;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
        #[Autowire(env: 'default::bool:PRISM_WORKER_PROBE')]
        private readonly ?bool $enabled = false,
    ) {
    }

    // après le gardien des connexions (256) et le reset du kernel
    #[AsEventListener(priority: 100)]
    public function onRequest(RequestEvent $event): void
    {
        if (true !== $this->enabled || !$event->isMainRequest()) {
            return;
        }
        ++$this->requests;
        // diagnostic seulement : l'identity map n'est pas exposée par l'UnitOfWork
        $this->managedAtStart = \count((array) new \ReflectionProperty(UnitOfWork::class, 'entities')->getValue($this->em->getUnitOfWork()));
        $this->transactionAtStart = $this->connection->getTransactionNestingLevel();
    }

    #[AsEventListener(priority: -1024)]
    public function onResponse(ResponseEvent $event): void
    {
        if (true !== $this->enabled || !$event->isMainRequest()) {
            return;
        }
        $headers = $event->getResponse()->headers;
        $headers->set('X-Prism-Pid', (string) getmypid());
        $headers->set('X-Prism-Requests', (string) $this->requests);
        $headers->set('X-Prism-Memory', (string) memory_get_usage());
        $headers->set('X-Prism-Managed-At-Start', (string) $this->managedAtStart);
        $headers->set('X-Prism-Transaction-At-Start', (string) $this->transactionAtStart);
    }
}
