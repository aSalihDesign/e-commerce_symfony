<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Form\OrderType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CheckoutController extends AbstractController
{
    private const STATUS_EN_ATTENTE = 'en attente';

    #[Route('/checkout', name: 'checkout')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérifier si l'utilisateur est connecté
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        // Récupérer le panier de la session
        $cart = $request->getSession()->get('cart', []);
        
        if (empty($cart)) {
            $this->addFlash('error', 'Votre panier est vide');
            return $this->redirectToRoute('cart_show');
        }

        // Calculer le total
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        // Créer une nouvelle commande
        $order = new Order();
        $order->setUser($this->getUser());
        $order->setCountry('Senegal'); // Valeur par défaut

        // Créer le formulaire
        $form = $this->createForm(OrderType::class, $order);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $order->setStatus(self::STATUS_EN_ATTENTE);
            $order->setTotal($total);

            // Ajouter les items du panier
            foreach ($cart as $item) {
                $orderItem = new OrderItem();
                $orderItem->setOrderRef($order);
                $orderItem->setProduct($entityManager->getReference('App\Entity\Product', $item['product_id']));
                $orderItem->setQuantity($item['quantity']);
                $orderItem->setPrice($item['price']);
                
                $entityManager->persist($orderItem);
                $order->addItem($orderItem);
            }

            $entityManager->persist($order);
            $entityManager->flush();

            // Vider le panier
            $request->getSession()->remove('cart');

            return $this->redirectToRoute('order_success', ['id' => $order->getId()]);
        }

        return $this->render('checkout/index.html.twig', [
            'form' => $form->createView(),
            'cart' => $cart,
            'total' => $total
        ]);
    }

    #[Route('/order/success/{id}', name: 'order_success')]
    public function success(Order $order): Response
    {
        // Vérifier que l'utilisateur actuel est bien le propriétaire de la commande
        if ($order->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order
        ]);
    }
}