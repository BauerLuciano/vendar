# Datos de Testing Multi-Tenant

## Comando

```bash
php artisan test:data              # Seedear datos (idempotente)
php artisan test:data --fresh      # Truncar y re-seedear
```

## Comercios

| ID | Nombre | Slug | Plan | Plan ID |
|----|--------|------|------|---------|
| 1 | Almacén Norte | almacen-norte | básico | 1 |
| 2 | Almacén Sur | almacen-sur | profesional | 2 |

## Sucursales

| ID | Nombre | Comercio ID | Comercio |
|----|--------|-------------|----------|
| 1 | Casa Central | 1 | Almacén Norte |
| 2 | Sucursal Norte | 1 | Almacén Norte |
| 3 | Casa Central | 2 | Almacén Sur |
| 4 | Sucursal Sur | 2 | Almacén Sur |

## Usuarios

| Email | Password | Rol | Comercio | Sucursal |
|-------|----------|-----|----------|----------|
| admin.a@test.com | password | SuperAdmin | Almacén Norte | Casa Central (1) |
| user.a@test.com | password | Cajero | Almacén Norte | Casa Central (1) |
| admin.b@test.com | password | SuperAdmin | Almacén Sur | Casa Central (3) |
| user.b@test.com | password | Cajero | Almacén Sur | Casa Central (3) |

### Usuarios existentes actualizados

| Email | Comercio | Sucursal |
|-------|----------|----------|
| luciano@gmail.com | Almacén Norte (1) | Casa Central (1) |
| test@example.com | Almacén Norte (1) | Casa Central (1) |
| adminvendar@gmail.com | — | — (Admin Global) |

## Productos

### Almacén Norte (IDs 1-10)

| ID | Nombre | Precio Venta | Cod. Barras |
|----|--------|-------------|-------------|
| 1 | Coca Cola 500ml | $800 | 10000001 |
| 2 | Agua Mineral 1L | $400 | 10000002 |
| 3 | Papas Fritas 120g | $700 | 10000003 |
| 4 | Galletitas Oreo | $350 | 10000004 |
| 5 | Arroz 1kg | $500 | 10000005 |
| 6 | Fideos Tallarín 500g | $300 | 10000006 |
| 7 | Aceite Girasol 1L | $700 | 10000007 |
| 8 | Yerba Mate 1kg | $900 | 10000008 |
| 9 | Leche Larga Vida 1L | $450 | 10000009 |
| 10 | Jugo Naranja 1L | $550 | 10000010 |

### Almacén Sur (IDs 11-20)

| ID | Nombre | Precio Venta | Cod. Barras |
|----|--------|-------------|-------------|
| 11 | Pepsi 500ml | $750 | 20000001 |
| 12 | Agua con Gas 1L | $380 | 20000002 |
| 13 | Lays 120g | $650 | 20000003 |
| 14 | Chocolatina Águila | $300 | 20000004 |
| 15 | Arroz Gallo 1kg | $520 | 20000005 |
| 16 | Spaghetti Matarazzo 500g | $290 | 20000006 |
| 17 | Aceite Natura 1L | $680 | 20000007 |
| 18 | Yerba Playadito 1kg | $950 | 20000008 |
| 19 | Leche Ilolay 1L | $430 | 20000009 |
| 20 | Jugo Tang 1L | $530 | 20000010 |

## Stock por Sucursal

### Almacén Norte
- **Sucursal 1** (Casa Central): 100 uds físicas, 10 reservadas por producto
- **Sucursal 2** (Sucursal Norte): 50 uds físicas, 5 reservadas por producto

### Almacén Sur
- **Sucursal 3** (Casa Central): 100 uds físicas, 10 reservadas por producto
- **Sucursal 4** (Sucursal Sur): 50 uds físicas, 5 reservadas por producto

## Consumidores

| ID | Nombre | Apellido | Comercio | Límite CC |
|----|--------|----------|----------|-----------|
| 1 | Consumidor | Final | Almacén Norte | $0 |
| 2 | Juan | Pérez | Almacén Norte | $5.000 |
| 3 | María | García | Almacén Norte | $3.000 |
| 4 | Carlos | López | Almacén Norte | $10.000 |
| 5 | Ana | Martínez | Almacén Norte | $2.000 |
| 6 | Pedro | Sánchez | Almacén Sur | $5.000 |
| 7 | Laura | Rodríguez | Almacén Sur | $3.000 |
| 8 | Diego | Fernández | Almacén Sur | $10.000 |
| 9 | Valentina | Gómez | Almacén Sur | $2.000 |
| 10 | Sofía | Díaz | Almacén Sur | $7.000 |

## Cajas

| ID | Nombre | Sucursal ID | Sucursal |
|----|--------|-------------|----------|
| 1 | Caja Principal | 1 | Casa Central (A) |
| 2 | Caja Kiosco Ventana | 1 | Casa Central (A) |
| 3 | Caja Secundaria A | 2 | Sucursal Norte |
| 4 | Caja Principal B | 3 | Casa Central (B) |
| 5 | Caja Secundaria B | 4 | Sucursal Sur |

## Turnos

| ID | Caja ID | Cajero | Sucursal | Estado |
|----|---------|--------|----------|--------|
| 1 | 1 | admin.a | 1 | Cerrado (histórico) |
| 2 | 1 | admin.a | 1 | Abierto |
| 3 | 4 | admin.b | 3 | Cerrado (histórico) |
| 4 | 4 | admin.b | 3 | Abierto |

## Ventas

| ID | Turno | Consumidor | Método Pago | Total | Comercio |
|----|-------|-----------|-------------|-------|----------|
| 1 | 1 (cerrado A) | 1 - Cons Final | efectivo | $1.500 | Almacén Norte |
| 2 | 1 (cerrado A) | 2 - Juan Pérez | cta cte | $2.300 | Almacén Norte |
| 3 | 3 (cerrado B) | 6 - Pedro Sánchez | efectivo | $1.500 | Almacén Sur |

### Detalles de Ventas

**Venta 1**: 1x Coca Cola $800 + 1x Papas Fritas $700 = $1.500
**Venta 2**: 2x Arroz $1.000 + 1x Aceite $700 + 2x Oreo $600 = $2.300 (a cuenta corriente, saldo deudor $2.300)
**Venta 3**: 1x Pepsi $750 + 1x Lays $650 + 1x Agua Gas $100 = $1.500

## Pedidos Web

| ID | Comercio | Sucursal | Cliente | Total | Estado |
|----|----------|----------|---------|-------|--------|
| 1 | Almacén Norte | Casa Central (1) | Roberto | $1.800 | nuevo |
| 2 | Almacén Sur | Casa Central (3) | Florencia | $2.200 | en preparación, pagado |

## Órdenes de Compra

| ID | Sucursal | Proveedor | Total Est. | Estado |
|----|----------|-----------|-----------|--------|
| 1 | Sucursal Norte (2) | Proveedor General | $5.000 | pendiente |
| 2 | Sucursal Sur (4) | Proveedor General | $6.000 | pendiente |

## Transferencias Sugeridas

| ID | Origen | Destino | Producto | Cantidad | Estado |
|----|--------|---------|----------|----------|--------|
| 1 | Sucursal Norte (2) | Casa Central (1) | Papas Fritas 120g | 10 | pendiente |
| 2 | Sucursal Sur (4) | Casa Central (3) | Lays 120g | 10 | pendiente |

## Notas

- Todos los passwords son `password`
- El `--fresh` trunca tablas de prueba antes de re-seedear
- Categoria, Marca y Proveedor son globales (sin `comercio_id`)
- Correr `php artisan test:data` después de `php artisan db:seed`
