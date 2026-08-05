<?php

namespace App\Services\Ticket;

class TicketData
{
    public function __construct(
        public readonly array $empresa,
        public readonly array $venta,
        public readonly array $cliente,
        public readonly array $vendedor,
        public readonly array $sucursal,
        public readonly array $items,
        public readonly array $totales,
        public readonly array $pagos,
        public readonly string $formato,
        public readonly ?array $fiscal = null,
    ) {}

    public function toArray(): array
    {
        return [
            'empresa'  => $this->empresa,
            'venta'    => $this->venta,
            'cliente'  => $this->cliente,
            'vendedor' => $this->vendedor,
            'sucursal' => $this->sucursal,
            'items'    => $this->items,
            'totales'  => $this->totales,
            'pagos'    => $this->pagos,
            'formato'  => $this->formato,
            'fiscal'   => $this->fiscal,
        ];
    }
}
