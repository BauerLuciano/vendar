<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Sucursal;
use Carbon\Carbon;

class PromocionesSeedDemo extends Command
{
    protected $signature = 'promociones:seed-demo';
    protected $description = 'Pobla la base con categorías, productos y promociones demo para testing visual';

    private const ETIQUETAS = ['🔥 Promoción', '⚡ Oferta especial', '💰 Ahorrá hoy', '🏆 Oferta única'];
    private const DESCUENTOS = [10, 15, 20, 30, 40];
    private const PREFIX = 'DEMO - ';

    private array $categoriasData = [
        ['nombreCategoria' => 'Bebidas', 'slug' => 'bebidas', 'descripcion' => 'Gaseosas, aguas, jugos y bebidas'],
        ['nombreCategoria' => 'Lácteos', 'slug' => 'lacteos', 'descripcion' => 'Leches, yogures, quesos y manteca'],
        ['nombreCategoria' => 'Almacén', 'slug' => 'almacen', 'descripcion' => 'Arroz, fideos, aceite, harinas y conservas'],
        ['nombreCategoria' => 'Panificados', 'slug' => 'panificados', 'descripcion' => 'Panes, facturas, galletitas y pan rallado'],
        ['nombreCategoria' => 'Snacks', 'slug' => 'snacks', 'descripcion' => 'Papas fritas, chizitos, maní y palitos'],
        ['nombreCategoria' => 'Congelados', 'slug' => 'congelados', 'descripcion' => 'Helados, papas fritas congeladas, verduras congeladas'],
        ['nombreCategoria' => 'Limpieza', 'slug' => 'limpieza', 'descripcion' => 'Detergente, lavandina, jabón y desinfectante'],
        ['nombreCategoria' => 'Perfumería', 'slug' => 'perfumeria', 'descripcion' => 'Shampoo, jabón de tocador, desodorante y crema'],
        ['nombreCategoria' => 'Mascotas', 'slug' => 'mascotas', 'descripcion' => 'Alimento para perros y gatos, snacks y arena'],
        ['nombreCategoria' => 'Frutas y Verduras', 'slug' => 'frutas-verduras', 'descripcion' => 'Frutas y verduras frescas'],
        ['nombreCategoria' => 'Carnes', 'slug' => 'carnes', 'descripcion' => 'Carne vacuna, pollo, cerdo y embutidos'],
        ['nombreCategoria' => 'Golosinas', 'slug' => 'golosinas', 'descripcion' => 'Caramelos, chicles, chocolates y chupetines'],
    ];

    private array $productosData = [
        'Bebidas' => [
            ['nombre' => 'Coca Cola 500ml', 'precio_venta' => 800, 'precio_costo' => 520],
            ['nombre' => 'Coca Cola 2.25L', 'precio_venta' => 1800, 'precio_costo' => 1200],
            ['nombre' => 'Sprite 500ml', 'precio_venta' => 750, 'precio_costo' => 490],
            ['nombre' => 'Fanta 500ml', 'precio_venta' => 750, 'precio_costo' => 490],
            ['nombre' => 'Agua Mineral 500ml', 'precio_venta' => 350, 'precio_costo' => 200],
            ['nombre' => 'Agua Mineral 2L', 'precio_venta' => 600, 'precio_costo' => 380],
            ['nombre' => 'Jugo Naranja 1L', 'precio_venta' => 550, 'precio_costo' => 350],
            ['nombre' => 'Jugo Multifruta 1L', 'precio_venta' => 550, 'precio_costo' => 350],
            ['nombre' => 'Pepsi 500ml', 'precio_venta' => 750, 'precio_costo' => 480],
            ['nombre' => '7UP 500ml', 'precio_venta' => 750, 'precio_costo' => 480],
        ],
        'Lácteos' => [
            ['nombre' => 'Leche Entera 1L', 'precio_venta' => 450, 'precio_costo' => 290],
            ['nombre' => 'Leche Descremada 1L', 'precio_venta' => 450, 'precio_costo' => 290],
            ['nombre' => 'Yogur Frutilla 190g', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Yogur Vainilla 190g', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Queso Cremoso x500g', 'precio_venta' => 1200, 'precio_costo' => 800],
            ['nombre' => 'Manteca 200g', 'precio_venta' => 600, 'precio_costo' => 400],
            ['nombre' => 'Crema de Leche 200cc', 'precio_venta' => 500, 'precio_costo' => 320],
            ['nombre' => 'Dulce de Leche 400g', 'precio_venta' => 550, 'precio_costo' => 360],
            ['nombre' => 'Queso Rallado 120g', 'precio_venta' => 450, 'precio_costo' => 290],
            ['nombre' => 'Leche Chocolatada 1L', 'precio_venta' => 500, 'precio_costo' => 320],
        ],
        'Almacén' => [
            ['nombre' => 'Arroz 1kg', 'precio_venta' => 500, 'precio_costo' => 330],
            ['nombre' => 'Fideos Tallarín 500g', 'precio_venta' => 300, 'precio_costo' => 190],
            ['nombre' => 'Aceite Girasol 1L', 'precio_venta' => 700, 'precio_costo' => 480],
            ['nombre' => 'Harina 000 1kg', 'precio_venta' => 280, 'precio_costo' => 170],
            ['nombre' => 'Azúcar 1kg', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Sal Fina 500g', 'precio_venta' => 200, 'precio_costo' => 120],
            ['nombre' => 'Tomate Triturado 500g', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Lentejas 500g', 'precio_venta' => 400, 'precio_costo' => 260],
            ['nombre' => 'Atún al natural 160g', 'precio_venta' => 600, 'precio_costo' => 400],
            ['nombre' => 'Vinagre de Vino 500cc', 'precio_venta' => 250, 'precio_costo' => 150],
        ],
        'Panificados' => [
            ['nombre' => 'Pan Francés xkg', 'precio_venta' => 500, 'precio_costo' => 300],
            ['nombre' => 'Pan de Molde Blanco 600g', 'precio_venta' => 600, 'precio_costo' => 390],
            ['nombre' => 'Facturas surtidas x6', 'precio_venta' => 800, 'precio_costo' => 500],
            ['nombre' => 'Pan Rallado 200g', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Pan Hamburguesa x4', 'precio_venta' => 400, 'precio_costo' => 250],
            ['nombre' => 'Pan Pancho x4', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Galletitas Saladas 200g', 'precio_venta' => 400, 'precio_costo' => 260],
            ['nombre' => 'Galletitas de Arroz 120g', 'precio_venta' => 300, 'precio_costo' => 190],
            ['nombre' => 'Pebetes x6', 'precio_venta' => 450, 'precio_costo' => 280],
            ['nombre' => 'Figacitas x6', 'precio_venta' => 500, 'precio_costo' => 310],
        ],
        'Snacks' => [
            ['nombre' => 'Papas Fritas 120g', 'precio_venta' => 700, 'precio_costo' => 450],
            ['nombre' => 'Chizitos 120g', 'precio_venta' => 550, 'precio_costo' => 350],
            ['nombre' => 'Maní Salado 150g', 'precio_venta' => 400, 'precio_costo' => 250],
            ['nombre' => 'Palitos Salados 100g', 'precio_venta' => 300, 'precio_costo' => 180],
            ['nombre' => 'Pipas 80g', 'precio_venta' => 250, 'precio_costo' => 150],
            ['nombre' => 'Mix de Frutos Secos 100g', 'precio_venta' => 600, 'precio_costo' => 400],
            ['nombre' => 'Nachos 200g', 'precio_venta' => 500, 'precio_costo' => 320],
            ['nombre' => 'Papas Fritas 300g', 'precio_venta' => 1200, 'precio_costo' => 800],
            ['nombre' => 'Huevito Kinder 30g', 'precio_venta' => 250, 'precio_costo' => 150],
            ['nombre' => 'Barrita de Cereal x3', 'precio_venta' => 350, 'precio_costo' => 220],
        ],
        'Congelados' => [
            ['nombre' => 'Helado Vainilla 1L', 'precio_venta' => 1500, 'precio_costo' => 1000],
            ['nombre' => 'Helado Chocolate 1L', 'precio_venta' => 1500, 'precio_costo' => 1000],
            ['nombre' => 'Papas Fritas Congeladas 500g', 'precio_venta' => 700, 'precio_costo' => 450],
            ['nombre' => 'Arvejas Congeladas 400g', 'precio_venta' => 400, 'precio_costo' => 250],
            ['nombre' => 'Espinaca Congelada 400g', 'precio_venta' => 450, 'precio_costo' => 280],
            ['nombre' => 'Pizza Congelada', 'precio_venta' => 1200, 'precio_costo' => 800],
            ['nombre' => 'Empanadas Congeladas x6', 'precio_venta' => 900, 'precio_costo' => 600],
            ['nombre' => 'Medallones de Verdura x4', 'precio_venta' => 600, 'precio_costo' => 390],
            ['nombre' => 'Hamburguesas Congeladas x4', 'precio_venta' => 800, 'precio_costo' => 520],
            ['nombre' => 'Bastones de Muzzarella x6', 'precio_venta' => 700, 'precio_costo' => 460],
        ],
        'Limpieza' => [
            ['nombre' => 'Detergente 500ml', 'precio_venta' => 400, 'precio_costo' => 250],
            ['nombre' => 'Lavandina 1L', 'precio_venta' => 250, 'precio_costo' => 150],
            ['nombre' => 'Jabón en Polvo 500g', 'precio_venta' => 600, 'precio_costo' => 400],
            ['nombre' => 'Limpiavidrios 500ml', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Desinfectante 1L', 'precio_venta' => 450, 'precio_costo' => 290],
            ['nombre' => 'Esponja x3', 'precio_venta' => 200, 'precio_costo' => 120],
            ['nombre' => 'Lavandina Lavanda 1L', 'precio_venta' => 280, 'precio_costo' => 170],
            ['nombre' => 'Jabón Líquido 300ml', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Quitagrasas 500ml', 'precio_venta' => 400, 'precio_costo' => 250],
            ['nombre' => 'Suavizante 500ml', 'precio_venta' => 350, 'precio_costo' => 220],
        ],
        'Perfumería' => [
            ['nombre' => 'Shampoo 400ml', 'precio_venta' => 700, 'precio_costo' => 460],
            ['nombre' => 'Acondicionador 400ml', 'precio_venta' => 700, 'precio_costo' => 460],
            ['nombre' => 'Jabón de Tocador x3', 'precio_venta' => 300, 'precio_costo' => 180],
            ['nombre' => 'Desodorante Spray', 'precio_venta' => 600, 'precio_costo' => 390],
            ['nombre' => 'Crema Corporal 250ml', 'precio_venta' => 800, 'precio_costo' => 520],
            ['nombre' => 'Pasta Dental 90g', 'precio_venta' => 450, 'precio_costo' => 290],
            ['nombre' => 'Enjuague Bucal 250ml', 'precio_venta' => 500, 'precio_costo' => 320],
            ['nombre' => 'Toallitas Desmaquillantes', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Talco 100g', 'precio_venta' => 300, 'precio_costo' => 190],
            ['nombre' => 'Protector Solar 100ml', 'precio_venta' => 1200, 'precio_costo' => 800],
        ],
        'Mascotas' => [
            ['nombre' => 'Alimento Perro 15kg', 'precio_venta' => 4500, 'precio_costo' => 3200],
            ['nombre' => 'Alimento Gato 7.5kg', 'precio_venta' => 3500, 'precio_costo' => 2400],
            ['nombre' => 'Snacks para Perro 100g', 'precio_venta' => 400, 'precio_costo' => 250],
            ['nombre' => 'Arena Sanitaria 5kg', 'precio_venta' => 800, 'precio_costo' => 500],
            ['nombre' => 'Alimento Perro 3kg', 'precio_venta' => 1500, 'precio_costo' => 1000],
            ['nombre' => 'Alimento Gato 1.5kg', 'precio_venta' => 1200, 'precio_costo' => 800],
            ['nombre' => 'Hueso Cuero', 'precio_venta' => 250, 'precio_costo' => 150],
            ['nombre' => 'Juguete Pelota Perro', 'precio_venta' => 300, 'precio_costo' => 180],
            ['nombre' => 'Leche Gatito 250ml', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Shampoo Perro 250ml', 'precio_venta' => 600, 'precio_costo' => 400],
        ],
        'Frutas y Verduras' => [
            ['nombre' => 'Manzana Roja xkg', 'precio_venta' => 400, 'precio_costo' => 250],
            ['nombre' => 'Banana xkg', 'precio_venta' => 350, 'precio_costo' => 200],
            ['nombre' => 'Naranja xkg', 'precio_venta' => 280, 'precio_costo' => 170],
            ['nombre' => 'Lechuga x unidad', 'precio_venta' => 250, 'precio_costo' => 150],
            ['nombre' => 'Tomate xkg', 'precio_venta' => 500, 'precio_costo' => 320],
            ['nombre' => 'Papa xkg', 'precio_venta' => 300, 'precio_costo' => 180],
            ['nombre' => 'Cebolla xkg', 'precio_venta' => 280, 'precio_costo' => 170],
            ['nombre' => 'Zanahoria xkg', 'precio_venta' => 250, 'precio_costo' => 150],
            ['nombre' => 'Pimiento Rojo xkg', 'precio_venta' => 650, 'precio_costo' => 420],
            ['nombre' => 'Pera xkg', 'precio_venta' => 380, 'precio_costo' => 240],
        ],
        'Carnes' => [
            ['nombre' => 'Carne Picada xkg', 'precio_venta' => 2500, 'precio_costo' => 1700],
            ['nombre' => 'Pechuga de Pollo xkg', 'precio_venta' => 2200, 'precio_costo' => 1500],
            ['nombre' => 'Carne de Cerdo xkg', 'precio_venta' => 2000, 'precio_costo' => 1400],
            ['nombre' => 'Chorizo xkg', 'precio_venta' => 1800, 'precio_costo' => 1200],
            ['nombre' => 'Milanesa de Pollo xkg', 'precio_venta' => 2400, 'precio_costo' => 1600],
            ['nombre' => 'Milanesa de Carne xkg', 'precio_venta' => 2800, 'precio_costo' => 1900],
            ['nombre' => 'Alitas de Pollo xkg', 'precio_venta' => 1600, 'precio_costo' => 1000],
            ['nombre' => 'Costilla de Cerdo xkg', 'precio_venta' => 2200, 'precio_costo' => 1500],
            ['nombre' => 'Matambre xkg', 'precio_venta' => 3000, 'precio_costo' => 2100],
            ['nombre' => 'Hígado de Pollo xkg', 'precio_venta' => 1200, 'precio_costo' => 750],
        ],
        'Golosinas' => [
            ['nombre' => 'Caramelo Menta 50g', 'precio_venta' => 150, 'precio_costo' => 80],
            ['nombre' => 'Chicle Menta x10', 'precio_venta' => 100, 'precio_costo' => 50],
            ['nombre' => 'Chocolate con Leche 80g', 'precio_venta' => 600, 'precio_costo' => 400],
            ['nombre' => 'Chupetín x unidad', 'precio_venta' => 80, 'precio_costo' => 35],
            ['nombre' => 'Alfajor Simple', 'precio_venta' => 250, 'precio_costo' => 150],
            ['nombre' => 'Alfajor Triple', 'precio_venta' => 350, 'precio_costo' => 220],
            ['nombre' => 'Gomitas 80g', 'precio_venta' => 200, 'precio_costo' => 120],
            ['nombre' => 'Turrón 50g', 'precio_venta' => 150, 'precio_costo' => 80],
            ['nombre' => 'Chocolate Blanco 80g', 'precio_venta' => 650, 'precio_costo' => 430],
            ['nombre' => 'Snack de Chocolate 50g', 'precio_venta' => 300, 'precio_costo' => 190],
        ],
    ];

    public function handle()
    {
        $sucursal = Sucursal::first();
        if (!$sucursal) {
            $this->error('No hay sucursales en la base. Creá una primero.');
            return;
        }

        $marcaDemo = Marca::firstOrCreate(
            ['nombreMarca' => 'Demo'],
            ['slug' => 'demo', 'estado' => true]
        );

        $categoriasCreadas = 0;
        $productosCreados = 0;
        $promocionesActivadas = 0;

        $this->info("Usando sucursal: {$sucursal->nombre} (ID: {$sucursal->id})");
        $this->info("Marca demo: {$marcaDemo->id}");
        $this->line('');

        $bar = $this->output->createProgressBar(count($this->productosData));
        $bar->start();

        foreach ($this->categoriasData as $catData) {
            $categoria = Categoria::firstOrCreate(
                ['nombreCategoria' => $catData['nombreCategoria']],
                $catData
            );

            if ($categoria->wasRecentlyCreated) {
                $categoriasCreadas++;
            }

            $catName = $catData['nombreCategoria'];
            $productos = $this->productosData[$catName] ?? [];

            foreach ($productos as $prodData) {
                $nombreDemo = self::PREFIX . $prodData['nombre'];

                $producto = Producto::updateOrCreate(
                    ['nombre' => $nombreDemo],
                    [
                        'categoria_id' => $categoria->id,
                        'marca_id' => $marcaDemo->id,
                        'proveedor_id' => null,
                        'codigo_barras' => 'DEMO' . str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT),
                        'descripcion' => 'Producto demo — ' . $prodData['nombre'],
                        'unidad_medida' => 'Unidad',
                        'es_retornable' => false,
                        'precio_costo' => $prodData['precio_costo'],
                        'precio_venta' => $prodData['precio_venta'],
                        'stock_minimo' => 5,
                        'imagen' => null,
                        'estado' => true,
                    ]
                );

                if (!$producto->wasRecentlyCreated && !$producto->wasChanged()) {
                    // already exists, skip
                } else {
                    $productosCreados++;
                }

                $stock = rand(10, 100);
                $producto->sucursales()->syncWithoutDetaching([
                    $sucursal->id => [
                        'cantidad_fisica' => $stock,
                        'cantidad_reservada' => 0,
                    ],
                ]);

                if ($producto->promocion_activa) {
                    $promocionesActivadas++;
                }
            }

            $bar->advance();
        }

        // now activate promos on ~25 random products
        $productosConStock = Producto::where('nombre', 'like', self::PREFIX . '%')
            ->where('estado', true)
            ->whereHas('sucursales', fn($q) => $q->where('cantidad_fisica', '>', 0))
            ->inRandomOrder()
            ->take(25)
            ->get();

        foreach ($productosConStock as $producto) {
            $descuento = self::DESCUENTOS[array_rand(self::DESCUENTOS)];
            $precioPromo = round($producto->precio_venta * (1 - $descuento / 100), 2);
            $etiqueta = self::ETIQUETAS[array_rand(self::ETIQUETAS)];

            $producto->update([
                'precio_promocion' => $precioPromo,
                'promocion_activa' => true,
                'etiqueta_promocion' => $etiqueta,
                'promocion_tipo' => 'manual',
                'promocion_fin' => Carbon::today()->addDays(30),
            ]);

            $promocionesActivadas++;
        }

        $bar->finish();
        $this->newLine();
        $this->line('');

        $totalProductos = Producto::where('nombre', 'like', self::PREFIX . '%')->count();
        $totalPromos = Producto::where('nombre', 'like', self::PREFIX . '%')
            ->where('promocion_activa', true)
            ->count();

        $this->info('✅ Resumen final:');
        $this->line("   Categorías creadas: {$categoriasCreadas}");
        $this->line("   Productos demo en BD: {$totalProductos}");
        $this->line("   Promociones activadas: {$totalPromos}");
        $this->line("   Sucursal vinculada: {$sucursal->nombre}");
    }
}
