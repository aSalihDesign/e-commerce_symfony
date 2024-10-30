<?php

namespace App\Controller;

use DateTime;
use App\Entity\Order;
use App\Entity\Product;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\OrderItem;

class CartController extends AbstractController
{

    public function initializeCart(SessionInterface $session)
    {
        if (!$session->has('cart')) {
            $session->set('cart', []);
        }
    }

    #[Route('/cart', name: 'cart_show')]
    public function showCart(SessionInterface $session)
    {
        // Récupérer le panier de la session
        $cart = $session->get('cart', []);

        // Calculer le total du panier
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'total' => $total,
        ]);
    }
    
    #[Route('/cart/add/{productId}', name: 'cart_add')]
    public function addToCart(Request $request, SessionInterface $session, $productId, ManagerRegistry $doctrine): Response
    {
        // Initialiser le panier s'il n'existe pas
        $this->initializeCart($session);

        // Récupérer le panier actuel depuis la session
        $cart = $session->get('cart', []);

        // Récupérer le produit depuis la base de données
        $product = $doctrine->getRepository(Product::class)->find($productId);
        if (!$product) {
            throw $this->createNotFoundException('Le produit n\'existe pas.');
        }

        // Ajouter le produit au panier ou incrémenter la quantité
        if (isset($cart[$productId])) {
            $cart[$productId]['quantity'] += 1;
        } else {
            $cart[$productId] = [
                'product_id' => $product->getId(),
                'name' => $product->getNom(),
                'price' => $product->getPrix(),
                'image' => $product->getImage(),
                'quantity' => 1,
            ];
        }

        // Enregistrer le panier mis à jour dans la session
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/update/{productId}', name: 'cart_update', methods: ["POST"])]
    public function updateCart(Request $request, SessionInterface $session, $productId)
    {
        $cart = $session->get('cart', []);

        // Vérifier si le produit est dans le panier
        if (!isset($cart[$productId])) {
            throw $this->createNotFoundException('Le produit n\'existe pas dans le panier.');
        }

        // Récupérer la nouvelle quantité depuis la requête
        $newQuantity = $request->request->get('quantity');

        if ($newQuantity > 0) {
            $cart[$productId]['quantity'] = $newQuantity;
        } else {
            unset($cart[$productId]); // Supprimer le produit si la quantité est zéro
        }

        // Enregistrer le panier mis à jour dans la session
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_show');
    }

    #[Route('/cart/remove/{productId}', name: 'cart_remove')]
    public function removeFromCart(SessionInterface $session, $productId)
    {
        $cart = $session->get('cart', []);

        // Vérifier si le produit existe dans le panier
        if (isset($cart[$productId])) {
            unset($cart[$productId]);
        }

        // Mettre à jour la session
        $session->set('cart', $cart);

        return $this->redirectToRoute('cart_show');
    }

}
