<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Invoice;
use App\Entity\Invoice_;
use App\Tests\AppWebTestCase;
use App\Tests\Factory\OrderFactory;
use App\Tests\Factory\OrderLineFactory;
use Prism\Orm\Audit\AuditReader;
use Prism\Orm\EntityManager;
use Prism\Orm\EntityManagerInterface;

/**
 * Pilier 3 dans la démo : chaque société ne voit que ses données (acme.localhost,
 * globex.localhost), une adresse inconnue n'a pas de pages, et l'émission d'une facture est
 * journalisée (ADR 0014, ADR 0015).
 */
final class TenancyTest extends AppWebTestCase
{
    public function testEachCompanySeesOnlyItsOrders(): void
    {
        $client = static::createClient();
        $acme = OrderFactory::new(['reference' => 'ORD-ACME'])->create();
        self::company('globex');
        OrderFactory::new(['reference' => 'ORD-GLOBEX'])->create();
        self::forgetFixtures();

        $client->request('GET', '/orders');
        self::assertSelectorTextContains('tbody', 'ORD-ACME');
        self::assertSelectorTextNotContains('tbody', 'ORD-GLOBEX');

        $client->request('GET', '/orders', server: ['HTTP_HOST' => 'globex.localhost']);
        self::assertSelectorTextContains('tbody', 'ORD-GLOBEX');
        self::assertSelectorTextNotContains('tbody', 'ORD-ACME');

        // la commande d'Acme n'existe pas pour Globex
        $client->request('GET', '/orders/' . $acme->id, server: ['HTTP_HOST' => 'globex.localhost']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownCompanyHasNoPages(): void
    {
        $client = static::createClient();

        $client->request('GET', '/orders', server: ['HTTP_HOST' => 'initech.localhost']);

        self::assertResponseStatusCodeSame(404);
    }

    // RG-08.10 : la facture émise est journalisée, dans la société, avec l'identifiant de la requête
    public function testIssuedInvoiceIsAudited(): void
    {
        $client = static::createClient();
        $acme = self::company('acme');
        $line = OrderLineFactory::new(['quantity' => 2, 'unitPrice' => '7.25'])->create();
        self::forgetFixtures();
        $page = $client->request('GET', '/orders/' . $line->order->id);

        $client->submit($page->selectButton('Issue the invoice')->form(['form[number]' => 'INV-AUDIT-1']), serverParameters: ['HTTP_X_REQUEST_ID' => 'req-demo']);

        self::assertResponseRedirects();
        self::company('acme');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManager);
        $c = Invoice_::as('i');
        $invoice = $em->query($c)->where($c->number->eq('INV-AUDIT-1'))->getOneOrNull();
        self::assertNotNull($invoice);
        $history = new AuditReader($em)->history(Invoice::class, $invoice->id);
        self::assertCount(1, $history);
        self::assertSame(['insert', (string) $acme, 'req-demo'], [$history[0]->action, $history[0]->tenantId, $history[0]->requestId]);
        self::assertSame([null, 'INV-AUDIT-1'], $history[0]->changes['number']);
    }
}
