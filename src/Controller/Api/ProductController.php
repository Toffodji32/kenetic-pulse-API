<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Repository\CategoryRepository;


#[Route('/api/products')]
class ProductController extends AbstractController
{
    // ========================
    // 📄 LISTE PRODUITS
    // ========================
    #[Route('', methods: ['GET'])]
    public function index(ProductRepository $productRepo): JsonResponse
    {
        $products = $productRepo->findAll();

        $data = [];

        foreach ($products as $product) {
            $data[] = [
                "id" => $product->getId(),
                "name" => $product->getName(),
                "description" => $product->getDescription(),
                "price" => $product->getPrice(),
                "quantity" => $product->getQuantity(),
                "image" => $product->getImage(),
                "stock_status" => $product->getQuantity() > 0 ? "in_stock" : "out_of_stock",
                "category"     => $product->getCategory()?->getName(),
            ];
        }

        return $this->json($data);
    }

    // ========================
    // 🔍 DETAIL PRODUIT
    // ========================
    #[Route('/{id}', methods: ['GET'])]
    public function show(Product $product): JsonResponse
    {
        return $this->json([
            "id" => $product->getId(),
            "name" => $product->getName(),
            "description" => $product->getDescription(),
            "price" => $product->getPrice(),
            "quantity" => $product->getQuantity(),
            "image" => $product->getImage(),
            "stock_status" => $product->getQuantity() > 0 ? "in_stock" : "out_of_stock",
            "category"     => $product->getCategory()?->getName(),
        ]);
    }

    // ========================
    // ➕ CREER PRODUIT
    // ========================
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, CategoryRepository $categoryRepo): JsonResponse
    {
        $name = $request->request->get('name');
        $price = $request->request->get('price');
        $quantity = $request->request->get('quantity');
        $description = $request->request->get('description');
        $imageFile = $request->files->get('image');
        $categoryName = $request->request->get('category');

        if (!$name || !$price || $quantity === null) {
            return $this->json([
                "error" => "name, price et quantity requis"
            ], 400);
        }

        $product = new Product();
        $product->setName($name);
        $product->setDescription($description);
        $product->setPrice((float) $price);
        $product->setQuantity((int) $quantity);

        // Ajouter la catégorie si fournie
        if ($categoryName) {
            $category = $categoryRepo->findOneBy(['name' => $categoryName]);
            if ($category) {
                $product->setCategory($category);
            }
        }

        // 📸 IMAGE
        if ($imageFile) {
            $newFilename = $this->uploadImage($imageFile);
            if (!$newFilename) {
                return $this->json(["error" => "Erreur upload image"], 500);
            }
            $product->setImage('/uploads/' . $newFilename);
        }

        $em->persist($product);
        $em->flush();

        return $this->json([
            "message" => "Produit créé avec succès",
            "product_id" => $product->getId()
        ], 201);
    }

    // ========================
    // ✏️ UPDATE PRODUIT
    // ========================
    #[Route('/{id}', methods: ['POST'])]
    public function update(Product $product, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $name = $request->request->get('name');
        $price = $request->request->get('price');
        $quantity = $request->request->get('quantity');
        $description = $request->request->get('description');
        $imageFile = $request->files->get('image');

        if ($name) $product->setName($name);
        if ($description) $product->setDescription($description);
        if ($price) $product->setPrice((float) $price);
        if ($quantity !== null) $product->setQuantity((int) $quantity);

        // 📸 UPDATE IMAGE
        if ($imageFile) {

            // supprimer ancienne image
            if ($product->getImage()) {
                $oldPath = $this->getParameter('upload_directory') . '/' . basename($product->getImage());
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $newFilename = $this->uploadImage($imageFile);
            if (!$newFilename) {
                return $this->json(["error" => "Erreur upload image"], 500);
            }

            $product->setImage('/uploads/' . $newFilename);
        }

        $em->flush();

        return $this->json([
            "message" => "Produit mis à jour"
        ]);
    }

    // ========================
    // ❌ DELETE PRODUIT
    // ========================
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Product $product, EntityManagerInterface $em): JsonResponse
    {
        // supprimer image
        if ($product->getImage()) {
            $path = $this->getParameter('upload_directory') . '/' . basename($product->getImage());
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $em->remove($product);
        $em->flush();

        return $this->json([
            "message" => "Produit supprimé"
        ]);
    }

    // ========================
    // 📉 DIMINUER STOCK
    // ========================
    #[Route('/{id}/decrease-stock', methods: ['POST'])]
    public function decreaseStock(Product $product, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $quantity = $data['quantity'] ?? 1;

        if ($product->getQuantity() < $quantity) {
            return $this->json([
                "error" => "Stock insuffisant"
            ], 400);
        }

        $product->setQuantity($product->getQuantity() - $quantity);

        $em->flush();

        return $this->json([
            "message" => "Stock mis à jour",
            "new_quantity" => $product->getQuantity()
        ]);
    }

    // ========================
    // 📸 FONCTION UPLOAD
    // ========================
    private function uploadImage($imageFile): ?string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($imageFile->getMimeType(), $allowedTypes)) {
            return null;
        }

        $newFilename = uniqid('product_', true) . '.' . $imageFile->guessExtension();

        try {
            $imageFile->move(
                $this->getParameter('upload_directory'),
                $newFilename
            );
        } catch (FileException $e) {
            return null;
        }

        return $newFilename;
    }
}
