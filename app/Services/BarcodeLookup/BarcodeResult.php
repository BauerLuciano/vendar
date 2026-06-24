<?php

namespace App\Services\BarcodeLookup;

class BarcodeResult
{
    public function __construct(
        public readonly ?string $nombre = null,
        public readonly ?string $marca = null,
        public readonly ?string $categoria = null,
        public readonly ?string $presentacion = null,
        public readonly ?float $pesoGramos = null,
        public readonly ?string $imagen = null,
        public readonly ?string $descripcion = null,
        public readonly ?string $fabricante = null,
        public readonly ?string $paisOrigen = null,
        public readonly ?string $provider = null,
        public readonly array $datosExtra = [],
    ) {}

    public function merge(BarcodeResult $other): static
    {
        return new static(
            nombre: $other->nombre ?? $this->nombre,
            marca: $other->marca ?? $this->marca,
            categoria: $other->categoria ?? $this->categoria,
            presentacion: $other->presentacion ?? $this->presentacion,
            pesoGramos: $other->pesoGramos ?? $this->pesoGramos,
            imagen: $other->imagen ?? $this->imagen,
            descripcion: $other->descripcion ?? $this->descripcion,
            fabricante: $other->fabricante ?? $this->fabricante,
            paisOrigen: $other->paisOrigen ?? $this->paisOrigen,
            provider: $this->provider && $other->provider ? 'composite' : ($other->provider ?? $this->provider),
            datosExtra: array_merge($this->datosExtra, $other->datosExtra),
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'nombre' => $this->nombre,
            'marca' => $this->marca,
            'categoria' => $this->categoria,
            'presentacion' => $this->presentacion,
            'peso_gramos' => $this->pesoGramos,
            'imagen' => $this->imagen,
            'descripcion' => $this->descripcion,
            'fabricante' => $this->fabricante,
            'pais_origen' => $this->paisOrigen,
            'provider' => $this->provider,
        ], fn ($v) => $v !== null);
    }
}
