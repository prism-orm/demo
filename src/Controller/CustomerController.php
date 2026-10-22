<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Customer_;
use Prism\Orm\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

final class CustomerController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/customers', name: 'customer_index')]
    public function index(#[MapQueryParameter] int $page = 1): Response
    {
        $c = Customer_::as('c');

        return $this->render('customer/index.html.twig', [
            'customers' => $this->em->query($c)->orderBy($c->name->asc())->paginate(max(1, $page), 20),
        ]);
    }

    // RG-07.3 : {id} → Customer, 404 s'il n'existe pas ; ses commandes chargées au premier accès
    #[Route('/customers/{id}', name: 'customer_show', requirements: ['id' => '\d+'])]
    public function show(Customer $customer): Response
    {
        return $this->render('customer/show.html.twig', ['customer' => $customer]);
    }
}
