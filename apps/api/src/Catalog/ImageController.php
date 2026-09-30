<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Shared\DomainError;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,JsonResponse,BinaryFileResponse};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

final class ImageController extends AbstractController
{
    #[Route('/api/v1/media/{name}', methods:['GET'])]
    public function image(string $name, ImageStorage $storage): BinaryFileResponse
    {
        $path = $storage->path($name);
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }
        $mime = match(pathinfo($path, PATHINFO_EXTENSION)) {
            'jpg' => 'image/jpeg','png' => 'image/png',default => 'image/webp'
        };
        return new BinaryFileResponse($path, 200, ['Content-Type' => $mime,'Content-Security-Policy' => "default-src 'none'; sandbox",'X-Content-Type-Options' => 'nosniff']);
    }
    #[Route('/api/v1/admin/products/{id}/images', methods:['POST'])]
    public function upload(string $id, Request $r, EntityManagerInterface $em, ImageStorage $storage, CatalogService $catalog): JsonResponse
    {
        $v = $em->find(Variant::class, $id);
        if (!$v) {
            throw $this->createNotFoundException();
        }
        $file = $r->files->get('image');
        if (!$file instanceof UploadedFile) {
            throw new DomainError('IMAGE_REQUIRED', 'Sélectionnez une image.');
        }
        if (count($v->product->images) >= 8) {
            throw new DomainError('IMAGE_LIMIT', 'Huit images maximum par produit.');
        }
        $image = $storage->store($file, $r->request->getString('alt'));
        try {
            $em->wrapInTransaction(function () use ($em, $v, $image): void {
                $em->refresh($v->product, LockMode::PESSIMISTIC_WRITE);
                if (count($v->product->images) >= 8) {
                    throw new DomainError('IMAGE_LIMIT', 'Huit images maximum.');
                } $v->product->images[] = $image;
            });
        } catch (\Throwable $e) {
            $storage->remove($image['id']);
            throw $e;
        }
        return $this->json($catalog->view($v), 201);
    }
    #[Route('/api/v1/admin/products/{id}/images/{name}', methods:['DELETE','PATCH'])]
    public function modify(string $id, string $name, Request $r, EntityManagerInterface $em, ImageStorage $storage, CatalogService $catalog): JsonResponse
    {
        $v = $em->find(Variant::class, $id);
        if (!$v) {
            throw $this->createNotFoundException();
        }
        $found = false;
        $em->wrapInTransaction(function () use ($em, $v, $name, $r, &$found): void {
            $em->refresh($v->product, LockMode::PESSIMISTIC_WRITE);
            $images = [];
            $selected = null;
            $position = 0;
            foreach ($v->product->images as $image) {
                if (($image['id'] ?? '') === $name) {
                    $found = true;
                    if ($r->isMethod('DELETE')) {
                        continue;
                    } $data = $r->toArray();
                    $alt = $data['alt'] ?? $image['alt'];
                    $position = $data['position'] ?? 0;
                    if (!is_string($alt) || trim($alt) === '' || strlen($alt) > 180 || !is_int($position) || $position < 0 || $position > 7) {
                        throw new DomainError('IMAGE_INVALID', 'Texte alternatif ou position invalide.');
                    }$image['alt'] = $alt;
                    $image['position'] = $position;
                    $selected = $image;
                    continue;
                }
                $images[] = $image;
            }
            if (!$found) {
                throw $this->createNotFoundException();
            }
            if ($selected !== null) {
                array_splice($images, min($position, count($images)), 0, [$selected]);
            }
            foreach ($images as $i => &$image) {
                $image['primary'] = $i === 0;
                $image['position'] = $i;
            }unset($image);
            $v->product->images = $images;
        });
        if ($r->isMethod('DELETE')) {
            $storage->remove($name);
        }return $this->json($catalog->view($v));
    }
}
