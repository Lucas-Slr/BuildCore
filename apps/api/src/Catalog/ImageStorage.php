<?php

declare(strict_types=1);

namespace App\Catalog;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImageStorage
{
    /** @return array{id:string,url:string,alt:string,primary:bool,position:int} */
    public function store(UploadedFile $file, string $alt): array;
    public function path(string $name): string;
    public function remove(string $name): void;
}
