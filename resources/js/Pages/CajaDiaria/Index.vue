<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios'; 
import Swal from 'sweetalert2';

const loading = ref(true);
const cajasDisponibles = ref([]);
const sesionActual = ref(null);
const balance = ref(null);
const movimientos = ref([]);
const pendientesInfo = ref(null);
const mostrarHistorial = ref(false);
const historialCajas = ref([]);
const mostrarModalCierre = ref(false);
const mostrarModalGasto = ref(false);
const mostrarModalDetalleHistorial = ref(false);
const cajaSeleccionada = ref(null);
const movimientosHistorial = ref([]);
const cargandoMovimientosHistorial = ref(false);

// NUEVO: Estado para almacenar los consumidores (clientes)
const consumidores = ref([]);

const formApertura = ref({ 
  caja: '', 
  tipoFondo: 'EFECTIVO', 
  saldo_inicial_efectivo: 0,
  saldo_inicial_mp: 0 
});

const formCierre = ref({ 
  saldo_final_efectivo_real: 0, 
  saldo_final_mp_real: 0, 
  saldo_final_transf_real: 0, 
  observaciones: '' 
});

// NUEVO: Agregado consumidor_id al formulario
const formGasto = ref({ 
  tipo: 'EGRESO', 
  concepto: 'GASTO_OPERATIVO', 
  metodo_pago: 'EFECTIVO', 
  monto: '', 
  descripcion: '',
  consumidor_id: '' 
});

watch(() => formGasto.value.tipo, (nuevoTipo) => {
  if (nuevoTipo === 'EGRESO') {
    formGasto.value.concepto = 'GASTO_OPERATIVO';
    formGasto.value.consumidor_id = ''; // Limpiamos
  } else {
    formGasto.value.concepto = 'APORTE_SOCIO';
    formGasto.value.consumidor_id = ''; // Limpiamos
  }
});

// Limpiar el cliente si cambia el concepto y no es cobro de cuenta corriente
watch(() => formGasto.value.concepto, (nuevoConcepto) => {
  if (nuevoConcepto !== 'COBRO_CUENTA_CORRIENTE') {
    formGasto.value.consumidor_id = '';
  }
});

let pollingInterval = null;
const itemsPerPage = 10;

const filterUser = ref('');
const filterDateFrom = ref('');
const filterDateTo = ref('');

const uniqueUsers = computed(() => {
  const users = historialCajas.value
    .map(c => c.usuario_cierre_nombre)
    .filter(u => u && u.trim() !== '');
  return [...new Set(users)];
});

const filteredCajas = computed(() => {
  return historialCajas.value.filter(caja => {
    if (filterUser.value && caja.usuario_cierre_nombre !== filterUser.value) return false;

    if (filterDateFrom.value) {
      const fechaCaja = new Date(caja.fecha_apertura);
      fechaCaja.setHours(0, 0, 0, 0);
      const desde = new Date(filterDateFrom.value);
      desde.setHours(0, 0, 0, 0);
      if (fechaCaja < desde) return false;
    }

    if (filterDateTo.value) {
      const fechaCaja = new Date(caja.fecha_apertura);
      fechaCaja.setHours(0, 0, 0, 0);
      const hasta = new Date(filterDateTo.value);
      hasta.setHours(0, 0, 0, 0);
      if (fechaCaja > hasta) return false;
    }

    return true;
  });
});

const currentPageCajas = ref(1);
const paginatedCajas = computed(() => {
  const start = (currentPageCajas.value - 1) * itemsPerPage;
  return filteredCajas.value.slice(start, start + itemsPerPage);
});
const totalPagesCajas = computed(() => Math.ceil(filteredCajas.value.length / itemsPerPage));

watch([filterUser, filterDateFrom, filterDateTo], () => {
  currentPageCajas.value = 1;
});

const limpiarFiltros = () => {
  filterUser.value = '';
  filterDateFrom.value = '';
  filterDateTo.value = '';
  currentPageCajas.value = 1;
};

const diferenciaCierre = computed(() => {
  if (!balance.value) return 0;
  
  const esperado = parseFloat(balance.value.esperado_efectivo || 0) + 
                   parseFloat(balance.value.esperado_mp || 0) + 
                   parseFloat(balance.value.esperado_transf || 0); 

  const real = parseFloat(formCierre.value.saldo_final_efectivo_real || 0) + 
               parseFloat(formCierre.value.saldo_final_mp_real || 0) +
               parseFloat(formCierre.value.saldo_final_transf_real || 0);
  
  return real - esperado; 
});

const hayDiferencia = computed(() => {
  return Math.abs(diferenciaCierre.value) > 0.01;
});

// Limpiar observación si de repente no hay diferencia
watch(hayDiferencia, (nuevaDiferencia) => {
  if (!nuevaDiferencia) {
    formCierre.value.observaciones = '';
  }
});

const tipoDiferencia = computed(() => {
  if (!balance.value) return '';
  const esperado = parseFloat(balance.value.esperado_efectivo || 0) + 
                   parseFloat(balance.value.esperado_mp || 0) + 
                   parseFloat(balance.value.esperado_transf || 0);

  if (diferenciaCierre.value < 0) {
      return 'FALTANTE';
  }
  
  if (diferenciaCierre.value > 0) {
      if (esperado < 0 && parseFloat(formCierre.value.saldo_final_efectivo_real || 0) === 0) {
          return 'REPOSICIÓN EXTERNA';
      }
      return 'SOBRANTE';
  }
  return '';
});

const calcularTotalRealDeclarado = (caja) => {
  return parseFloat(caja.saldo_final_efectivo_real || 0) + 
         parseFloat(caja.saldo_final_mp_real || 0) + 
         parseFloat(caja.saldo_final_transf_real || 0);
};

const calcularTotalCaja = (caja) => {
  if (caja.esta_abierta) return 0;
  return calcularTotalRealDeclarado(caja);
};

const formatearMoneda = (valor) => {
  const n = parseFloat(valor || 0);
  const signo = n < 0 ? '-' : '';
  const formateado = Math.abs(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return `$ ${signo}${formateado}`;
};

const currentPageMovs = ref(1);
const paginatedMovs = computed(() => {
  const start = (currentPageMovs.value - 1) * itemsPerPage;
  return movimientos.value.slice(start, start + itemsPerPage);
});
const totalPagesMovs = computed(() => Math.ceil(movimientos.value.length / itemsPerPage));

const currentPageModalMovs = ref(1);
const paginatedModalMovs = computed(() => {
  const start = (currentPageModalMovs.value - 1) * itemsPerPage;
  return movimientosHistorial.value.slice(start, start + itemsPerPage);
});
const totalPagesModalMovs = computed(() => Math.ceil(movimientosHistorial.value.length / itemsPerPage));

// Función para determinar el estilo del badge según el método de pago
const getMetodoPagoBadgeClass = (metodo) => {
  if (!metodo) return 'bg-gray-100 text-gray-700';
  const metodoUpper = metodo.toUpperCase();
  if (metodoUpper === 'EFECTIVO') return 'bg-emerald-100 text-emerald-700';
  if (['MERCADO_PAGO', 'MERCADOPAGO', 'MP', 'TRANSFERENCIA', 'TRANSFER', 'DÉBITO', 'DEBITO', 'CRÉDITO', 'CREDITO', 'TARJETA'].some(m => metodoUpper.includes(m))) 
    return 'bg-sky-100 text-sky-700';
  if (metodoUpper.includes('CUENTA CORRIENTE') || metodoUpper.includes('FIADO') || metodoUpper === 'CTA CTE')
    return 'bg-amber-100 text-amber-700';
  return 'bg-gray-100 text-gray-700';
};

const inicializar = async () => {
  loading.value = true;
  try {
    try {
      const resSesion = await axios.get('/api/sesiones-caja/actual');
      sesionActual.value = resSesion.data;
      await cargarDatosCajaAbierta(sesionActual.value.id);
      iniciarRadar();
    } catch (e) {
      if (e.response && e.response.status === 404) {
        sesionActual.value = null;
        await cargarDatosApertura();
      }
    }
    await cargarHistorial();
    await cargarConsumidores(); // Cargamos los clientes al inicializar
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

// NUEVO: Función para traer los consumidores desde el backend
const cargarConsumidores = async () => {
  try {
    // IMPORTANTE: Ajustá esta URL a tu endpoint real que devuelva los consumidores
    const res = await axios.get('/api/consumidores'); 
    consumidores.value = res.data;
  } catch (error) {
    console.error("Error al cargar consumidores:", error);
  }
};

const cargarDatosApertura = async () => {
  const [resCajas, resPendientes] = await Promise.all([
    axios.get('/api/sesiones-caja/cajas-disponibles'),
    axios.get('/api/sesiones-caja/pendientes')
  ]);
  cajasDisponibles.value = resCajas.data.filter(c => c.estado);
  if (cajasDisponibles.value.length > 0) {
    formApertura.value.caja = cajasDisponibles.value[0].id;
  }
  pendientesInfo.value = resPendientes.data;
};

const cargarDatosCajaAbierta = async (sesionId) => {
  const [resBalance, resMovs] = await Promise.all([
    axios.get(`/api/sesiones-caja/${sesionId}/balance`),
    axios.get(`/api/sesiones-caja/${sesionId}/movimientos`)
  ]);
  balance.value = resBalance.data;
  movimientos.value = resMovs.data;
  currentPageMovs.value = 1; 
  
  formCierre.value.saldo_final_efectivo_real = Math.max(0, parseFloat(balance.value.esperado_efectivo || 0));
  formCierre.value.saldo_final_mp_real = Math.max(0, parseFloat(balance.value.esperado_mp || 0)); 
  formCierre.value.saldo_final_transf_real = Math.max(0, parseFloat(balance.value.esperado_transf || 0)); 
  formCierre.value.observaciones = ''; 
};

const checkNuevosMovimientos = async () => {
  if (!sesionActual.value || mostrarHistorial.value) return;
  try {
    const resMovs = await axios.get(`/api/sesiones-caja/${sesionActual.value.id}/movimientos`);
    if (resMovs.data.length > movimientos.value.length) {
      movimientos.value = resMovs.data;
      const resBalance = await axios.get(`/api/sesiones-caja/${sesionActual.value.id}/balance`);
      balance.value = resBalance.data;
      Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `¡Nuevos pagos ingresados!`, showConfirmButton: false, timer: 3000 });
    }
  } catch (error) { console.error(error); }
};

const iniciarRadar = () => {
  if (pollingInterval) clearInterval(pollingInterval);
  pollingInterval = setInterval(checkNuevosMovimientos, 15000);
};

const detenerRadar = () => { if (pollingInterval) clearInterval(pollingInterval); };

const cargarHistorial = async () => {
  try {
    const res = await axios.get('/api/sesiones-caja/');
    historialCajas.value = res.data;
  } catch (e) { console.error(e); }
}

const abrirCaja = async () => {
  try {
    const tipo = formApertura.value.tipoFondo;
    const efectivo = ['EFECTIVO', 'AMBOS'].includes(tipo) ? Number(formApertura.value.saldo_inicial_efectivo) : 0;
    const mp = ['MERCADO_PAGO', 'AMBOS'].includes(tipo) ? Number(formApertura.value.saldo_inicial_mp) : 0;

    const payload = {
      caja: formApertura.value.caja,
      saldo_inicial_efectivo: efectivo,
      saldo_inicial_mp: mp,
    };

    await axios.post('/api/sesiones-caja/abrir', payload);
    
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: '¡Turno iniciado!', showConfirmButton: false, timer: 2000 });
    
    inicializar(); 
  } catch (error) { 
    console.error(error.response?.data || error);
    Swal.fire('Error', 'No se pudo abrir la caja. Verificá los datos.', 'error'); 
  }
};

const cerrarCaja = async () => {
  if (hayDiferencia.value && !formCierre.value.observaciones.trim()) {
      Swal.fire({ icon: 'warning', title: 'Justificación Requerida', text: `Debe justificar la diferencia de ${formatearMoneda(Math.abs(diferenciaCierre.value))} (${tipoDiferencia.value})` });
      return;
  }
  Swal.fire({
      title: '¿Cerrar caja?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, cerrar e imprimir',
      cancelButtonText: 'Cancelar'
  }).then(async (result) => {
      if (result.isConfirmed) {
          try {
            const sesionIdCerrada = sesionActual.value.id; 
            await axios.post(`/api/sesiones-caja/${sesionIdCerrada}/cerrar`, formCierre.value);
            detenerRadar();
            mostrarModalCierre.value = false;
            
            // await descargarPDFCaja(sesionIdCerrada); // Opcional

            inicializar();
            Swal.fire('Caja Cerrada', 'El turno se ha cerrado correctamente.', 'success');
          } catch (error) { 
            let errorMsg = 'Error al cerrar la caja';
            if (error.response && error.response.data && error.response.data.error) {
                errorMsg = error.response.data.error;
            }
            Swal.fire({ icon: 'error', title: 'Error', text: errorMsg }); 
          }
      }
  });
};

const registrarGastoManual = async () => {
  // Validación frontend: Si eligió pagar cuenta corriente, debe seleccionar un cliente
  if (formGasto.value.concepto === 'COBRO_CUENTA_CORRIENTE' && !formGasto.value.consumidor_id) {
    Swal.fire('Atención', 'Debe seleccionar un cliente para registrar el pago de cuenta corriente.', 'warning');
    return;
  }

  if (formGasto.value.tipo === 'EGRESO') {
      const montoEgreso = parseFloat(formGasto.value.monto);
      let saldoDisponible = 0;
      let metodoNombre = '';

      if (formGasto.value.metodo_pago === 'EFECTIVO') {
          saldoDisponible = parseFloat(balance.value.esperado_efectivo);
          metodoNombre = 'Efectivo';
      } else if (formGasto.value.metodo_pago === 'MERCADO_PAGO') {
          saldoDisponible = parseFloat(balance.value.esperado_mp);
          metodoNombre = 'Mercado Pago';
      } else if (formGasto.value.metodo_pago === 'TRANSFERENCIA') {
          saldoDisponible = parseFloat(balance.value.esperado_transf || 0); 
          metodoNombre = 'Transferencia';
      }

      if (montoEgreso > saldoDisponible) {
          Swal.fire({
              icon: 'error',
              title: 'Fondos Insuficientes',
              html: `Estás intentando registrar un egreso de <b>${formatearMoneda(montoEgreso)}</b>, pero el saldo actual en <b>${metodoNombre}</b> es de solo <b>${formatearMoneda(saldoDisponible)}</b>.<br><br>Si se agregó dinero adicional a la caja, registrá primero un <i>Ingreso Manual</i> por la diferencia.`,
              confirmButtonColor: '#ef4444',
              confirmButtonText: 'Entendido'
          });
          return; 
      }
  }

  try {
    await axios.post('/api/sesiones-caja/movimiento-manual', formGasto.value);
    mostrarModalGasto.value = false;
    
    // Resetear form
    formGasto.value = { 
      tipo: 'EGRESO', 
      concepto: 'GASTO_OPERATIVO', 
      metodo_pago: 'EFECTIVO', 
      monto: '', 
      descripcion: '',
      consumidor_id: '' 
    };
    
    cargarDatosCajaAbierta(sesionActual.value.id);
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Movimiento registrado`, showConfirmButton: false, timer: 3000 });
  } catch (error) { 
    Swal.fire('Error', 'Error al registrar el movimiento', 'error'); 
  }
};

const verDetalleCajaCerrada = async (caja) => {
  cajaSeleccionada.value = caja;
  mostrarModalDetalleHistorial.value = true;
  cargandoMovimientosHistorial.value = true;
  currentPageModalMovs.value = 1;
  try {
    const res = await axios.get(`/api/sesiones-caja/${caja.id}/movimientos`);
    movimientosHistorial.value = res.data;
  } catch (error) { console.error(error); } finally { cargandoMovimientosHistorial.value = false; }
};

const calcularTotalActual = (bal) => {
  if (!bal) return 0;
  return parseFloat(bal.esperado_efectivo || 0) + 
         parseFloat(bal.esperado_mp || 0) + 
         parseFloat(bal.esperado_transf || 0);
};

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-AR') : '';
const formatearFechaCorta = (f) => f ? new Date(f).toLocaleDateString('es-AR') : '';
const formatearHora = (f) => f ? new Date(f).toLocaleTimeString('es-AR', {hour:'2-digit', minute:'2-digit'}) : '';

onMounted(() => inicializar());
onUnmounted(() => detenerRadar());
</script>

<template>
  <Head title="Caja Diaria - VendAR" />

  <AuthenticatedLayout>
    <div class="list-container py-8 px-4 max-w-7xl mx-auto">
      <div class="list-card bg-white rounded-2xl shadow-lg p-6">
        
        <div class="list-header border-b pb-4 mb-6 flex justify-between items-center">
          <div class="header-content">
            <h1 class="text-2xl font-black text-slate-800"><i class="ri-bank-card-line"></i> Caja Diaria</h1>
            <p class="text-slate-500 text-sm mt-1">Control de ingresos, egresos y arqueo de caja.</p>
          </div>
          <div class="header-buttons">
            <button 
              class="bg-slate-800 hover:bg-slate-700 text-white font-bold py-2 px-4 rounded-xl transition-colors text-sm" 
              @click="mostrarHistorial = !mostrarHistorial"
            >
              <i class="ri-history-line"></i> {{ mostrarHistorial ? 'Volver a Caja Actual' : 'Ver Historial de Cajas' }}
            </button>
          </div>
        </div>

        <div v-if="loading" class="flex flex-col items-center justify-center py-12 text-slate-500">
          <i class="ri-loader-4-line animate-spin text-4xl mb-3"></i>
          <p class="font-medium">Cargando información de tu caja...</p>
        </div>

        <div v-else>
          
          <div v-if="mostrarHistorial">
            <h2 class="text-lg font-bold text-slate-700 mb-4">Historial de Sesiones Anteriores</h2>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 items-end">
              <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Usuario que cerró</label>
                <select v-model="filterUser" class="w-full border-gray-300 rounded-lg shadow-sm">
                  <option value="">Todos los usuarios</option>
                  <option v-for="user in uniqueUsers" :key="user" :value="user">{{ user }}</option>
                </select>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Fecha desde</label>
                <input type="date" v-model="filterDateFrom" class="w-full border-gray-300 rounded-lg shadow-sm">
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Fecha hasta</label>
                <input type="date" v-model="filterDateTo" class="w-full border-gray-300 rounded-lg shadow-sm">
              </div>

              <div>
                <button class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2 rounded-lg" @click="limpiarFiltros">
                  Limpiar Filtros
                </button>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Sesión</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Apertura</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cierre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Usuario</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Final</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Estado</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Acciones</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-if="filteredCajas.length === 0">
                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">No hay cajas que coincidan con los filtros.</td>
                  </tr>
                  <tr v-for="caja in paginatedCajas" :key="caja.id">
                    <td class="px-6 py-4 whitespace-nowrap font-bold text-gray-900">#{{ caja.id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatearFechaCorta(caja.fecha_apertura) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ caja.fecha_cierre ? formatearFechaCorta(caja.fecha_cierre) : '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ caja.usuario_cierre_nombre || '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-emerald-600">
                      {{ caja.esta_abierta ? '-' : formatearMoneda(calcularTotalRealDeclarado(caja)) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      <span :class="caja.esta_abierta ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'" class="px-2 py-1 text-xs font-bold rounded-full">
                        {{ caja.esta_abierta ? 'ABIERTA' : 'CERRADA' }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">
                      <button @click="verDetalleCajaCerrada(caja)" class="text-sky-600 hover:text-sky-800 font-bold text-xs">
                        <i class="ri-eye-line text-lg align-middle"></i> Ver Detalles
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="totalPagesCajas > 1" class="flex justify-between items-center mt-4">
              <button @click="currentPageCajas--" :disabled="currentPageCajas === 1" class="px-4 py-2 bg-gray-200 rounded-lg disabled:opacity-50">Anterior</button>
              <span class="text-sm text-gray-500 font-medium">Página {{ currentPageCajas }} de {{ totalPagesCajas }}</span>
              <button @click="currentPageCajas++" :disabled="currentPageCajas === totalPagesCajas" class="px-4 py-2 bg-gray-200 rounded-lg disabled:opacity-50">Siguiente</button>
            </div>
          </div>

          <div v-else>
            
            <div v-if="!sesionActual" class="flex flex-col items-center justify-center py-10 bg-slate-50 rounded-xl border border-dashed border-slate-300">
              <div class="text-6xl text-slate-300 mb-4"><i class="ri-lock-2-line"></i></div>
              <h2 class="text-2xl font-bold text-slate-700">Caja Cerrada</h2>
              <p class="text-slate-500 mb-6">Para registrar cobros o pagos, necesitás iniciar una sesión de caja.</p>

              <form @submit.prevent="abrirCaja" class="bg-white p-6 rounded-xl shadow-sm w-full max-w-md border border-slate-200">
                
                <div class="mb-4">
                  <label class="block text-sm font-bold text-slate-700 mb-1">Seleccionar Caja Física</label>
                  <select v-model="formApertura.caja" required class="w-full border-gray-300 rounded-lg">
                    <option value="" disabled>Seleccione una caja...</option>
                    <option v-for="c in cajasDisponibles" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                  </select>
                </div>

                <div class="mb-4">
                  <label class="block text-sm font-bold text-slate-700 mb-1">¿Con qué fondos abrís la caja?</label>
                  <select v-model="formApertura.tipoFondo" required class="w-full border-gray-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                    <option value="EFECTIVO">Solo Efectivo</option>
                    <option value="MERCADO_PAGO">Solo Mercado Pago</option>
                    <option value="AMBOS">Ambos (Efectivo y MP)</option>
                  </select>
                </div>

                <div v-if="['EFECTIVO', 'AMBOS'].includes(formApertura.tipoFondo)" class="mb-4">
                  <label class="block text-sm font-bold text-slate-700 mb-1">Fondo Inicial en Efectivo</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
                    <input type="number" v-model="formApertura.saldo_inicial_efectivo" step="0.01" min="0" required class="w-full pl-8 border-gray-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                  </div>
                </div>

                <div v-if="['MERCADO_PAGO', 'AMBOS'].includes(formApertura.tipoFondo)" class="mb-4">
                  <label class="block text-sm font-bold text-slate-700 mb-1">Fondo Inicial en Mercado Pago</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
                    <input type="number" v-model="formApertura.saldo_inicial_mp" step="0.01" min="0" required class="w-full pl-8 border-gray-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
                  </div>
                </div>

                <button type="submit" class="w-full bg-sky-600 hover:bg-sky-700 text-white font-bold py-3 rounded-lg mt-2 transition-colors flex justify-center items-center gap-2">
                  <i class="ri-play-circle-line"></i> INICIAR TURNO DE CAJA
                </button>
              </form>
            </div>

            <div v-else>
              <div class="bg-sky-50 border border-sky-200 p-4 rounded-xl flex justify-between items-center mb-6">
                <div>
                  <span class="bg-sky-600 text-white text-xs font-bold px-2 py-1 rounded">ABIERTA</span>
                  <span class="font-bold text-slate-800 ml-3">{{ sesionActual.caja_nombre }}</span>
                  <span class="text-sm text-slate-500 ml-2">Por {{ sesionActual.usuario_apertura_nombre }}</span>
                </div>
                <button @click="mostrarModalCierre = true" class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-2 px-5 rounded-lg text-sm shadow-sm transition-colors flex items-center gap-2">
                  <i class="ri-lock-unlock-line"></i> Cerrar Caja (Arqueo)
                </button>
              </div>

              <div v-if="balance" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
                  <p class="text-sm font-bold text-slate-500">Saldo (Efectivo)</p>
                  <h3 class="text-3xl font-black text-emerald-600 mt-1">{{ formatearMoneda(balance.esperado_efectivo) }}</h3>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-slate-200">
                  <p class="text-sm font-bold text-slate-500">Saldo (Mercado Pago)</p>
                  <h3 class="text-3xl font-black text-sky-600 mt-1">{{ formatearMoneda(balance.esperado_mp) }}</h3>
                </div>
                <div class="bg-slate-800 p-5 rounded-xl shadow-sm border border-slate-700">
                  <p class="text-sm font-bold text-slate-400">Total General</p>
                  <h3 class="text-3xl font-black text-white mt-1">{{ formatearMoneda(calcularTotalActual(balance)) }}</h3>
                </div>
              </div>

              <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                <div class="bg-slate-50 p-4 border-b flex justify-between items-center">
                  <h3 class="font-bold text-slate-700">Movimientos de la Sesión</h3>
                  <button @click="mostrarModalGasto = true" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-1.5 px-3 rounded-lg text-xs transition-colors">
                    <i class="ri-add-line align-middle"></i> Movimiento Manual
                  </button>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-white">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Hora</th>
                      <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Tipo</th>
                      <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Concepto</th>
                      <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Método</th>
                      <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Descripción</th>
                      <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Monto</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200">
                    <tr v-if="movimientos.length === 0">
                      <td colspan="6" class="px-6 py-8 text-center text-gray-500">No hay movimientos.</td>
                    </tr>
                    <tr v-for="mov in paginatedMovs" :key="mov.id" class="hover:bg-slate-50">
                      <td class="px-6 py-3 whitespace-nowrap text-sm text-slate-600">{{ formatearHora(mov.fecha) }}</td>
                      <td class="px-6 py-3 whitespace-nowrap">
                        <span :class="mov.tipo === 'INGRESO' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'" class="px-2 py-1 text-[10px] font-bold rounded-md">
                          {{ mov.tipo }}
                        </span>
                      </td>
                      <td class="px-6 py-3 whitespace-nowrap text-sm font-bold text-slate-700">{{ mov.concepto }}</td>
                      <td class="px-6 py-3 whitespace-nowrap">
                        <span :class="getMetodoPagoBadgeClass(mov.metodo_pago)" class="px-2 py-1 text-[10px] font-bold rounded-full uppercase tracking-wide">
                          {{ mov.metodo_pago || 'N/D' }}
                        </span>
                      </td>
                      <td class="px-6 py-3 text-sm text-slate-500 truncate max-w-[200px]">{{ mov.descripcion }}</td>
                      <td class="px-6 py-3 whitespace-nowrap text-sm font-black text-right" :class="mov.tipo === 'INGRESO' ? 'text-emerald-600' : 'text-rose-600'">
                        {{ mov.tipo === 'EGRESO' ? '-' : '+' }}{{ formatearMoneda(mov.monto) }}
                      </td>
                    </tr>
                  </tbody>
                </table>

                <div v-if="totalPagesMovs > 1" class="flex justify-between items-center p-4 border-t bg-gray-50">
                  <button @click="currentPageMovs--" :disabled="currentPageMovs === 1" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg disabled:opacity-50 transition-colors">Anterior</button>
                  <span class="text-sm font-medium text-slate-500">Página {{ currentPageMovs }} de {{ totalPagesMovs }}</span>
                  <button @click="currentPageMovs++" :disabled="currentPageMovs === totalPagesMovs" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg disabled:opacity-50 transition-colors">Siguiente</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Cierre de Caja -->
    <div v-if="mostrarModalCierre" class="fixed inset-0 bg-slate-900 bg-opacity-70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full flex flex-col max-h-[90vh] overflow-hidden animate-fade-in-up">
        
        <div class="flex justify-between items-center border-b px-6 py-4 bg-slate-50">
          <h3 class="text-2xl font-black text-slate-800"><i class="ri-scales-3-line text-sky-600 mr-2"></i> Arqueo y Cierre de Caja</h3>
          <button @click="mostrarModalCierre = false" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="ri-close-line text-3xl"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div class="bg-slate-50 rounded-xl p-5 border border-slate-200">
              <h4 class="text-sm uppercase tracking-wider font-bold text-slate-500 mb-4"><i class="ri-computer-line"></i> Según el sistema</h4>
              
              <div class="space-y-3">
                <div class="bg-white border-l-4 border-emerald-500 p-3 rounded shadow-sm flex justify-between items-center">
                  <span class="font-bold text-slate-700">Efectivo</span>
                  <span class="text-xl font-black text-emerald-700">{{ formatearMoneda(balance?.esperado_efectivo || 0) }}</span>
                </div>
                <div class="bg-white border-l-4 border-sky-500 p-3 rounded shadow-sm flex justify-between items-center">
                  <span class="font-bold text-slate-700">Mercado Pago</span>
                  <span class="text-xl font-black text-sky-700">{{ formatearMoneda(balance?.esperado_mp || 0) }}</span>
                </div>
                <div class="bg-white border-l-4 border-indigo-500 p-3 rounded shadow-sm flex justify-between items-center">
                  <span class="font-bold text-slate-700">Transferencia</span>
                  <span class="text-xl font-black text-indigo-700">{{ formatearMoneda(balance?.esperado_transf || 0) }}</span>
                </div>
              </div>

              <div class="mt-6 p-4 bg-slate-800 rounded-lg text-white flex justify-between items-center">
                <span class="font-bold">Total Esperado</span>
                <span class="text-2xl font-black">{{ formatearMoneda(calcularTotalActual(balance)) }}</span>
              </div>
            </div>

            <div>
              <h4 class="text-sm uppercase tracking-wider font-bold text-slate-500 mb-4"><i class="ri-money-dollar-circle-line"></i> Dinero real en caja</h4>
              
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-bold text-slate-700 mb-1">Efectivo Real</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
                    <input type="number" v-model="formCierre.saldo_final_efectivo_real" step="0.01" class="w-full pl-8 border-gray-300 rounded-lg font-bold text-lg focus:ring-sky-500 focus:border-sky-500">
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-bold text-slate-700 mb-1">Mercado Pago Real</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
                    <input type="number" v-model="formCierre.saldo_final_mp_real" step="0.01" class="w-full pl-8 border-gray-300 rounded-lg font-bold text-lg focus:ring-sky-500 focus:border-sky-500">
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-bold text-slate-700 mb-1">Transferencias Real</label>
                  <div class="relative">
                    <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
                    <input type="number" v-model="formCierre.saldo_final_transf_real" step="0.01" class="w-full pl-8 border-gray-300 rounded-lg font-bold text-lg focus:ring-sky-500 focus:border-sky-500">
                  </div>
                </div>
              </div>

              <div v-if="hayDiferencia" class="mt-6 p-4 rounded-lg border flex items-start gap-3" 
                   :class="diferenciaCierre < 0 ? 'bg-rose-50 border-rose-200' : 'bg-amber-50 border-amber-200'">
                <i class="text-2xl mt-1" :class="diferenciaCierre < 0 ? 'ri-error-warning-fill text-rose-500' : 'ri-alert-fill text-amber-500'"></i>
                <div>
                  <p class="font-black" :class="diferenciaCierre < 0 ? 'text-rose-800' : 'text-amber-800'">
                    Diferencia de: {{ formatearMoneda(diferenciaCierre) }} ({{ tipoDiferencia }})
                  </p>
                  <p class="text-sm mt-1" :class="diferenciaCierre < 0 ? 'text-rose-700' : 'text-amber-700'">
                    La justificación es <span class="font-bold underline">obligatoria</span> para poder cerrar el turno.
                  </p>
                </div>
              </div>

              <div class="mt-4">
                <label class="block text-sm font-bold mb-1 transition-colors" :class="hayDiferencia ? 'text-slate-700' : 'text-slate-400'">
                  Observaciones / Justificación
                  <span v-if="hayDiferencia" class="text-rose-500 ml-1">*</span>
                  <span v-else class="text-xs font-normal ml-2">(Solo habilitado si hay diferencias)</span>
                </label>
                <textarea 
                  v-model="formCierre.observaciones" 
                  :disabled="!hayDiferencia"
                  rows="3" 
                  class="w-full rounded-lg focus:ring-sky-500 focus:border-sky-500 transition-colors disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed"
                  :class="hayDiferencia ? 'border-gray-300' : 'border-gray-200'"
                  placeholder="Anota aquí los motivos de las diferencias, retiros de dinero, etc..."
                ></textarea>
              </div>
            </div>
          </div>
        </div>

        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3 rounded-b-2xl">
          <button @click="mostrarModalCierre = false" class="px-5 py-2.5 bg-white border border-gray-300 text-slate-700 font-bold rounded-xl hover:bg-gray-50 transition-colors">
            Cancelar
          </button>
          <button @click="cerrarCaja" class="px-6 py-2.5 bg-rose-600 text-white font-black rounded-xl hover:bg-rose-700 transition-colors shadow-md flex items-center gap-2">
            <i class="ri-check-double-line"></i> Confirmar Cierre
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Movimiento Manual -->
    <div v-if="mostrarModalGasto" class="fixed inset-0 bg-slate-900 bg-opacity-70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
        <div class="flex justify-between items-center border-b px-6 py-4 bg-slate-50">
          <h3 class="text-xl font-bold text-slate-800">Movimiento Manual</h3>
          <button @click="mostrarModalGasto = false" class="text-slate-400 hover:text-slate-600 transition-colors"><i class="ri-close-line text-2xl"></i></button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Tipo de Movimiento</label>
            <select v-model="formGasto.tipo" class="w-full border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
              <option value="EGRESO">Egreso (Retiro / Gasto)</option>
              <option value="INGRESO">Ingreso (Aporte externo)</option>
            </select>
          </div>
          
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Concepto</label>
            <select v-model="formGasto.concepto" class="w-full border-gray-300 rounded-lg">
              <option v-if="formGasto.tipo === 'EGRESO'" value="GASTO_OPERATIVO">Gasto Operativo / Compra Insumos</option>
              <option v-if="formGasto.tipo === 'EGRESO'" value="RETIRO_SOCIO">Retiro de Dueño / Socio</option>
              
              <option v-if="formGasto.tipo === 'INGRESO'" value="APORTE_SOCIO">Aporte de Dueño / Cambio</option>
              <option v-if="formGasto.tipo === 'INGRESO'" value="COBRO_CUENTA_CORRIENTE">Cobro de Cuenta Corriente (Fiado)</option>
            </select>
          </div>

          <div v-if="formGasto.concepto === 'COBRO_CUENTA_CORRIENTE'">
            <label class="block text-sm font-bold text-slate-700 mb-1">Cliente (Consumidor)</label>
            <select v-model="formGasto.consumidor_id" required class="w-full border-gray-300 rounded-lg focus:ring-sky-500 focus:border-sky-500">
              <option value="" disabled>Seleccione el cliente que paga...</option>
              <option v-for="cliente in consumidores" :key="cliente.id" :value="cliente.id">
                {{ cliente.nombre }} {{ cliente.apellido }} (DNI: {{ cliente.documento }})
              </option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Método afectado</label>
            <select v-model="formGasto.metodo_pago" class="w-full border-gray-300 rounded-lg">
              <option value="EFECTIVO">Efectivo</option>
              <option value="MERCADO_PAGO">Mercado Pago</option>
              <option value="TRANSFERENCIA">Transferencia</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Monto a registrar</label>
            <div class="relative">
              <span class="absolute left-3 top-2.5 font-bold text-slate-400">$</span>
              <input type="number" v-model="formGasto.monto" step="0.01" required class="w-full pl-8 border-gray-300 rounded-lg font-bold">
            </div>
          </div>
          <div>
            <label class="block text-sm font-bold text-slate-700 mb-1">Descripción / Detalle</label>
            <input type="text" v-model="formGasto.descripcion" class="w-full border-gray-300 rounded-lg" placeholder="Ej: Pago a proveedor, compra descartables...">
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3 rounded-b-2xl">
          <button @click="mostrarModalGasto = false" class="px-4 py-2 bg-white border border-gray-300 rounded-lg font-bold text-slate-600 hover:bg-gray-50">Cancelar</button>
          <button @click="registrarGastoManual" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-lg hover:bg-emerald-700 shadow-sm">Guardar Movimiento</button>
        </div>
      </div>
    </div>

    <!-- Modal Detalle Historial -->
    <div v-if="mostrarModalDetalleHistorial" class="fixed inset-0 bg-slate-900 bg-opacity-70 backdrop-blur-sm flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full flex flex-col h-[85vh] overflow-hidden">
        
        <div class="flex justify-between items-center border-b px-6 py-4 bg-slate-800 text-white shrink-0">
          <h3 class="text-xl font-bold flex items-center gap-2">
            <i class="ri-file-list-3-line"></i> Detalle de Caja #{{ cajaSeleccionada?.id }}
          </h3>
          <button @click="mostrarModalDetalleHistorial = false" class="text-slate-300 hover:text-white transition-colors">
            <i class="ri-close-line text-3xl"></i>
          </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-6 bg-slate-50 border-b shrink-0">
          <div class="bg-white p-3 rounded-lg border shadow-sm">
            <span class="block text-xs font-bold text-slate-400 uppercase">Apertura</span>
            <span class="font-bold text-slate-700 block">{{ formatearFecha(cajaSeleccionada?.fecha_apertura) }}</span>
            <span class="text-sm text-slate-500">{{ cajaSeleccionada?.usuario_apertura_nombre }}</span>
          </div>
          <div class="bg-white p-3 rounded-lg border shadow-sm">
            <span class="block text-xs font-bold text-slate-400 uppercase">Cierre</span>
            <span class="font-bold text-slate-700 block">{{ formatearFecha(cajaSeleccionada?.fecha_cierre) || 'Pendiente' }}</span>
            <span class="text-sm text-slate-500">{{ cajaSeleccionada?.usuario_cierre_nombre || '-' }}</span>
          </div>
          <div class="bg-emerald-50 p-3 rounded-lg border border-emerald-200 shadow-sm flex flex-col justify-center">
            <span class="block text-xs font-bold text-emerald-600 uppercase">Total Declarado Final</span>
            <span class="text-2xl font-black text-emerald-700">{{ formatearMoneda(calcularTotalRealDeclarado(cajaSeleccionada)) }}</span>
          </div>
        </div>

        <div v-if="cajaSeleccionada?.observaciones" class="px-6 py-4 bg-amber-50 border-b border-amber-200 shrink-0">
          <h4 class="text-xs font-bold text-amber-800 uppercase mb-2 flex items-center gap-1">
            <i class="ri-error-warning-line text-base"></i> Justificación de Cierre / Observaciones
          </h4>
          <p class="text-sm text-amber-900 bg-amber-100/50 p-3 rounded border border-amber-200 italic">
            "{{ cajaSeleccionada.observaciones }}"
          </p>
        </div>
        
        <div class="flex-1 overflow-auto p-6 bg-white">
          <h4 class="font-black text-slate-700 mb-4 border-b pb-2"><i class="ri-list-check"></i> Historial de Movimientos</h4>
          
          <div v-if="cargandoMovimientosHistorial" class="flex justify-center items-center py-12 text-slate-400">
            <i class="ri-loader-4-line animate-spin text-4xl mr-3"></i> Cargando registros...
          </div>
          
          <table v-else class="min-w-full divide-y divide-gray-200 border rounded-lg overflow-hidden">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Hora</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Tipo</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Concepto</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase">Método</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-gray-500 uppercase">Monto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="mov in paginatedModalMovs" :key="mov.id" class="hover:bg-slate-50">
                <td class="px-4 py-3 text-sm text-slate-600">{{ formatearHora(mov.fecha) }}</td>
                <td class="px-4 py-3">
                  <span :class="mov.tipo === 'INGRESO' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'" class="px-2 py-1 text-[10px] font-bold rounded">
                    {{ mov.tipo }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm font-medium text-slate-700">{{ mov.concepto }}</td>
                <td class="px-4 py-3">
                  <span :class="getMetodoPagoBadgeClass(mov.metodo_pago)" class="px-2 py-1 text-[10px] font-bold rounded-full uppercase tracking-wide">
                    {{ mov.metodo_pago || 'N/D' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-right font-bold" :class="mov.tipo === 'INGRESO' ? 'text-emerald-600' : 'text-rose-600'">
                  {{ mov.tipo === 'EGRESO' ? '-' : '+' }}{{ formatearMoneda(mov.monto) }}
                </td>
              </tr>
              <tr v-if="movimientosHistorial.length === 0">
                <td colspan="5" class="text-center py-10 text-slate-500 font-medium border-t border-dashed">Sin movimientos registrados en esta sesión.</td>
              </tr>
            </tbody>
          </table>

          <div v-if="totalPagesModalMovs > 1" class="flex justify-between items-center mt-4 bg-slate-50 p-2 rounded border">
            <button @click="currentPageModalMovs--" :disabled="currentPageModalMovs === 1" class="px-4 py-1.5 bg-white border rounded shadow-sm disabled:opacity-50 hover:bg-gray-50 font-medium text-sm">Anterior</button>
            <span class="text-sm font-bold text-slate-500">Pág {{ currentPageModalMovs }} / {{ totalPagesModalMovs }}</span>
            <button @click="currentPageModalMovs++" :disabled="currentPageModalMovs === totalPagesModalMovs" class="px-4 py-1.5 bg-white border rounded shadow-sm disabled:opacity-50 hover:bg-gray-50 font-medium text-sm">Siguiente</button>
          </div>
        </div>

        <div class="px-6 py-4 bg-slate-100 border-t shrink-0 flex justify-end">
          <button @click="mostrarModalDetalleHistorial = false" class="px-8 py-3 bg-slate-800 text-white font-black rounded-xl hover:bg-slate-900 transition-colors shadow-lg flex items-center gap-2">
            <i class="ri-arrow-go-back-line text-xl"></i> Volver al Historial
          </button>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>