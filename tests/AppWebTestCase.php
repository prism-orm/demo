<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Company;
use App\Entity\Company_;
use Prism\Orm\EntityManagerInterface;
use Prism\Orm\Tenancy\StackTenantContext;
use Prism\TestPack\QueryCountAssertions;
use Prism\TestPack\ResetDatabase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les tests de pages de la démo : base remise à zéro à chaque test (RG-07.6), requêtes comptées.
 * Le client visite acme.localhost ; les factories écrivent dans la société d'Acme.
 */
abstract class AppWebTestCase extends WebTestCase
{
    use QueryCountAssertions;
    use ResetDatabase {
        createClient as private resetDatabaseClient;
    }

    /**
     * Le client de ResetDatabase, sur acme.localhost.
     *
     * @param array<mixed> $options
     * @param array<mixed> $server
     */
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $client = self::resetDatabaseClient($options, $server + ['HTTP_HOST' => 'acme.localhost']);
        self::company('acme');

        return $client;
    }

    /**
     * La société $slug, créée au besoin, devient le tenant des factories (pilier 3). kernel.reset
     * l'oublie après chaque requête : à rappeler avant de créer d'autres données.
     */
    protected static function company(string $slug): int
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $c = Company_::as('c');
        $company = $em->query($c)->where($c->slug->eq($slug))->getOneOrNull();
        if (null === $company) {
            $company = new Company();
            $company->slug = $slug;
            $company->name = ucfirst($slug);
            $em->persist($company);
            $em->flush();
        }
        $tenants = static::getContainer()->get(StackTenantContext::class);
        \assert($tenants instanceof StackTenantContext);
        $tenants->setTenantId((int) $company->id);

        return (int) $company->id;
    }

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
