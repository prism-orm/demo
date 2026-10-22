<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerRepository;
use Prism\Bundle\Validator\UniqueEntity;
use Prism\Orm\Mapping as Prism;
use Prism\Orm\Collection\ArrayCollection;
use Prism\Orm\Collection\Collection;

#[Prism\Entity(repositoryClass: CustomerRepository::class)]
#[UniqueEntity(fields: ['email'])]
final class Customer
{
    #[Prism\Id, Prism\GeneratedValue]
    public ?int $id = null;

    #[Prism\Column(length: 120)]
    public string $name;

    #[Prism\Column(length: 180, unique: true)]
    public string $email;

    #[Prism\Column(length: 80)]
    public ?string $city = null;

    /** @var Collection<int, Order> */
    #[Prism\OneToMany(targetEntity: Order::class, mappedBy: 'customer')]
    public Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }
}
