<?php

namespace App\Controller;

use DateTime;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/order')]
class OrderController extends AbstractController
{
    // Constantes pour les statuts
    private const STATUS_EN_ATTENTE = 'en attente';
    private const STATUS_EN_COURS = 'en cours';
    private const STATUS_TERMINEE = 'terminée';
    private const STATUS_ANNULEE = 'annulée';

    #[Route('/', name: 'app_order_index')]
    public function index(OrderRepository $orderRepository): Response
    {
        // Récupérer toutes les commandes sans filtre utilisateur
        $orders = $orderRepository->findBy(
            [], // Critères vides pour récupérer toutes les commandes
            ['createdAt' => 'DESC']
        );
        
        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'statuses' => [
                self::STATUS_EN_ATTENTE => 'bg-warning',
                self::STATUS_EN_COURS => 'bg-info',
                self::STATUS_TERMINEE => 'bg-success',
                self::STATUS_ANNULEE => 'bg-danger'
            ]
        ]);
    }

    #[Route('/new', name: 'app_order_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $cart = $request->getSession()->get('cart', []);
        if (empty($cart)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('app_cart');
        }

        $order = new Order();
        $order->setUser($this->getUser());
        $order->setStatus(self::STATUS_EN_ATTENTE);
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setDeliveryAddress($request->request->get('delivery_address'));
        $order->setPhoneNumber($request->request->get('phone_number'));

        $total = 0;
        foreach ($cart as $item) {
            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order);
            $orderItem->setProduct($entityManager->getReference('App\Entity\Product', $item['product_id']));
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setPrice($item['price']);
            
            $total += $item['price'] * $item['quantity'];
            
            $entityManager->persist($orderItem);
            $order->addItem($orderItem);
        }

        $order->setTotal($total);
        
        $entityManager->persist($order);
        $entityManager->flush();

        $request->getSession()->remove('cart');

        $this->addFlash('success', 'Votre commande a été enregistrée avec succès');
        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        
        return $this->render('order/voir.html.twig', [
            'order' => $order,
            'statuses' => [
                self::STATUS_EN_ATTENTE => 'bg-warning',
                self::STATUS_EN_COURS => 'bg-info',
                self::STATUS_TERMINEE => 'bg-success',
                self::STATUS_ANNULEE => 'bg-danger'
            ]
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_order_cancel', methods: ['POST'])]
    public function cancel(Order $order, EntityManagerInterface $entityManager): Response
    {
        
        if ($order->getStatus() === self::STATUS_EN_ATTENTE || $order->getStatus() === self::STATUS_EN_COURS) {
            $order->setStatus(self::STATUS_ANNULEE);
            $entityManager->flush();

            $this->addFlash('success', 'La commande a été annulée avec succès');
        } else {
            $this->addFlash('error', 'Cette commande ne peut plus être annulée');
        }

        return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/order/{id}/status', name: 'app_order_status', methods: ['POST'])]
    public function updateStatus(Order $order, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $newStatus = $request->request->get('status');
        $allowedStatuses = ['en attente', 'en cours', 'terminée', 'annulée'];

        if (in_array($newStatus, $allowedStatuses)) {
            $order->setStatus($newStatus);
            $entityManager->flush();

            $this->addFlash('success', 'Le statut de la commande a été mis à jour');
        }

        return $this->redirectToRoute('app_order_index');
    }
}