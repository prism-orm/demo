<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Prism\Orm\Exception\MissingTenantException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Une adresse sans société connue (pilier 3, fail-closed) : la page n'existe pas, plutôt qu'une
 * erreur 500.
 */
#[AsEventListener]
final class UnknownCompanySubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        if ($event->getThrowable() instanceof MissingTenantException) {
            $event->setThrowable(new NotFoundHttpException('Unknown company: use acme.localhost or globex.localhost.', $event->getThrowable()));
        }
    }
}
