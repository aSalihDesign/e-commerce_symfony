<?php

namespace App\Controller;

use App\Entity\Category;
use App\Form\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


final class CategoryController extends AbstractController
{
    #[Route(('/category'), name: 'app_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): Response
    {
        return $this->render('category/index.html.twig', [
            'categories' => $categoryRepository->findAll(),
        ]);
    }

    #[Route('/category/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Génération automatique du slug
            $slug = $slugger->slug($category->getNom()); // Utilisez le champ 'name' de votre entité
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
            $category->setSlug($slug); // Assurez-vous que le setter setSlug() existe dans votre entité

             // Gestion de l'upload de l'image
        /** @var UploadedFile $imageFile */
        $imageFile = $form->get('image')->getData();

        if ($imageFile) {
            // Supprimer l'ancienne image si elle existe
            $oldImage = $category->getImage(); // Obtenez le nom de l'ancienne image
            if ($oldImage) {
                $oldImagePath = $this->getParameter('categories_directory') . '/' . $oldImage;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath); // Supprimez l'ancienne image
                }
            }

            // Traitement de l'upload de la nouvelle image
            $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

            try {
                $imageFile->move(
                    $this->getParameter('categories_directory'), // Assurez-vous que le paramètre est défini
                    $newFilename
                );
            } catch (FileException $e) {
                // Gestion des erreurs d'upload
            }

            // Mettez à jour l'entité avec la nouvelle image
            $category->setImage($newFilename);
        }


            $entityManager->persist($category);
            $entityManager->flush();

            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/new.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gérer l'upload de la nouvelle image
            $imageFile = $form->get('image')->getData();
            
            if ($imageFile) {
                // Supprimer l'ancienne image si elle existe
                $oldImage = $category->getImage();
                if ($oldImage) {
                    $oldImagePath = $this->getParameter('categories_directory') . '/' . $oldImage;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                // Générer un nouveau nom de fichier unique
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                try {
                    // Déplacer le fichier
                    $imageFile->move(
                        $this->getParameter('categories_directory'),
                        $newFilename
                    );
                    
                    // Mettre à jour le nom de l'image dans l'entité
                    $category->setImage($newFilename);
                    
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors du téléchargement de l\'image');
                }
            }

            // Mettre à jour le slug
            $slugger = new AsciiSlugger();
            $category->setSlug(strtolower($slugger->slug($category->getNom())));

            $entityManager->flush();

            $this->addFlash('success', 'La catégorie a été modifiée avec succès');
            return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('category/edit.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/category/{id}', name: 'app_category_delete', methods: ['POST'])]
    public function delete(Request $request, Category $category, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $category->getId(), $request->request->get('_token'))) {
            try {
                // Détacher les catégories enfants
                foreach ($category->getChildren() as $child) {
                    $child->setParent(null);
                    $entityManager->persist($child);
                }

                // Supprimer l'ancienne image
                $oldImage = $category->getImage();
                if ($oldImage) {
                    $oldImagePath = $this->getParameter('categories_directory') . '/' . $oldImage;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $entityManager->remove($category);
                $entityManager->flush();

                $this->addFlash('success', 'La catégorie a été supprimée avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la catégorie.');
                // Optionnel : logger l'erreur
            }
        }

        return $this->redirectToRoute('app_category_index', [], Response::HTTP_SEE_OTHER);
    }

}
