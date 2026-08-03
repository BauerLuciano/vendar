<?php

namespace Database\Seeders;

use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Comercio;
use App\Models\Consumidor;
use App\Models\CuentaCorriente;
use App\Models\DetalleVenta;
use App\Models\Lote;
use App\Models\Marca;
use App\Models\MovimientoCaja;
use App\Models\MovimientoCuentaCorriente;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraDetalle;
use App\Models\PedidoWeb;
use App\Models\PedidoWebItem;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\TransferenciaSugerida;
use App\Models\TurnoCaja;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class QATestDataSeeder extends Seeder
{
    public function run(): void
    {
        Model::unguard();

        $this->crearComercios();
        $this->crearSucursales();
        $this->crearGlobalData();
        $this->actualizarUsuariosExistentes();
        $this->crearUsuariosQA();
        $this->crearConsumidores();
        $this->crearProductosA();
        $this->crearProductosB();
        $this->adjuntarProductosASucursales();
        $this->crearLotes();
        $this->crearCajas();
        $this->crearTurnos();
        $this->crearVentas();
        $this->crearPedidosWeb();
        $this->crearOrdenesCompra();
        $this->crearTransferenciasSugeridas();

        $this->sincronizarSecuencias();

        Model::reguard();
    }

    private function userIdA()
    {
        return User::where('email', 'admin.a@test.com')->value('id');
    }

    private function userIdB()
    {
        return User::where('email', 'admin.b@test.com')->value('id');
    }

    private function consumidorA()
    {
        return Consumidor::where('documento', '11111111')->value('id');
    }

    private function consumidorB()
    {
        return Consumidor::where('documento', '55555555')->value('id');
    }

    private function crearComercios(): void
    {
        $comercioA = Comercio::updateOrCreate(
            ['id' => 1],
            [
                'nombre' => 'Almacén Norte',
                'slug' => 'almacen-norte',
                'plan' => 'basico',
                'modulos_habilitados' => ['pos' => true, 'fiados' => true, 'lotes' => true, 'proveedores' => true, 'transferencias' => true],
                'limite_sucursales' => 1,
                'limite_usuarios' => 1,
                'acepta_efectivo' => true,
                'vencimiento_pago' => Carbon::now()->addDays(30),
            ]
        );
        DB::table('comercios')->where('id', $comercioA->id)->update([
            'plan_id' => 1,
            'status' => 'activo',
        ]);

        $comercioB = Comercio::updateOrCreate(
            ['id' => 2],
            [
                'nombre' => 'Almacén Sur',
                'slug' => 'almacen-sur',
                'plan' => 'pro',
                'modulos_habilitados' => ['pos' => true, 'fiados' => true, 'lotes' => true, 'proveedores' => true],
                'limite_sucursales' => 3,
                'limite_usuarios' => 3,
                'acepta_efectivo' => true,
                'vencimiento_pago' => Carbon::now()->addDays(15),
            ]
        );
        DB::table('comercios')->where('id', $comercioB->id)->update([
            'plan_id' => 2,
            'status' => 'activo',
        ]);
    }

    private function crearSucursales(): void
    {
        Sucursal::updateOrCreate(
            ['id' => 1],
            [
                'comercio_id' => 1,
                'nombre' => 'Casa Central',
                'direccion' => 'Av. Siempre Viva 123',
                'telefono' => '111111111',
                'tipo' => 'punto_de_venta',
                'estado' => true,
            ]
        );

        Sucursal::updateOrCreate(
            ['id' => 2],
            [
                'comercio_id' => 1,
                'nombre' => 'Sucursal Norte',
                'direccion' => 'Calle Norte 456',
                'telefono' => '111111112',
                'tipo' => 'punto_de_venta',
                'estado' => true,
            ]
        );

        Sucursal::updateOrCreate(
            ['id' => 3],
            [
                'comercio_id' => 2,
                'nombre' => 'Casa Central',
                'direccion' => 'Calle Sur 789',
                'telefono' => '222222221',
                'tipo' => 'punto_de_venta',
                'estado' => true,
            ]
        );

        Sucursal::updateOrCreate(
            ['id' => 4],
            [
                'comercio_id' => 2,
                'nombre' => 'Sucursal Sur',
                'direccion' => 'Av. Austral 321',
                'telefono' => '222222222',
                'tipo' => 'punto_de_venta',
                'estado' => true,
            ]
        );
    }

    private function crearGlobalData(): void
    {
        Categoria::updateOrCreate(
            ['id' => 1],
            ['nombreCategoria' => 'General', 'slug' => 'general', 'estado' => true]
        );

        Marca::updateOrCreate(
            ['id' => 1],
            ['nombreMarca' => 'Genérica', 'estado' => true]
        );

        Proveedor::updateOrCreate(
            ['id' => 1],
            [
                'razon_social' => 'Proveedor General S.A.',
                'cuit' => '30-12345678-9',
                'telefono' => '333333333',
                'email' => 'info@proveedorgeneral.com',
                'direccion' => 'Av. Mayorista 555',
                'estado' => true,
            ]
        );
    }

    private function actualizarUsuariosExistentes(): void
    {
        $user1 = User::find(1);
        if ($user1 && !$user1->comercio_id) {
            $user1->update(['comercio_id' => 1, 'branch_id' => 1]);
        }

        $user2 = User::find(2);
        if ($user2 && !$user2->comercio_id) {
            $user2->update(['comercio_id' => 1, 'branch_id' => 1]);
        }
    }

    private function crearUsuariosQA(): void
    {
        $adminA = User::updateOrCreate(
            ['email' => 'admin.a@test.com'],
            [
                'name' => 'Admin Norte',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'comercio_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $adminA->assignRole('SuperAdmin');

        $userA = User::updateOrCreate(
            ['email' => 'user.a@test.com'],
            [
                'name' => 'Cajero Norte',
                'password' => Hash::make('password'),
                'branch_id' => 1,
                'comercio_id' => 1,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $userA->assignRole('Cajero');

        $adminB = User::updateOrCreate(
            ['email' => 'admin.b@test.com'],
            [
                'name' => 'Admin Sur',
                'password' => Hash::make('password'),
                'branch_id' => 3,
                'comercio_id' => 2,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $adminB->assignRole('SuperAdmin');

        $userB = User::updateOrCreate(
            ['email' => 'user.b@test.com'],
            [
                'name' => 'Cajero Sur',
                'password' => Hash::make('password'),
                'branch_id' => 3,
                'comercio_id' => 2,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $userB->assignRole('Cajero');
    }

    private function crearConsumidores(): void
    {
        Consumidor::updateOrCreate(
            ['id' => 1],
            ['comercio_id' => 1, 'nombre' => 'Consumidor', 'apellido' => 'Final', 'documento' => '00000000']
        );

        $consumidoresA = [
            ['id' => 2, 'nombre' => 'Juan', 'apellido' => 'Pérez', 'documento' => '11111111', 'limite_cuenta_corriente' => 5000, 'comercio_id' => 1],
            ['id' => 3, 'nombre' => 'María', 'apellido' => 'García', 'documento' => '22222222', 'limite_cuenta_corriente' => 3000, 'comercio_id' => 1],
            ['id' => 4, 'nombre' => 'Carlos', 'apellido' => 'López', 'documento' => '33333333', 'limite_cuenta_corriente' => 10000, 'comercio_id' => 1],
            ['id' => 5, 'nombre' => 'Ana', 'apellido' => 'Martínez', 'documento' => '44444444', 'limite_cuenta_corriente' => 2000, 'comercio_id' => 1],
        ];

        foreach ($consumidoresA as $data) {
            $this->crearConsumidor($data);
        }

        $consumidoresB = [
            ['id' => 6, 'nombre' => 'Pedro', 'apellido' => 'Sánchez', 'documento' => '55555555', 'limite_cuenta_corriente' => 5000, 'comercio_id' => 2],
            ['id' => 7, 'nombre' => 'Laura', 'apellido' => 'Rodríguez', 'documento' => '66666666', 'limite_cuenta_corriente' => 3000, 'comercio_id' => 2],
            ['id' => 8, 'nombre' => 'Diego', 'apellido' => 'Fernández', 'documento' => '77777777', 'limite_cuenta_corriente' => 10000, 'comercio_id' => 2],
            ['id' => 9, 'nombre' => 'Valentina', 'apellido' => 'Gómez', 'documento' => '88888888', 'limite_cuenta_corriente' => 2000, 'comercio_id' => 2],
            ['id' => 10, 'nombre' => 'Sofía', 'apellido' => 'Díaz', 'documento' => '99999999', 'limite_cuenta_corriente' => 7000, 'comercio_id' => 2],
        ];

        foreach ($consumidoresB as $data) {
            $this->crearConsumidor($data);
        }
    }

    private function crearConsumidor(array $data): void
    {
        $consumidor = Consumidor::firstOrCreate(
            ['id' => $data['id']],
            [
                'comercio_id' => $data['comercio_id'],
                'nombre' => $data['nombre'],
                'apellido' => $data['apellido'],
                'documento' => $data['documento'],
                'limite_cuenta_corriente' => $data['limite_cuenta_corriente'],
                'estado' => true,
            ]
        );

        if (!$consumidor->cuentaCorriente) {
            $consumidor->cuentaCorriente()->create([
                'saldo_deudor' => 0,
                'estado' => true,
            ]);
        }
    }

    private function crearProductosA(): void
    {
        $productos = [
            ['id' => 1, 'nombre' => 'Coca Cola 500ml', 'codigo_barras' => '10000001', 'precio_costo' => 550, 'precio_venta' => 800, 'stock_minimo' => 10],
            ['id' => 2, 'nombre' => 'Agua Mineral 1L', 'codigo_barras' => '10000002', 'precio_costo' => 250, 'precio_venta' => 400, 'stock_minimo' => 15],
            ['id' => 3, 'nombre' => 'Papas Fritas 120g', 'codigo_barras' => '10000003', 'precio_costo' => 450, 'precio_venta' => 700, 'stock_minimo' => 10],
            ['id' => 4, 'nombre' => 'Galletitas Oreo', 'codigo_barras' => '10000004', 'precio_costo' => 200, 'precio_venta' => 350, 'stock_minimo' => 20],
            ['id' => 5, 'nombre' => 'Arroz 1kg', 'codigo_barras' => '10000005', 'precio_costo' => 350, 'precio_venta' => 500, 'stock_minimo' => 10],
            ['id' => 6, 'nombre' => 'Fideos Tallarín 500g', 'codigo_barras' => '10000006', 'precio_costo' => 200, 'precio_venta' => 300, 'stock_minimo' => 15],
            ['id' => 7, 'nombre' => 'Aceite Girasol 1L', 'codigo_barras' => '10000007', 'precio_costo' => 500, 'precio_venta' => 700, 'stock_minimo' => 8],
            ['id' => 8, 'nombre' => 'Yerba Mate 1kg', 'codigo_barras' => '10000008', 'precio_costo' => 650, 'precio_venta' => 900, 'stock_minimo' => 10],
            ['id' => 9, 'nombre' => 'Leche Larga Vida 1L', 'codigo_barras' => '10000009', 'precio_costo' => 300, 'precio_venta' => 450, 'stock_minimo' => 20],
            ['id' => 10, 'nombre' => 'Jugo Naranja 1L', 'codigo_barras' => '10000010', 'precio_costo' => 400, 'precio_venta' => 550, 'stock_minimo' => 10],
        ];

        foreach ($productos as $data) {
            Producto::updateOrCreate(
                ['id' => $data['id']],
                [
                    'categoria_id' => 1,
                    'marca_id' => 1,
                    'proveedor_id' => 1,
                    'nombre' => $data['nombre'],
                    'codigo_barras' => $data['codigo_barras'],
                    'precio_costo' => $data['precio_costo'],
                    'precio_venta' => $data['precio_venta'],
                    'stock_minimo' => $data['stock_minimo'],
                    'unidad_medida' => 'Unidad',
                    'estado' => true,
                    'descripcion' => $data['nombre'],
                ]
            );
        }
    }

    private function crearProductosB(): void
    {
        $productos = [
            ['id' => 11, 'nombre' => 'Pepsi 500ml', 'codigo_barras' => '20000001', 'precio_costo' => 500, 'precio_venta' => 750, 'stock_minimo' => 10],
            ['id' => 12, 'nombre' => 'Agua con Gas 1L', 'codigo_barras' => '20000002', 'precio_costo' => 220, 'precio_venta' => 380, 'stock_minimo' => 15],
            ['id' => 13, 'nombre' => 'Lays 120g', 'codigo_barras' => '20000003', 'precio_costo' => 420, 'precio_venta' => 650, 'stock_minimo' => 10],
            ['id' => 14, 'nombre' => 'Chocolatina Águila', 'codigo_barras' => '20000004', 'precio_costo' => 180, 'precio_venta' => 300, 'stock_minimo' => 20],
            ['id' => 15, 'nombre' => 'Arroz Gallo 1kg', 'codigo_barras' => '20000005', 'precio_costo' => 370, 'precio_venta' => 520, 'stock_minimo' => 10],
            ['id' => 16, 'nombre' => 'Spaghetti Matarazzo 500g', 'codigo_barras' => '20000006', 'precio_costo' => 190, 'precio_venta' => 290, 'stock_minimo' => 15],
            ['id' => 17, 'nombre' => 'Aceite Natura 1L', 'codigo_barras' => '20000007', 'precio_costo' => 480, 'precio_venta' => 680, 'stock_minimo' => 8],
            ['id' => 18, 'nombre' => 'Yerba Playadito 1kg', 'codigo_barras' => '20000008', 'precio_costo' => 700, 'precio_venta' => 950, 'stock_minimo' => 10],
            ['id' => 19, 'nombre' => 'Leche Ilolay 1L', 'codigo_barras' => '20000009', 'precio_costo' => 280, 'precio_venta' => 430, 'stock_minimo' => 20],
            ['id' => 20, 'nombre' => 'Jugo Tang 1L', 'codigo_barras' => '20000010', 'precio_costo' => 380, 'precio_venta' => 530, 'stock_minimo' => 10],
        ];

        foreach ($productos as $data) {
            Producto::updateOrCreate(
                ['id' => $data['id']],
                [
                    'categoria_id' => 1,
                    'marca_id' => 1,
                    'proveedor_id' => 1,
                    'nombre' => $data['nombre'],
                    'codigo_barras' => $data['codigo_barras'],
                    'precio_costo' => $data['precio_costo'],
                    'precio_venta' => $data['precio_venta'],
                    'stock_minimo' => $data['stock_minimo'],
                    'unidad_medida' => 'Unidad',
                    'estado' => true,
                    'descripcion' => $data['nombre'],
                ]
            );
        }
    }

    private function adjuntarProductosASucursales(): void
    {
        $sucursalesA = [1, 2];
        $productosA = range(1, 10);

        foreach ($sucursalesA as $sucursalId) {
            $sucursal = Sucursal::find($sucursalId);
            if (!$sucursal) continue;

            foreach ($productosA as $productoId) {
                $fisica = ($sucursalId === 1) ? 100 : 50;
                $reservada = ($sucursalId === 1) ? 10 : 5;

                DB::table('producto_sucursal')->updateOrInsert(
                    ['producto_id' => $productoId, 'sucursal_id' => $sucursalId],
                    [
                        'cantidad_fisica' => $fisica,
                        'cantidad_reservada' => $reservada,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $sucursalesB = [3, 4];
        $productosB = range(11, 20);

        foreach ($sucursalesB as $sucursalId) {
            $sucursal = Sucursal::find($sucursalId);
            if (!$sucursal) continue;

            foreach ($productosB as $productoId) {
                $fisica = ($sucursalId === 3) ? 100 : 50;
                $reservada = ($sucursalId === 3) ? 10 : 5;

                DB::table('producto_sucursal')->updateOrInsert(
                    ['producto_id' => $productoId, 'sucursal_id' => $sucursalId],
                    [
                        'cantidad_fisica' => $fisica,
                        'cantidad_reservada' => $reservada,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function crearLotes(): void
    {
        $lotes = [
            ['id' => 1, 'producto_id' => 1, 'sucursal_id' => 1, 'stock_inicial' => 100, 'stock_actual' => 97, 'fecha_vencimiento' => Carbon::now()->addMonths(6)],
            ['id' => 2, 'producto_id' => 2, 'sucursal_id' => 1, 'stock_inicial' => 100, 'stock_actual' => 100, 'fecha_vencimiento' => Carbon::now()->addMonths(8)],
            ['id' => 3, 'producto_id' => 3, 'sucursal_id' => 1, 'stock_inicial' => 100, 'stock_actual' => 99, 'fecha_vencimiento' => Carbon::now()->addMonths(4)],
            ['id' => 4, 'producto_id' => 1, 'sucursal_id' => 2, 'stock_inicial' => 50, 'stock_actual' => 50, 'fecha_vencimiento' => Carbon::now()->addMonths(6)],
            ['id' => 5, 'producto_id' => 3, 'sucursal_id' => 2, 'stock_inicial' => 50, 'stock_actual' => 50, 'fecha_vencimiento' => Carbon::now()->addMonths(5)],
            ['id' => 6, 'producto_id' => 11, 'sucursal_id' => 3, 'stock_inicial' => 100, 'stock_actual' => 99, 'fecha_vencimiento' => Carbon::now()->addMonths(6)],
            ['id' => 7, 'producto_id' => 13, 'sucursal_id' => 3, 'stock_inicial' => 100, 'stock_actual' => 100, 'fecha_vencimiento' => Carbon::now()->addMonths(4)],
            ['id' => 8, 'producto_id' => 11, 'sucursal_id' => 4, 'stock_inicial' => 50, 'stock_actual' => 50, 'fecha_vencimiento' => Carbon::now()->addMonths(6)],
        ];

        foreach ($lotes as $data) {
            Lote::updateOrCreate(
                ['id' => $data['id']],
                [
                    'producto_id' => $data['producto_id'],
                    'sucursal_id' => $data['sucursal_id'],
                    'stock_inicial' => $data['stock_inicial'],
                    'stock_actual' => $data['stock_actual'],
                    'fecha_vencimiento' => $data['fecha_vencimiento'],
                    'estado_liquidacion' => false,
                ]
            );
        }
    }

    private function crearCajas(): void
    {
        Caja::updateOrCreate(
            ['id' => 1],
            ['sucursal_id' => 1, 'nombre' => 'Caja Principal A', 'estado' => true]
        );

        Caja::updateOrCreate(
            ['id' => 2],
            ['sucursal_id' => 1, 'nombre' => 'Caja Kiosco Ventana A', 'estado' => true]
        );

        Caja::updateOrCreate(
            ['id' => 3],
            ['sucursal_id' => 2, 'nombre' => 'Caja Secundaria A', 'estado' => true]
        );

        Caja::updateOrCreate(
            ['id' => 4],
            ['sucursal_id' => 3, 'nombre' => 'Caja Principal B', 'estado' => true]
        );

        Caja::updateOrCreate(
            ['id' => 5],
            ['sucursal_id' => 4, 'nombre' => 'Caja Secundaria B', 'estado' => true]
        );
    }

    private function crearTurnos(): void
    {
        // Turno cerrado A (histórico)
        $turnoA = TurnoCaja::updateOrCreate(
            ['id' => 1],
            [
                'caja_id' => 1,
                'user_id' => $this->userIdA(), // admin.a
                'sucursal_id' => 1,
                'saldo_inicial' => 10000,
                'monto_apertura' => 10000,
                'fecha_apertura' => Carbon::now()->subDays(1)->setTime(8, 0, 0),
                'fecha_cierre' => Carbon::now()->subDays(1)->setTime(18, 0, 0),
                'estado' => 'Cerrado',
            ]
        );

        // Turno abierto A
        TurnoCaja::updateOrCreate(
            ['id' => 2],
            [
                'caja_id' => 1,
                'user_id' => $this->userIdA(), // admin.a
                'sucursal_id' => 1,
                'saldo_inicial' => 10000,
                'monto_apertura' => 10000,
                'fecha_apertura' => Carbon::now()->setTime(8, 0, 0),
                'estado' => 'Abierto',
            ]
        );

        // Turno cerrado B (histórico)
        TurnoCaja::updateOrCreate(
            ['id' => 3],
            [
                'caja_id' => 4,
                'user_id' => $this->userIdB(), // admin.b
                'sucursal_id' => 3,
                'saldo_inicial' => 10000,
                'monto_apertura' => 10000,
                'fecha_apertura' => Carbon::now()->subDays(1)->setTime(8, 0, 0),
                'fecha_cierre' => Carbon::now()->subDays(1)->setTime(18, 0, 0),
                'estado' => 'Cerrado',
            ]
        );

        // Turno abierto B
        TurnoCaja::updateOrCreate(
            ['id' => 4],
            [
                'caja_id' => 4,
                'user_id' => $this->userIdB(), // admin.b
                'sucursal_id' => 3,
                'saldo_inicial' => 10000,
                'monto_apertura' => 10000,
                'fecha_apertura' => Carbon::now()->setTime(8, 0, 0),
                'estado' => 'Abierto',
            ]
        );
    }

    private function crearVentas(): void
    {
        if (!Venta::where('id', 1)->exists()) {
            Venta::create([
                'id' => 1,
                'turno_caja_id' => 1,
                'consumidor_id' => $this->consumidorA(),
                'metodo_pago' => 'EFECTIVO',
                'total' => 1500,
                'estado' => 'Completada',
            ]);

            DetalleVenta::create([
                'venta_id' => 1,
                'producto_id' => 1,
                'cantidad' => 1,
                'precio_unitario' => 800,
                'subtotal' => 800,
            ]);

            DetalleVenta::create([
                'venta_id' => 1,
                'producto_id' => 3,
                'cantidad' => 1,
                'precio_unitario' => 700,
                'subtotal' => 700,
            ]);

            MovimientoCaja::create([
                'turno_caja_id' => 1,
                'tipo' => 'INGRESO',
                'concepto' => 'venta',
                'metodo_pago' => 'EFECTIVO',
                'monto' => 1500,
                'descripcion' => 'Venta #1 - Efectivo',
            ]);
        }

        if (!Venta::where('id', 2)->exists()) {
            Venta::create([
                'id' => 2,
                'turno_caja_id' => 1,
                'consumidor_id' => $this->consumidorA(), // Juan Pérez
                'metodo_pago' => 'CUENTA_CORRIENTE',
                'total' => 2300,
                'estado' => 'Completada',
            ]);

            DetalleVenta::create([
                'venta_id' => 2,
                'producto_id' => 5,
                'cantidad' => 2,
                'precio_unitario' => 500,
                'subtotal' => 1000,
            ]);

            DetalleVenta::create([
                'venta_id' => 2,
                'producto_id' => 7,
                'cantidad' => 1,
                'precio_unitario' => 700,
                'subtotal' => 700,
            ]);

            DetalleVenta::create([
                'venta_id' => 2,
                'producto_id' => 4,
                'cantidad' => 2,
                'precio_unitario' => 300,
                'subtotal' => 600,
            ]);

            // Actualizar saldo de cuenta corriente
            $cc = CuentaCorriente::whereHas('consumidor', fn($q) => $q->where('id', $this->consumidorA()))->first();
            if ($cc) {
                $cc->update(['saldo_deudor' => 2300]);
                MovimientoCuentaCorriente::create([
                    'cuenta_corriente_id' => $cc->id,
                    'monto' => 2300,
                    'tipo' => 'debito',
                    'descripcion' => 'Venta #2 a cuenta corriente',
                ]);
            }
        }

        if (!Venta::where('id', 3)->exists()) {
            Venta::create([
                'id' => 3,
                'turno_caja_id' => 3,
                'consumidor_id' => $this->consumidorB(), // Pedro Sánchez
                'metodo_pago' => 'EFECTIVO',
                'total' => 1500,
                'estado' => 'Completada',
            ]);

            DetalleVenta::create([
                'venta_id' => 3,
                'producto_id' => 11,
                'cantidad' => 1,
                'precio_unitario' => 750,
                'subtotal' => 750,
            ]);

            DetalleVenta::create([
                'venta_id' => 3,
                'producto_id' => 13,
                'cantidad' => 1,
                'precio_unitario' => 650,
                'subtotal' => 650,
            ]);

            DetalleVenta::create([
                'venta_id' => 3,
                'producto_id' => 12,
                'cantidad' => 1,
                'precio_unitario' => 100,
                'subtotal' => 100,
            ]);

            MovimientoCaja::create([
                'turno_caja_id' => 3,
                'tipo' => 'INGRESO',
                'concepto' => 'venta',
                'metodo_pago' => 'EFECTIVO',
                'monto' => 1500,
                'descripcion' => 'Venta #3 - Efectivo',
            ]);
        }
    }

    private function crearPedidosWeb(): void
    {
        if (!PedidoWeb::where('id', 1)->exists()) {
            PedidoWeb::create([
                'id' => 1,
                'comercio_id' => 1,
                'sucursal_id' => 1,
                'cliente_nombre' => 'Roberto',
                'cliente_telefono' => '155555111',
                'cliente_direccion' => 'Av. Cliente 777',
                'subtotal' => 1600,
                'costo_envio' => 200,
                'total' => 1800,
                'metodo_pago' => 'efectivo',
                'estado_pedido' => 'nuevo',
            ]);

            PedidoWebItem::create([
                'pedido_web_id' => 1,
                'producto_id' => 1,
                'cantidad' => 2,
                'precio_unitario' => 800,
                'subtotal' => 1600,
            ]);
        }

        if (!PedidoWeb::where('id', 2)->exists()) {
            PedidoWeb::create([
                'id' => 2,
                'comercio_id' => 2,
                'sucursal_id' => 3,
                'cliente_nombre' => 'Florencia',
                'cliente_telefono' => '155555222',
                'cliente_direccion' => 'Calle Pedido 888',
                'subtotal' => 1950,
                'costo_envio' => 250,
                'total' => 2200,
                'metodo_pago' => 'transferencia',
                'estado_pedido' => 'preparando',
                'estado_pago' => 'pagado',
            ]);

            PedidoWebItem::create([
                'pedido_web_id' => 2,
                'producto_id' => 11,
                'cantidad' => 2,
                'precio_unitario' => 750,
                'subtotal' => 1500,
            ]);

            PedidoWebItem::create([
                'pedido_web_id' => 2,
                'producto_id' => 13,
                'cantidad' => 1,
                'precio_unitario' => 450,
                'subtotal' => 450,
            ]);
        }
    }

    private function crearOrdenesCompra(): void
    {
        if (!OrdenCompra::where('id', 1)->exists()) {
            OrdenCompra::create([
                'id' => 1,
                'proveedor_id' => 1,
                'sucursal_id' => 2,
                'user_id' => $this->userIdA(),
                'nro_comprobante' => 'OC-001',
                'fecha_emision' => Carbon::now()->subDays(2),
                'fecha_entrega_esperada' => Carbon::now()->addDays(5),
                'estado' => 'Borrador',
                'total_estimado' => 5000,
                'observaciones' => 'Pedido de reposición Almacén Norte',
            ]);

            OrdenCompraDetalle::create([
                'orden_compra_id' => 1,
                'producto_id' => 1,
                'cantidad_pedida' => 10,
                'cantidad_recibida' => 0,
                'costo_unitario_estimado' => 550,
                'subtotal_estimado' => 5500,
            ]);

            OrdenCompraDetalle::create([
                'orden_compra_id' => 1,
                'producto_id' => 3,
                'cantidad_pedida' => 5,
                'cantidad_recibida' => 0,
                'costo_unitario_estimado' => 450,
                'subtotal_estimado' => 2250,
            ]);
        }

        if (!OrdenCompra::where('id', 2)->exists()) {
            OrdenCompra::create([
                'id' => 2,
                'proveedor_id' => 1,
                'sucursal_id' => 4,
                'user_id' => $this->userIdB(),
                'nro_comprobante' => 'OC-002',
                'fecha_emision' => Carbon::now()->subDays(1),
                'fecha_entrega_esperada' => Carbon::now()->addDays(7),
                'estado' => 'Borrador',
                'total_estimado' => 6000,
                'observaciones' => 'Pedido de reposición Almacén Sur',
            ]);

            OrdenCompraDetalle::create([
                'orden_compra_id' => 2,
                'producto_id' => 11,
                'cantidad_pedida' => 10,
                'cantidad_recibida' => 0,
                'costo_unitario_estimado' => 500,
                'subtotal_estimado' => 5000,
            ]);

            OrdenCompraDetalle::create([
                'orden_compra_id' => 2,
                'producto_id' => 13,
                'cantidad_pedida' => 5,
                'cantidad_recibida' => 0,
                'costo_unitario_estimado' => 420,
                'subtotal_estimado' => 2100,
            ]);
        }
    }

    private function sincronizarSecuencias(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $tablesConExplicitIds = [
            'comercios', 'sucursales', 'categorias', 'marcas', 'proveedores',
            'consumidores', 'productos', 'lotes', 'cajas', 'turno_cajas',
            'ventas', 'pedidos_web', 'orden_compras', 'transferencia_sugeridas',
        ];

        foreach ($tablesConExplicitIds as $table) {
            if (DB::table($table)->count() === 0) continue;
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'), coalesce(max(id),0) + 1, false) FROM \"{$table}\";"
            );
        }
    }

    private function crearTransferenciasSugeridas(): void
    {
        if (!TransferenciaSugerida::where('id', 1)->exists()) {
            TransferenciaSugerida::create([
                'id' => 1,
                'origen_id' => 2,
                'destino_id' => 1,
                'producto_id' => 3,
                'cantidad' => 10,
                'estado' => 'pendiente',
            ]);
        }

        if (!TransferenciaSugerida::where('id', 2)->exists()) {
            TransferenciaSugerida::create([
                'id' => 2,
                'origen_id' => 4,
                'destino_id' => 3,
                'producto_id' => 13,
                'cantidad' => 10,
                'estado' => 'pendiente',
            ]);
        }
    }
}
