<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\OrderLine;
use App\Entity\Product;
use App\Money;
use Prism\Orm\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Un jeu de données de démonstration : clients, produits, commandes et leurs lignes, factures
 * pour une partie des commandes. Déterministe (mt_srand) : deux démos montrent les mêmes chiffres.
 */
#[AsCommand('app:seed', 'Fill the demo database with customers, products, orders and invoices')]
final readonly class SeedCommand
{
    private const array CITIES = ['Paris', 'Lyon', 'Marseille', 'Lille', 'Nantes', 'Bordeaux', 'Toulouse'];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Number of customers')] int $customers = 50,
        #[Option('Number of orders')] int $orders = 500,
    ): int {
        mt_srand(42);

        $products = [];
        for ($i = 1; $i <= 30; ++$i) {
            $product = new Product();
            $product->sku = \sprintf('SKU-%03d', $i);
            $product->name = 'Product ' . $i;
            $product->price = Money::fromCents(mt_rand(500, 50_000));
            $this->em->persist($product);
            $products[] = $product;
        }

        $people = [];
        for ($i = 1; $i <= $customers; ++$i) {
            $customer = new Customer();
            $customer->name = 'Customer ' . $i;
            $customer->email = \sprintf('customer%d@example.test', $i);
            $customer->city = self::CITIES[$i % \count(self::CITIES)];
            $this->em->persist($customer);
            $people[] = $customer;
        }
        $this->em->flush();

        $start = new \DateTimeImmutable('2026-01-01 09:00:00');
        for ($i = 1; $i <= $orders; ++$i) {
            $order = new Order();
            $order->reference = \sprintf('ORD-%05d', $i);
            $order->placedAt = $start->modify(\sprintf('+%d hours', $i * 7));
            $order->status = 0 === $i % 3 ? 'shipped' : 'open';
            $order->customer = $people[mt_rand(0, \count($people) - 1)];
            $this->em->persist($order);
            for ($n = mt_rand(1, 5); $n > 0; --$n) {
                $line = new OrderLine();
                $line->order = $order;
                $line->product = $products[mt_rand(0, \count($products) - 1)];
                $line->quantity = mt_rand(1, 10);
                $line->unitPrice = $line->product->price;
                $order->lines->add($line);
                $this->em->persist($line);
            }
            if (0 === $i % 3) {
                $invoice = new Invoice();
                $invoice->number = \sprintf('INV-%05d', $i);
                $invoice->issuedAt = $order->placedAt->modify('+1 day');
                $invoice->total = $order->total();
                $invoice->paid = 0 === $i % 2;
                $invoice->order = $order;
                $this->em->persist($invoice);
            }
            if (0 === $i % 100) {
                $this->em->flush();
                $this->em->clear();
                $products = array_map(fn (Product $p): Product => $this->em->getReference(Product::class, (int) $p->id), $products);
                $people = array_map(fn (Customer $c): Customer => $this->em->getReference(Customer::class, (int) $c->id), $people);
            }
        }
        $this->em->flush();

        $io->success(\sprintf('%d customers, 30 products, %d orders and their lines, %d invoices.', $customers, $orders, intdiv($orders, 3)));

        return 0;
    }
}
