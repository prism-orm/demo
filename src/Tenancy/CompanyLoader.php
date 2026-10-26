<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Entity\Company_;
use Prism\Bundle\Tenancy\TenantLoader;
use Prism\Orm\EntityManagerInterface;

/**
 * Le sous-domaine de la requête (acme.localhost) → l'identifiant de la société ; un sous-domaine
 * inconnu n'a pas de tenant, et ses pages ne lisent rien (RG-08.4).
 */
final readonly class CompanyLoader implements TenantLoader
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function load(string $identifier): ?int
    {
        $c = Company_::as('c');

        return $this->em->query($c)->where($c->slug->eq($identifier))->getOneOrNull()?->id;
    }
}
