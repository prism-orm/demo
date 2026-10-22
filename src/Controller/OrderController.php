<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\Order;
use App\Entity\Order_;
use App\Money;
use Prism\Orm\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class OrderController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * 20 commandes, leur client, leur facture et leurs lignes : 5 requêtes quel que soit le nombre
     * de commandes (la page, son compte, puis factures, clients et lignes par lot ; pilier 1).
     */
    #[Route('/orders', name: 'order_index')]
    public function index(#[MapQueryParameter] int $page = 1): Response
    {
        $o = Order_::as('o');

        return $this->render('order/index.html.twig', [
            'orders' => $this->em->query($o)->orderBy($o->placedAt->desc())->paginate(max(1, $page), 20),
        ]);
    }

    #[Route('/orders/{id}', name: 'order_show', requirements: ['id' => '\d+'])]
    public function show(Order $order): Response
    {
        return $this->render('order/show.html.twig', ['order' => $order, 'form' => $this->invoiceForm($order)]);
    }

    /**
     * Facture d'une commande : numéro unique (#[UniqueEntity]), montant calculé, écrite dans
     * une transaction.
     */
    #[Route('/orders/{id}/invoice', name: 'order_invoice', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function invoice(Order $order, Request $request, ValidatorInterface $validator): Response
    {
        if (null !== $order->invoice) {
            $this->addFlash('error', \sprintf('Order %s is already invoiced.', $order->reference));

            return $this->redirectToRoute('order_show', ['id' => $order->id]);
        }
        $form = $this->invoiceForm($order);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $invoice = new Invoice();
            $invoice->number = (string) $form->get('number')->getData();
            $invoice->issuedAt = new \DateTimeImmutable();
            $invoice->total = Money::fromCents(Money::toCents($order->total()));
            $invoice->paid = false;
            $invoice->order = $order;

            $errors = $validator->validate($invoice);
            if (0 === \count($errors)) {
                $this->em->wrapInTransaction(static function (EntityManagerInterface $em) use ($invoice): void {
                    $em->persist($invoice);
                });
                $this->addFlash('success', \sprintf('Invoice %s issued.', $invoice->number));

                return $this->redirectToRoute('order_show', ['id' => $order->id]);
            }
            foreach ($errors as $error) {
                $this->addFlash('error', (string) $error->getMessage());
            }
        }

        return $this->render('order/show.html.twig', ['order' => $order, 'form' => $form], new Response(status: 422));
    }

    private function invoiceForm(Order $order): \Symfony\Component\Form\FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('order_invoice', ['id' => $order->id]))
            ->add('number', TextType::class, ['label' => 'Invoice number', 'data' => 'INV-' . $order->reference])
            ->add('issue', SubmitType::class, ['label' => 'Issue the invoice'])
            ->getForm();
    }
}
