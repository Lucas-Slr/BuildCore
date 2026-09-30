<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Shared\DomainError;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class LocalImageStorage implements ImageStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/uploads')] private string $directory)
    {
    }
    public function store(UploadedFile $file, string $alt): array
    {
        if (!$file->isValid() || $file->getSize() > 5 * 1024 * 1024 || trim($alt) === '' || mb_strlen($alt) > 180) {
            throw new DomainError('IMAGE_INVALID', 'Image de 5 Mo maximum et texte alternatif requis.');
        }
        $info = @getimagesize($file->getPathname());
        $extension = $info ? ([IMAGETYPE_JPEG => 'jpg',IMAGETYPE_PNG => 'png',IMAGETYPE_WEBP => 'webp'][$info[2]] ?? null) : null;
        if (!$extension || $info[0] > 4096 || $info[1] > 4096 || $info[0] < 1 || $info[1] < 1) {
            throw new DomainError('IMAGE_INVALID', 'Utilisez une image JPEG, PNG ou WebP de 4096 pixels maximum.');
        }
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0750, true);
        }
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->directory, $name);
        return ['id' => $name,'url' => '/api/v1/media/'.$name,'alt' => trim($alt),'primary' => false,'position' => 0];
    }
    public function path(string $name): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) {
            throw new DomainError('IMAGE_NOT_FOUND', 'Image introuvable.', 404);
        }
        return $this->directory.'/'.$name;
    }
    public function remove(string $name): void
    {
        $path = $this->path($name);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
