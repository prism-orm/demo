<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice_;
use App\Report\CustomerRevenue;
use Prism\Orm\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ReportController extends AbstractController
{
    /**
     * Chiffre d'affaires facturé par client : une requête groupée, projetée dans un DTO
     * (selectInto, RG-05.6), sans chaîne de nom de champ.
     */
    #[Route('/reports/revenue', name: 'report_revenue')]
    public function revenue(EntityManagerInterface $em): Response
    {
        $i = Invoice_::as('i');
        $o = $i->order->as('o');
        $c = $o->customer->as('c');

        $rows = $em->query($i)->join($o)->join($c)
            ->groupBy($c->id, $c->name)
            ->orderBy($i->total->sum()->desc(), $c->name->asc())
            ->selectInto(CustomerRevenue::class, customer: $c->name, invoices: $i->id->count(), total: $i->total->sum(), lastInvoice: $i->issuedAt->max())
            ->getResult();

        return $this->render('report/revenue.html.twig', ['rows' => $rows]);
    }
}
