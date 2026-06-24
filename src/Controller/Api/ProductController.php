<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Security\GymResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(
        private GymResolver $gymResolver,
    ) {}

    // ── LISTE ─────────────────────────────────────────────────────────────
    #[Route('', methods: ['GET'])]
    public function index(ProductRepository $productRepo): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        $products = $gym
            ? $productRepo->findBy(['gym' => $gym])
            : $productRepo->findAll();

        return $this->json(array_map(
            fn($p) => $this->formatProduct($p),
            $products
        ));
    }

    // ── DÉTAIL ────────────────────────────────────────────────────────────
    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json($this->formatProduct($product));
    }

    // ── CRÉER ─────────────────────────────────────────────────────────────
    #[Route('', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo
    ): JsonResponse {
        $name         = $request->request->get('name');
        $price        = $request->request->get('price');
        $quantity     = $request->request->get('quantity');
        $description  = $request->request->get('description');
        $categoryName = $request->request->get('category');
        $imageFile    = $request->files->get('image');

        if (!$name || !$price || $quantity === null) {
            return $this->json(['error' => 'name, price et quantity requis'], 400);
        }

        $gym = $this->gymResolver->getGym();
        if (!$gym) {
            return $this->json(['error' => 'Aucune salle associée'], 400);
        }

        $product = new Product();
        $product->setGym($gym);
        $product->setName($name);
        $product->setDescription($description);
        $product->setPrice((float) $price);
        $product->setQuantity((int) $quantity);

        // ── Catégorie ──────────────────────────────────────────────────────
        if ($categoryName) {
            $category = $categoryRepo->findOneBy(['name' => $categoryName]);
            if ($category) {
                $product->setCategory($category);
            }
        }

        // ── Image ──────────────────────────────────────────────────────────
        if ($imageFile) {
            $newFilename = $this->uploadImage($imageFile);
            if (!$newFilename) {
                return $this->json(['error' => 'Erreur upload image'], 500);
            }
            $product->setImage('/uploads/' . $newFilename);
        }

        $em->persist($product);
        $em->flush();

        return $this->json([
            'message'    => 'Produit créé avec succès',
            'product_id' => $product->getId(),
        ], 201);
    }

    // ── MODIFIER ──────────────────────────────────────────────────────────
    #[Route('/{id}', methods: ['POST'])]
    public function update(
        Product $product,
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepo
    ): JsonResponse {
        $gym = $this->gymResolver->getGym();
        if (!$gym || $product->getGym()->getId() !== $gym->getId()) {
            return $this->json(['error' => 'Produit non trouvé'], 404);
        }

        $name         = $request->request->get('name');
        $price        = $request->request->get('price');
        $quantity     = $request->request->get('quantity');
        $description  = $request->request->get('description');
        $categoryName = $request->request->get('category');
        $imageFile    = $request->files->get('image');

        if ($name)             $product->setName($name);
        if ($description !== null)      $product->setDescription($description);
        if ($price)            $product->setPrice((float) $price);
        if ($quantity !== null) $product->setQuantity((int) $quantity);

        // ── Catégorie — CORRECTION PRINCIPALE ─────────────────────────────
        if ($categoryName !== null) {
            if ($categoryName === '') {
                // Chaîne vide = retirer la catégorie
                $product->setCategory(null);
            } else {
                $category = $categoryRepo->findOneBy(['name' => $categoryName]);
                if ($category) {
                    $product->setCategory($category);
                } else {
                    return $this->json(['error' => "Catégorie \"$categoryName\" introuvable"], 404);
                }
            }
        }

        // ── Image ──────────────────────────────────────────────────────────
        if ($imageFile) {
            // Supprimer l'ancienne image
            if ($product->getImage()) {
                $oldPath = $this->getParameter('upload_directory') . '/' . basename($product->getImage());
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            $newFilename = $this->uploadImage($imageFile);
            if (!$newFilename) {
                return $this->json(['error' => 'Erreur upload image'], 500);
            }
            $product->setImage('/uploads/' . $newFilename);
        }

        $em->flush();

        return $this->json([
            'message' => 'Produit mis à jour',
            'product' => $this->formatProduct($product),  // ← renvoie le produit mis à jour
        ]);
    }

    // ── SUPPRIMER ─────────────────────────────────────────────────────────
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product, EntityManagerInterface $em): JsonResponse
    {
        $gym = $this->gymResolver->getGym();
        if (!$gym || $product->getGym()->getId() !== $gym->getId()) {
            return $this->json(['error' => 'Produit non trouvé'], 404);
        }

        if ($product->getImage()) {
            $path = $this->getParameter('upload_directory') . '/' . basename($product->getImage());
            if (file_exists($path)) {
                unlink($path);
            }
        }
        $em->remove($product);
        $em->flush();

        return $this->json(['message' => 'Produit supprimé']);
    }

    // ── DIMINUER STOCK ────────────────────────────────────────────────────
    #[Route('/{id}/decrease-stock', methods: ['POST'])]
    public function decreaseStock(
        Product $product,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $gym = $this->gymResolver->getGym();
        if (!$gym || $product->getGym()->getId() !== $gym->getId()) {
            return $this->json(['error' => 'Produit non trouvé'], 404);
        }

        $data     = json_decode($request->getContent(), true);
        $quantity = $data['quantity'] ?? 1;

        if ($product->getQuantity() < $quantity) {
            return $this->json(['error' => 'Stock insuffisant'], 400);
        }

        $product->setQuantity($product->getQuantity() - $quantity);
        $em->flush();

        return $this->json([
            'message'      => 'Stock mis à jour',
            'new_quantity' => $product->getQuantity(),
        ]);
    }

    // ── FORMAT ────────────────────────────────────────────────────────────
    private function formatProduct(Product $product): array
    {
        return [
            'id'           => $product->getId(),
            'name'         => $product->getName(),
            'description'  => $product->getDescription(),
            'price'        => $product->getPrice(),
            'quantity'     => $product->getQuantity(),
            'image'        => $product->getImage(),
            'stock_status' => $product->getQuantity() > 0 ? 'in_stock' : 'out_of_stock',
            'category'     => $product->getCategory()?->getName(),
            'category_id'  => $product->getCategory()?->getId(),  // ← utile côté front
        ];
    }

    // ── UPLOAD IMAGE ──────────────────────────────────────────────────────
    private function uploadImage($imageFile): ?string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
            return null;
        }

        $newFilename = uniqid('product_', true) . '.' . $imageFile->guessExtension();

        try {
            $imageFile->move($this->getParameter('upload_directory'), $newFilename);
        } catch (FileException $e) {
            return null;
        }

        return $newFilename;
    }
}
