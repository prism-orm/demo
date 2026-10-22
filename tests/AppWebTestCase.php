<?php

declare(strict_types=1);

namespace App\Tests;

use Prism\Orm\EntityManagerInterface;
use Prism\TestPack\QueryCountAssertions;
use Prism\TestPack\ResetDatabase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les tests de pages de la démo : base remise à zéro à chaque test (RG-07.6), requêtes comptées.
 */
abstract class AppWebTestCase extends WebTestCase
{
    use QueryCountAssertions;
    use ResetDatabase;

    /**
     * Les entités créées par les factories restent dans l'identity map : la page les relit
     * depuis la base, comme dans une vraie requête.
     */
    protected static function forgetFixtures(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $em->clear();
    }
}
