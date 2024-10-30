<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CartControllerTest extends WebTestCase
{
    public function testAddToCart()
    {
        // Créer un client pour simuler la navigation de l'utilisateur
        $client = static::createClient();

        // Simuler l'ajout d'un produit avec l'ID 3 au panier
        $productId = 3;
        $client->request('GET', '/cart/add/' . $productId);

        // Vérifier si la redirection après l'ajout est correcte
        $this->assertResponseRedirects('/cart');

        // Suivre la redirection
        $client->followRedirect();

        // Vérifier si le produit est bien ajouté dans la session
        $session = $client->getRequest()->getSession();
        $cart = $session->get('cart', []);

        $this->assertArrayHasKey($productId, $cart, 'Le produit n\'a pas été ajouté au panier.');
        $this->assertEquals(1, $cart[$productId]['quantity'], 'La quantité n\'est pas correcte.');
    }
}
