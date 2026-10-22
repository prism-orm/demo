<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Invoice;
use App\Tests\AppWebTestCase;
use App\Tests\Factory\CustomerFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\OrderFactory;
use App\Tests\Factory\OrderLineFactory;
use Prism\Orm\EntityManagerInterface;

final class OrderPagesTest extends AppWebTestCase
{
    /**
     * $count commandes de 3 lignes, une facture pour une sur deux.
     */
    private static function orders(int $count): void
    {
        $customer = CustomerFactory::new()->create();
        for ($i = 0; $i < $count; ++$i) {
            $order = OrderFactory::new(['customer' => $customer])->create();
            OrderLineFactory::new(['order' => $order, 'quantity' => 3, 'unitPrice' => '4.50'])->many(3);
            if (0 === $i % 2) {
                InvoiceFactory::new(['order' => $order])->create();
            }
        }
        self::forgetFixtures();
    }

    public function testListShowsTheCustomerTheInvoiceAndTheTotal(): void
    {
        $client = static::createClient();
        self::orders(3);

        $client->request('GET', '/orders');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(3, 'tbody tr');
        self::assertSelectorTextContains('tbody tr td.amount', '40.50', '3 lines of 3 × 4.50');
        self::assertSelectorTextContains('main', 'INV-');
    }

    /**
     * Pilier 1 : une page de 20 commandes coûte 5 requêtes, quel que soit le nombre de commandes —
     * la page, son compte, puis les factures, les clients et les lignes de toute la page par lot.
     * (Une première page incomplète se passe du compte : 4.)
     */
    public function testListCostsTheSameNumberOfQueriesWhateverTheNumberOfOrders(): void
    {
        $client = static::createClient();
        self::orders(2);
        $this->assertQueryCount(4, static fn () => $client->request('GET', '/orders'), message: 'a page that is not full knows its total');

        self::orders(19);
        $this->assertQueryCount(5, static fn () => $client->request('GET', '/orders'));
        self::orders(40);
        $this->assertQueryCount(5, static fn () => $client->request('GET', '/orders'), message: 'no N+1: 61 orders, 20 on the page, still 5 queries');
    }

    public function testShowAndUnknownOrder(): void
    {
        $client = static::createClient();
        $line = OrderLineFactory::new(['quantity' => 2, 'unitPrice' => '7.25'])->create();
        self::forgetFixtures();

        $client->request('GET', '/orders/' . $line->order->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tfoot', '14.50');

        $client->request('GET', '/orders/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvoiceIsIssuedOnce(): void
    {
        $client = static::createClient();
        $line = OrderLineFactory::new(['quantity' => 2, 'unitPrice' => '7.25'])->create();
        self::forgetFixtures();
        $page = $client->request('GET', '/orders/' . $line->order->id);

        $client->submit($page->selectButton('Issue the invoice')->form(['form[number]' => 'INV-TEST-1']));

        self::assertResponseRedirects('/orders/' . $line->order->id);
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Invoice INV-TEST-1 issued.');
        self::assertSelectorTextContains('main', '14.50 (not paid)');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        self::assertSame(1, $em->getRepository(Invoice::class)->count());
    }

    // #[UniqueEntity] : un numéro déjà pris est refusé, rien n'est écrit
    public function testInvoiceNumberIsUnique(): void
    {
        $client = static::createClient();
        InvoiceFactory::new(['number' => 'INV-TAKEN'])->create();
        $line = OrderLineFactory::new()->create();
        self::forgetFixtures();
        $page = $client->request('GET', '/orders/' . $line->order->id);

        $client->submit($page->selectButton('Issue the invoice')->form(['form[number]' => 'INV-TAKEN']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'This value is already used.');
    }
}
