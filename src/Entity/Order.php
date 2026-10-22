<?php

declare(strict_types=1);

namespace App\Entity;

use App\Money;
use App\Repository\OrderRepository;
use Prism\Orm\Mapping as Prism;
use Prism\Orm\Collection\ArrayCollection;
use Prism\Orm\Collection\Collection;

#[Prism\Entity(repositoryClass: OrderRepository::class)]
final class Order
{
    #[Prism\Id, Prism\GeneratedValue]
    public ?int $id = null;

    #[Prism\Column(length: 20)]
    public string $reference;

    #[Prism\Column]
    public \DateTimeImmutable $placedAt;

    #[Prism\Column(length: 20)]
    public string $status;

    #[Prism\ManyToOne(inversedBy: 'orders')]
    public Customer $customer;

    /** @var Collection<int, OrderLine> */
    #[Prism\OneToMany(targetEntity: OrderLine::class, mappedBy: 'order')]
    public Collection $lines;

    #[Prism\OneToOne(mappedBy: 'order')]
    public ?Invoice $invoice = null;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function total(): string
    {
        $cents = 0;
        foreach ($this->lines as $line) {
            $cents += Money::toCents($line->total());
        }

        return Money::fromCents($cents);
    }
}
