<?php

namespace App\Services\BarcodeLookup;

class BarcodeResult
{
    public function __construct(
        public readonly ?string $nombre = null,
        public readonly ?string $marca = null,
        public readonly ?string $categoria = null,
        public readonly ?string $presentacion = null,
        public readonly ?string $imagen = null,
        public readonly ?string $descripcion = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'nombre' => $this->nombre,
            'marca' => $this->marca,
            'categoria' => $this->categoria,
            'presentacion' => $this->presentacion,
            'imagen' => $this->imagen,
            'descripcion' => $this->descripcion,
        ], fn ($v) => $v !== null);
    }
}
