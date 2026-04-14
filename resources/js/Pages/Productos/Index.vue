<template>
    <Head title="Gestión de Productos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Módulo</p>
                    <h2 class="text-xl font-semibold text-slate-800 mt-0.5">Control de Inventario</h2>
                </div>
            </div>
        </template>

        <div v-if="menuAbierto" @click="cerrarMenu" class="fixed inset-0 z-30"></div>

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-5">

                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-700">Listado de productos</h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <span class="font-medium text-slate-600">{{ productosFiltrados.length }}</span>
                            de {{ productos.length }} productos
                            <span v-if="hayFiltrosActivos" class="text-blue-500 font-medium"> · filtrado</span>
                        </p>
                    </div>
                    <button @click="abrirNuevo"
                        class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-medium text-sm hover:bg-blue-700 active:scale-95 shadow-sm shadow-blue-600/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Nuevo producto
                    </button>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl px-5 py-4 shadow-sm flex flex-col md:flex-row md:items-center gap-3">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input
                                v-model="busqueda"
                                type="text"
                                placeholder="Buscar por nombre o código..."
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>

                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/>
                            </svg>
                            <select
                                v-model="filtroCategoria"
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todas las categorías</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                            </select>
                        </div>

                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                            </svg>
                            <select
                                v-model="filtroMarca"
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todas las marcas</option>
                                <option v-for="m in marcas" :key="m.id" :value="m.id">{{ m.nombreMarca }}</option>
                            </select>
                        </div>

                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <select
                                v-model="filtroEstado"
                                class="w-full pl-9 pr-3 py-2.5 text-sm border border-slate-200 rounded-lg bg-slate-50 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none">
                                <option value="">Todos los estados</option>
                                <option value="activo">Activos</option>
                                <option value="inactivo">Inactivos</option>
                            </select>
                        </div>
                    </div>

                    <button
                        v-if="hayFiltrosActivos"
                        @click="limpiarFiltros"
                        class="mt-3 md:mt-0 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-red-50 text-red-600 border border-red-100 text-xs font-bold hover:bg-red-100 hover:text-red-700 transition-colors whitespace-nowrap w-full md:w-auto">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Limpiar Filtros
                    </button>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 bg-slate-50/80 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                                    <th class="px-5 py-4">Cód. / SKU</th>
                                    <th class="px-5 py-4">Producto</th>
                                    <th class="px-5 py-4 text-center">Stock</th>
                                    <th class="px-5 py-4 text-right">P. Venta</th>
                                    <th class="px-5 py-4 text-center">Estado</th>
                                    <th class="px-5 py-4 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">

                                <tr v-if="productosFiltrados.length === 0 && hayFiltrosActivos">
                                    <td colspan="6" class="py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center">
                                                <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-slate-600">Sin resultados</p>
                                                <p class="text-xs text-slate-400 mt-1">Ningún producto coincide con los filtros aplicados.</p>
                                            </div>
                                            <button @click="limpiarFiltros" class="text-xs text-blue-600 font-medium hover:underline">Limpiar filtros</button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-else-if="productos.length === 0">
                                    <td colspan="6" class="py-20 text-center">
                                        <div class="flex flex-col items-center gap-3 text-slate-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-slate-400">No hay mercadería cargada</p>
                                                <p class="text-xs text-slate-300 mt-1">Registrá tu primer producto para comenzar.</p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-for="p in productosPaginados" :key="p.id" class="hover:bg-blue-50/30 group">

                                    <td class="px-5 py-4" :class="{ 'opacity-40 grayscale': !p.estado }">
                                        <span class="font-mono text-[11px] font-semibold text-blue-700 bg-blue-50 border border-blue-100 px-2.5 py-1 rounded-md tracking-tight block w-max">
                                            {{ p.codigo_barras }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4" :class="{ 'opacity-40 grayscale': !p.estado }">
                                        <div class="flex items-center gap-3">
                                            <div class="w-11 h-11 shrink-0 rounded-xl overflow-hidden border border-slate-100 bg-slate-50 flex items-center justify-center">
                                                <img v-if="p.url_imagen" :src="p.url_imagen" class="w-full h-full object-cover">
                                                <svg v-else class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-sm font-semibold text-slate-800 leading-snug">{{ p.nombre }}</p>
                                                <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                                    <span class="text-[10px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
                                                        {{ p.categoria?.nombreCategoria }}
                                                    </span>
                                                    <span class="text-[10px] font-medium text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">
                                                        {{ p.marca?.nombreMarca }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 text-center" :class="{ 'opacity-40 grayscale': !p.estado }">
                                        <div class="inline-flex flex-col items-center gap-1">
                                            <span :class="calcularTotalStock(p) <= p.stock_minimo
                                                    ? 'text-red-700 bg-red-50 border-red-200'
                                                    : 'text-emerald-700 bg-emerald-50 border-emerald-200'"
                                                class="px-3 py-1 rounded-lg text-xs font-bold border tabular-nums">
                                                
                                                {{ formatearCantidad(calcularTotalStock(p)) }}
                                                
                                            </span>
                                            <span class="text-[9px] text-slate-400 uppercase tracking-widest font-medium">{{ p.unidad_medida }}</span>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 text-right" :class="{ 'opacity-40 grayscale': !p.estado }">
                                        <div class="flex items-baseline justify-end gap-0.5">
                                            <span class="text-xs text-slate-400">$</span>
                                            <span class="text-base font-bold text-slate-800 tabular-nums">{{ Number(p.precio_venta).toLocaleString('es-AR') }}</span>
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 text-center" :class="{ 'opacity-40 grayscale': !p.estado }">
                                        <span :class="p.estado
                                                ? 'text-emerald-700 bg-emerald-50 border-emerald-200'
                                                : 'text-slate-400 bg-slate-100 border-slate-200'"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold border">
                                            <span :class="p.estado ? 'bg-emerald-500' : 'bg-slate-400'" class="w-1.5 h-1.5 rounded-full"></span>
                                            {{ p.estado ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 text-center relative opacity-100">
                                        <!-- Botón de acciones: solo ícono tres puntos (igual a órdenes de compra) -->
                                        <button @click.stop="toggleMenu(p.id)"
                                            class="p-2 rounded-full text-slate-400 hover:text-sky-600 hover:bg-sky-100 transition-colors focus:outline-none">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                            </svg>
                                        </button>

                                        <!-- Menú desplegable con posición ajustada -->
                                        <div v-if="menuAbierto === p.id"
                                            class="absolute right-10 top-10 w-48 bg-white rounded-xl shadow-xl border border-slate-200 z-[100] py-1.5 overflow-hidden">

                                            <div class="px-4 py-2 border-b border-slate-50 mb-1">
                                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest truncate">{{ p.nombre }}</p>
                                            </div>

                                            <button @click="abrirAjuste(p)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium text-violet-600 hover:bg-violet-50 flex items-center gap-2.5">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                                                Ajustar stock
                                            </button>

                                            <button @click="abrirAuditoria(p)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50 flex items-center gap-2.5">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                Historial
                                            </button>

                                            <button @click="abrirStock(p)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 flex items-center gap-2.5">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                                Stock por suc.
                                            </button>

                                            <button @click="abrirDetalle(p)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium text-sky-600 hover:bg-sky-50 flex items-center gap-2.5">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                Detalles
                                            </button>

                                            <button @click="abrirEditar(p)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium text-amber-600 hover:bg-amber-50 flex items-center gap-2.5">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                                Editar
                                            </button>

                                            <div class="border-t border-slate-100 my-1"></div>

                                            <button @click="toggleEstado(p)"
                                                class="w-full text-left px-4 py-2.5 text-xs font-medium flex items-center gap-2.5"
                                                :class="p.estado ? 'text-red-500 hover:bg-red-50' : 'text-emerald-600 hover:bg-emerald-50'">
                                                <svg v-if="p.estado" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                {{ p.estado ? 'Dar de baja' : 'Reactivar' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación modificada para que coincida con el estilo de Órdenes de Compra -->
                    <div v-if="productosFiltrados.length > 0" class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <span class="text-sm text-slate-500 font-medium">
                            Mostrando 
                            {{ (paginaActual - 1) * itemsPorPagina + 1 }} 
                            a 
                            {{ Math.min(paginaActual * itemsPorPagina, productosFiltrados.length) }} 
                            de 
                            {{ productosFiltrados.length }} productos
                        </span>
                        <div class="flex flex-wrap justify-center gap-1">
                            <button @click="paginaAnterior" :disabled="paginaActual === 1"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                                :class="paginaActual === 1 
                                    ? 'opacity-50 cursor-not-allowed bg-slate-50 text-slate-400 border-slate-200' 
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100 cursor-pointer'">
                                Anterior
                            </button>
                            <button @click="paginaSiguiente" :disabled="paginaActual === totalPaginas"
                                class="px-3 py-1.5 text-sm rounded-lg transition-colors border"
                                :class="paginaActual === totalPaginas 
                                    ? 'opacity-50 cursor-not-allowed bg-slate-50 text-slate-400 border-slate-200' 
                                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100 cursor-pointer'">
                                Siguiente
                            </button>
                        </div>
                    </div>

                </div>

            </div>
        </div>

        <ModalProducto :mostrar="verModal" :producto="seleccionado" :categorias="categorias" :marcas="marcas" :proveedores="proveedores" @cerrar="cerrarModalGlobal" />
        <DetalleProducto :mostrar="verDetalle" :producto="seleccionado" @cerrar="verDetalle = false" />

        <div v-if="verStock && seleccionado" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Disponibilidad física</p>
                        <h3 class="text-base font-semibold text-slate-700 mt-0.5">{{ seleccionado.nombre }}</h3>
                    </div>
                    <button @click="verStock = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 text-lg leading-none">✕</button>
                </div>
                <div class="p-6 space-y-2">
                    <div v-if="!seleccionado.sucursales || seleccionado.sucursales.length === 0"
                        class="text-center py-8 text-slate-400 text-sm bg-slate-50 rounded-xl">
                        Sin stock registrado en sucursales.
                    </div>
                    <div v-for="suc in seleccionado.sucursales" :key="suc.id"
                        class="flex justify-between items-center px-4 py-3 bg-slate-50 rounded-xl border border-slate-100">
                        <span class="text-sm font-medium text-slate-600">{{ suc.nombre }}</span>
                        <span class="text-sm font-semibold text-indigo-700">
                            {{ Number(suc.pivot?.cantidad_fisica || 0).toFixed(seleccionado.unidad_medida === 'Kg' ? 3 : 0) }}
                            <span class="text-[10px] text-indigo-400 ml-1">{{ seleccionado.unidad_medida }}</span>
                        </span>
                    </div>
                </div>
                <div class="px-6 pb-6">
                    <button @click="verStock = false" class="w-full py-3 bg-slate-900 text-white rounded-xl text-sm font-medium hover:bg-black active:scale-95">Cerrar</button>
                </div>
            </div>
        </div>

        <div v-if="verAjuste && seleccionado" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Gestión de inventario</p>
                        <h3 class="text-base font-semibold text-slate-700 mt-0.5">{{ seleccionado.nombre }}</h3>
                    </div>
                    <button @click="verAjuste = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 text-lg leading-none">✕</button>
                </div>
                <form @submit.prevent="guardarAjuste" class="p-6 space-y-5">
                    <div>
                        <label class="block text-[10px] font-medium uppercase text-slate-400 mb-1.5 tracking-widest">Sucursal</label>
                        <select v-model="formAjuste.sucursal_id" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:ring-violet-500 py-2.5 text-sm font-medium text-slate-700" required>
                            <option value="" disabled>Seleccione sucursal...</option>
                            <option v-for="suc in sucursales" :key="suc.id" :value="suc.id">{{ suc.nombre }}</option>
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <div class="flex-1">
                            <label class="block text-[10px] font-medium uppercase text-slate-400 mb-1.5 tracking-widest">Operación</label>
                            <select v-model="formAjuste.tipo_ajuste"
                                class="w-full rounded-xl border-slate-200 bg-slate-50 py-2.5 text-sm font-medium"
                                :class="formAjuste.tipo_ajuste === 'Restar' ? 'text-red-600' : 'text-emerald-600'">
                                <option value="Sumar">+ Ingresar</option>
                                <option value="Restar">− Descontar</option>
                            </select>
                        </div>
                        <div class="flex-1">
                            <label class="block text-[10px] font-medium uppercase text-slate-400 mb-1.5 tracking-widest">Cantidad ({{ seleccionado.unidad_medida }})</label>
                            <input v-model="formAjuste.cantidad" type="number" step="0.001" min="0.001"
                                class="w-full rounded-xl border-slate-200 bg-slate-50 focus:ring-violet-500 py-2.5 text-sm font-medium text-slate-700"
                                placeholder="0.000" required>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-medium uppercase text-slate-400 mb-1.5 tracking-widest">Motivo</label>
                        <select v-model="formAjuste.motivo" class="w-full rounded-xl border-slate-200 bg-slate-50 focus:ring-violet-500 py-2.5 text-sm font-medium text-slate-700" required>
                            <option>Rotura o Daño</option>
                            <option>Vencimiento</option>
                            <option>Faltante / Robo</option>
                            <option>Sobrante / Encontrado</option>
                            <option>Consumo Interno</option>
                            <option>Corrección de Inventario</option>
                            <option>Otro</option>
                        </select>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="verAjuste = false"
                            class="flex-1 py-3 rounded-xl text-sm font-medium text-slate-500 border border-slate-200 hover:bg-slate-50">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="flex-1 bg-violet-600 text-white py-3 rounded-xl text-sm font-medium hover:bg-violet-700 disabled:opacity-50"
                            :disabled="formAjuste.processing">
                            Guardar ajuste
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="verAuditoria && seleccionado" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl flex flex-col max-h-[88vh] border border-slate-100">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
                    <div>
                        <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Historial de movimientos</p>
                        <h3 class="text-base font-semibold text-slate-700 mt-0.5">
                            {{ seleccionado.nombre }}
                            <span class="text-slate-400 font-normal text-sm">({{ seleccionado.unidad_medida }})</span>
                        </h3>
                    </div>
                    <button @click="verAuditoria = false" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 text-lg leading-none">✕</button>
                </div>
                <div class="overflow-y-auto flex-1 custom-scrollbar">
                    <table class="w-full text-left">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                                <th class="px-5 py-3.5">Fecha</th>
                                <th class="px-5 py-3.5">Sucursal</th>
                                <th class="px-5 py-3.5">Detalles</th>
                                <th class="px-5 py-3.5 text-center">Previo</th>
                                <th class="px-5 py-3.5 text-center">Mov.</th>
                                <th class="px-5 py-3.5 text-center">Final</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-if="movimientos.length === 0">
                                <td colspan="6" class="py-16 text-center text-sm text-slate-400">Sin movimientos registrados para este producto.</td>
                            </tr>
                            <tr v-for="mov in movimientos" :key="mov.id" class="hover:bg-slate-50 bg-white">
                                <td class="px-5 py-3.5">
                                    <p class="text-xs font-medium text-slate-700">{{ new Date(mov.created_at).toLocaleDateString('es-AR') }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">{{ new Date(mov.created_at).toLocaleTimeString('es-AR') }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-xs font-medium text-indigo-600 uppercase tracking-tight">{{ mov.sucursal }}</td>
                                <td class="px-5 py-3.5">
                                    <p class="text-xs font-medium text-slate-700">{{ mov.tipo_movimiento }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[180px]">{{ mov.usuario }} · {{ mov.motivo || 'Sin motivo' }}</p>
                                </td>
                                <td class="px-5 py-3.5 text-center font-mono text-xs text-slate-300">{{ Number(mov.cantidad_anterior).toFixed(3) }}</td>
                                <td class="px-5 py-3.5 text-center font-mono text-sm font-semibold"
                                    :class="mov.cantidad_movimiento > 0 ? 'text-emerald-600' : 'text-red-500'">
                                    {{ mov.cantidad_movimiento > 0 ? '+' : '' }}{{ Number(mov.cantidad_movimiento).toFixed(3) }}
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <span class="font-mono text-xs font-semibold text-slate-700 bg-slate-100 px-2 py-1 rounded-md">
                                        {{ Number(mov.cantidad_actual).toFixed(3) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #e2e8f0; border-radius: 20px; }
.custom-scrollbar { scrollbar-width: thin; scrollbar-color: #e2e8f0 transparent; }
</style>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ModalProducto from './Componentes/ModalProducto.vue'; 
import DetalleProducto from './Componentes/DetalleProducto.vue'; 
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({ 
    productos: Array, 
    categorias: Array, 
    marcas: Array,
    proveedores: Array,
    sucursales: Array
});

const page = usePage();
const verModal = ref(false);
const verDetalle = ref(false);
const verStock = ref(false); 
const seleccionado = ref(null);

const verAjuste = ref(false);
const verAuditoria = ref(false);
const movimientos = ref([]);

const menuAbierto = ref(null);

// --- Filtros ---
const busqueda = ref('');
const filtroCategoria = ref('');
const filtroMarca = ref('');
const filtroEstado = ref('');

const productosFiltrados = computed(() => {
    return props.productos.filter(p => {
        const matchBusqueda = busqueda.value === '' ||
            p.nombre.toLowerCase().includes(busqueda.value.toLowerCase()) ||
            p.codigo_barras?.toLowerCase().includes(busqueda.value.toLowerCase());
        const matchCategoria = filtroCategoria.value === '' || p.categoria?.id == filtroCategoria.value;
        const matchMarca = filtroMarca.value === '' || p.marca?.id == filtroMarca.value;
        const matchEstado = filtroEstado.value === '' || 
            (filtroEstado.value === 'activo' && p.estado) || 
            (filtroEstado.value === 'inactivo' && !p.estado);
        return matchBusqueda && matchCategoria && matchMarca && matchEstado;
    });
});

const hayFiltrosActivos = computed(() => 
    busqueda.value !== '' || filtroCategoria.value !== '' || filtroMarca.value !== '' || filtroEstado.value !== ''
);

const limpiarFiltros = () => {
    busqueda.value = '';
    filtroCategoria.value = '';
    filtroMarca.value = '';
    filtroEstado.value = '';
};

// --- 🚀 LÓGICA DE PAGINACIÓN ---
const paginaActual = ref(1);
const itemsPorPagina = ref(7); // 7 por defecto, como pediste

// Si cambia algún filtro o la cantidad por página, volvemos a la página 1
watch([busqueda, filtroCategoria, filtroMarca, filtroEstado, itemsPorPagina], () => {
    paginaActual.value = 1;
});

const totalPaginas = computed(() => {
    return Math.ceil(productosFiltrados.value.length / itemsPorPagina.value) || 1;
});

const productosPaginados = computed(() => {
    const inicio = (paginaActual.value - 1) * itemsPorPagina.value;
    const fin = inicio + itemsPorPagina.value;
    return productosFiltrados.value.slice(inicio, fin);
});

const paginaAnterior = () => {
    if (paginaActual.value > 1) paginaActual.value--;
};

const paginaSiguiente = () => {
    if (paginaActual.value < totalPaginas.value) paginaActual.value++;
};
// --------------------------------

const toggleMenu = (id) => {
    menuAbierto.value = menuAbierto.value === id ? null : id;
};

const cerrarMenu = () => {
    menuAbierto.value = null;
};

const calcularTotalStock = (producto) => {
    if (!producto.sucursales) return 0;
    return producto.sucursales.reduce((acc, suc) => acc + Number(suc.pivot?.cantidad_fisica || 0), 0);
};

const formAjuste = useForm({
    sucursal_id: '',
    tipo_ajuste: 'Restar',
    cantidad: '',
    motivo: 'Rotura o Daño',
});

watch(() => page.props.flash, (nuevo) => {
    if (nuevo.exito) Swal.fire({ title: '¡Éxito!', text: nuevo.exito, icon: 'success', timer: 3000, showConfirmButton: false });
    if (nuevo.error) Swal.fire({ title: 'Error', text: nuevo.error, icon: 'error' });
}, { deep: true });

const abrirAjuste = (p) => {
    seleccionado.value = p;
    formAjuste.reset();
    if (props.sucursales && props.sucursales.length === 1) formAjuste.sucursal_id = props.sucursales[0].id;
    cerrarMenu();
    verAjuste.value = true;
};

const guardarAjuste = () => {
    formAjuste.post(route('productos.ajustar', seleccionado.value.id), {
        preserveScroll: true,
        onSuccess: () => { verAjuste.value = false; }
    });
};

const abrirAuditoria = async (p) => {
    seleccionado.value = p;
    cerrarMenu();
    verAuditoria.value = true;
    movimientos.value = [];
    try {
        const respuesta = await axios.get(route('productos.auditoria', p.id));
        movimientos.value = respuesta.data;
    } catch (error) {
        Swal.fire('Error', 'No se pudo cargar el historial', 'error');
    }
};

const formatearCantidad = (cantidad) => {
    if (!cantidad) return '0';
    return new Intl.NumberFormat('es-AR', { maximumFractionDigits: 3 }).format(parseFloat(cantidad));
};

const abrirNuevo = () => { seleccionado.value = null; verModal.value = true; };
const abrirEditar = (p) => { seleccionado.value = p; cerrarMenu(); verModal.value = true; };
const abrirDetalle = (p) => { seleccionado.value = p; cerrarMenu(); verDetalle.value = true; };
const abrirStock = (p) => { seleccionado.value = p; cerrarMenu(); verStock.value = true; };
const cerrarModalGlobal = () => { verModal.value = false; seleccionado.value = null; };

const toggleEstado = (p) => {
    cerrarMenu();
    const accion = p.estado ? 'desactivar' : 'activar';
    const resultado = p.estado ? 'desactivado' : 'activado';
    const colorConfirm = p.estado ? '#ef4444' : '#10b981';

    Swal.fire({
        title: `¿${accion.toUpperCase()} producto?`,
        text: `El producto "${p.nombre}" cambiará su estado a ${resultado}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: colorConfirm,
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sí, ${accion}`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.patch(route('productos.status', p.id), {}, {
                onSuccess: () => Swal.fire({ title: '¡Listo!', text: `Producto ${resultado}.`, icon: 'success', confirmButtonColor: '#0284c7' })
            });
        }
    });
};
</script>