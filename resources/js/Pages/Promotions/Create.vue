<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductoSelector from './Componentes/ProductoSelector.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';

const props = defineProps({
    promotion: Object,
    promotionRules: Array,
    promotionProducts: Array,
    categories: Array,
    brands: Array,
});

const esEdicion = !!props.promotion;
const paso = ref(1);
const totalPasos = 5;

const errors = ref({});

const clearErrors = () => { errors.value = {}; };
const setError = (field, msg) => { errors.value[field] = msg; };

const formulario = useForm({
    name: props.promotion?.name ?? '',
    description: props.promotion?.description ?? '',
    type: props.promotion?.type ?? 'MANUAL',
    discount_type: props.promotion?.discount_type ?? 'percent',
    value: props.promotion?.value ?? null,
    discount_config: props.promotion?.discount_config ?? { x: 3, y: 2 },
    starts_at: props.promotion?.starts_at?.substring(0, 16) ?? '',
    ends_at: props.promotion?.ends_at?.substring(0, 16) ?? '',
    active: props.promotion?.active ?? true,
    priority: props.promotion?.priority ?? 0,
    exclusive: props.promotion?.exclusive ?? false,
    cumulative: props.promotion?.cumulative ?? false,
    product_ids: props.promotionProducts?.map(p => p.id) ?? [],
    rules: props.promotionRules?.map(r => ({
        condition_type: r.condition_type,
        operator: r.operator,
        value: r.value,
        action_type: r.action_type,
        action_value: r.action_value,
    })) ?? [],
});

const previewData = ref(null);
const previewLoading = ref(false);

const discountTypes = {
    percent: 'Porcentaje (%)',
    fixed_amount: 'Monto Fijo ($)',
    fixed_price: 'Precio Fijo ($)',
    '2x1': '2x1',
    bundle: 'Combo',
    x_for_y: 'X por Y',
};

const conditionTypes = {
    product: 'Producto específico',
    category: 'Categoría',
    brand: 'Marca',
    stock: 'Stock disponible',
    expiry_date: 'Próximo a vencer',
    margin: 'Margen de ganancia',
    product_margin: 'Precio de venta',
};

const operators = {
    '=': 'Igual a',
    '!=': 'Distinto de',
    '>': 'Mayor que',
    '<': 'Menor que',
    '>=': 'Mayor o igual',
    '<=': 'Menor o igual',
    'in': 'En lista',
};

function validarPaso() {
    clearErrors();
    switch (paso.value) {
        case 1: {
            let ok = true;
            if (!formulario.name?.trim()) { setError('name', 'El nombre es obligatorio'); ok = false; }
            if (!formulario.starts_at) { setError('starts_at', 'La fecha de inicio es obligatoria'); ok = false; }
            if (!formulario.ends_at) { setError('ends_at', 'La fecha de fin es obligatoria'); ok = false; }
            if (formulario.starts_at && formulario.ends_at && formulario.ends_at <= formulario.starts_at) {
                setError('ends_at', 'La fecha de fin debe ser posterior a la de inicio');
                ok = false;
            }
            return ok;
        }
        case 2: {
            const dt = formulario.discount_type;
            const val = formulario.value;
            if (dt === 'percent') {
                if (val === null || val === '' || val === undefined) { setError('value', 'El porcentaje es obligatorio'); return false; }
                const n = Number(val);
                if (n <= 0) { setError('value', 'Debe ser mayor que 0'); return false; }
                if (n > 100) { setError('value', 'Debe ser 100 o menos'); return false; }
            } else if (dt === 'fixed_amount') {
                if (val === null || val === '' || val === undefined) { setError('value', 'El monto es obligatorio'); return false; }
                if (Number(val) <= 0) { setError('value', 'Debe ser mayor que 0'); return false; }
            } else if (dt === 'fixed_price') {
                if (val === null || val === '' || val === undefined) { setError('value', 'El precio final es obligatorio'); return false; }
                if (Number(val) < 0) { setError('value', 'Debe ser 0 o mayor'); return false; }
            } else if (dt === 'bundle') {
                if (!formulario.discount_config?.items || formulario.discount_config.items < 2) {
                    setError('discount_config.items', 'Debe incluir al menos 2 items');
                    return false;
                }
                if (!formulario.discount_config?.price || Number(formulario.discount_config.price) <= 0) {
                    setError('discount_config.price', 'El precio del combo es obligatorio');
                    return false;
                }
            } else if (dt === 'x_for_y') {
                if (!formulario.discount_config?.x || Number(formulario.discount_config.x) <= 0) {
                    setError('discount_config.x', 'Lleva (X) debe ser mayor que 0');
                    return false;
                }
                if (!formulario.discount_config?.y || Number(formulario.discount_config.y) <= 0) {
                    setError('discount_config.y', 'Paga (Y) debe ser mayor que 0');
                    return false;
                }
                if (Number(formulario.discount_config.y) >= Number(formulario.discount_config.x)) {
                    setError('discount_config.y', 'Paga (Y) debe ser menor que Lleva (X)');
                    return false;
                }
            }
            return true;
        }
        case 3: {
            if (formulario.type === 'MANUAL') {
                if (formulario.product_ids.length === 0) {
                    setError('product_ids', 'Debe seleccionar al menos un producto');
                    return false;
                }
            } else {
                if (formulario.rules.length === 0) {
                    setError('rules', 'Debe agregar al menos una regla');
                    return false;
                }
                for (let i = 0; i < formulario.rules.length; i++) {
                    const r = formulario.rules[i];
                    if (!r.value?.toString().trim()) {
                        setError(`rules.${i}.value`, 'El valor de la regla es obligatorio');
                        return false;
                    }
                }
            }
            return true;
        }
        default:
            return true;
    }
}

const canContinue = computed(() => {
    switch (paso.value) {
        case 1: return !!(formulario.name?.trim() && formulario.starts_at && formulario.ends_at);
        case 2: {
            const dt = formulario.discount_type;
            const val = formulario.value;
            if (dt === 'percent') { const n = Number(val); return val !== null && val !== '' && n > 0 && n <= 100; }
            if (dt === 'fixed_amount') { return val !== null && val !== '' && Number(val) > 0; }
            if (dt === 'fixed_price') { return val !== null && val !== '' && Number(val) >= 0; }
            if (dt === '2x1') return true;
            if (dt === 'bundle') {
                return formulario.discount_config?.items >= 2 && Number(formulario.discount_config?.price) > 0;
            }
            if (dt === 'x_for_y') {
                const x = Number(formulario.discount_config?.x);
                const y = Number(formulario.discount_config?.y);
                return x > 0 && y > 0 && y < x;
            }
            return false;
        }
        case 3: {
            if (formulario.type === 'MANUAL') return formulario.product_ids.length > 0;
            return formulario.rules.length > 0;
        }
        case 4: return previewData.value !== null;
        default: return true;
    }
});

const avanzar = () => {
    if (!validarPaso()) return;
    if (paso.value < totalPasos) {
        if (paso.value === 3 && formulario.type === 'AUTO' && formulario.rules.length === 0) {
            agregarRegla();
        }
        paso.value++;
        if (paso.value === 4) {
            cargarPreview();
        }
    }
};

const retroceder = () => {
    if (paso.value > 1) {
        clearErrors();
        paso.value--;
    }
};

const agregarRegla = () => {
    formulario.rules.push({
        condition_type: 'category',
        operator: '=',
        value: '',
        action_type: 'discount_percent',
        action_value: formulario.value ?? 0,
    });
};

const eliminarRegla = (index) => {
    formulario.rules.splice(index, 1);
};

const cargarPreview = async () => {
    if (formulario.type === 'MANUAL' && formulario.product_ids.length === 0) {
        previewData.value = null;
        return;
    }

    previewLoading.value = true;
    previewData.value = null;

    try {
        const payload = {
            product_ids: formulario.product_ids,
            discount_type: formulario.discount_type,
            value: formulario.value,
            discount_config: formulario.discount_type === 'x_for_y' || formulario.discount_type === 'bundle'
                ? formulario.discount_config : null,
        };
        const response = await axios.post(route('promotions.preview'), payload);
        previewData.value = response.data;
    } catch (e) {
        previewData.value = { total_products: 0, product_previews: [], original_price: 0, final_price: 0, discount_amount: 0, warnings: ['Error al calcular vista previa'] };
    } finally {
        previewLoading.value = false;
    }
};

const guardar = () => {
    const ruta = esEdicion ? route('promotions.update', props.promotion.id) : route('promotions.store');
    const metodo = esEdicion ? 'put' : 'post';

    if (['x_for_y', '2x1', 'bundle'].includes(formulario.discount_type)) {
        formulario.value = null;
    }
    if (!['x_for_y', 'bundle'].includes(formulario.discount_type)) {
        formulario.discount_config = null;
    }

    formulario[metodo](ruta, {
        onSuccess: () => {
            Swal.fire({
                title: '¡Éxito!',
                text: `Promoción ${esEdicion ? 'actualizada' : 'creada'} correctamente.`,
                icon: 'success',
                confirmButtonColor: '#0284c7',
            });
        },
    });
};

const confirmCancel = () => {
    Swal.fire({
        title: '¿Descartar cambios?',
        text: 'Los datos ingresados se perderán.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sí, descartar',
        cancelButtonText: 'Seguir editando',
    }).then((r) => {
        if (r.isConfirmed) router.get(route('promotions.index'));
    });
};

const formatPrice = (val) => {
    if (val === null || val === undefined) return '-';
    return '$' + Number(val).toLocaleString('es-AR', { minimumFractionDigits: 2 });
};

const productosSeleccionados = computed(() => {
    return formulario.product_ids.length;
});

const fieldClass = (field) => {
    return errors.value[field]
        ? 'border-rose-500 bg-rose-50 focus:ring-rose-500'
        : 'border-slate-200 bg-slate-50 focus:ring-sky-500 focus:border-sky-500';
};
</script>

<template>
    <Head :title="esEdicion ? 'Editar Promoción' : 'Nueva Promoción'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ esEdicion ? 'Editar Promoción' : 'Nueva Promoción' }}
                </h2>
                <button @click="confirmCancel" class="text-sm text-rose-500 hover:text-rose-700 font-bold">
                    Cancelar
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

                <!-- Progress -->
                <div class="mb-8">
                    <div class="flex items-center justify-between mb-2">
                        <span v-for="i in totalPasos" :key="i"
                            class="flex-1 text-center text-xs font-black uppercase tracking-widest"
                            :class="paso >= i ? 'text-sky-600' : 'text-slate-300'">
                            Paso {{ i }}
                        </span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-sky-600 rounded-full transition-all duration-500"
                            :style="{ width: ((paso / totalPasos) * 100) + '%' }"></div>
                    </div>
                </div>

                <!-- Step 1: General Info -->
                <div v-show="paso === 1" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h3 class="text-lg font-black text-slate-800 mb-6">Información General</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Nombre *</label>
                            <input v-model="formulario.name" type="text"
                                class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                :class="fieldClass('name')" placeholder="Ej: 2x1 en Bebidas">
                            <p v-if="errors.name" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Descripción</label>
                            <textarea v-model="formulario.description" rows="2"
                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 focus:ring-sky-500 focus:border-sky-500 transition-all text-sm text-slate-700 resize-none"
                                placeholder="Descripción opcional de la promoción"></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Tipo</label>
                                <select v-model="formulario.type"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 transition-all font-bold text-slate-700">
                                    <option value="MANUAL">Manual (productos específicos)</option>
                                    <option value="AUTO">Automática (por reglas)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Prioridad</label>
                                <input v-model="formulario.priority" type="number" min="0"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 transition-all font-bold text-slate-700"
                                    placeholder="0 (mayor = más importante)">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Inicio *</label>
                                <input v-model="formulario.starts_at" type="datetime-local"
                                    class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                    :class="fieldClass('starts_at')">
                                <p v-if="errors.starts_at" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors.starts_at }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Fin *</label>
                                <input v-model="formulario.ends_at" type="datetime-local"
                                    class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                    :class="fieldClass('ends_at')">
                                <p v-if="errors.ends_at" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors.ends_at }}</p>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4 space-y-3">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="formulario.active" type="checkbox" class="rounded text-sky-600 focus:ring-sky-500">
                                <span class="text-xs font-bold text-slate-600">Activa</span>
                            </label>

                            <label class="flex items-start gap-2 cursor-pointer">
                                <input v-model="formulario.exclusive" type="checkbox" class="rounded text-sky-600 focus:ring-sky-500 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold text-slate-600">Exclusiva</span>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">No puede combinarse con otras promociones. Si esta promoción está activa, las demás no aplican.</p>
                                </div>
                            </label>

                            <label class="flex items-start gap-2 cursor-pointer">
                                <input v-model="formulario.cumulative" type="checkbox" class="rounded text-sky-600 focus:ring-sky-500 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold text-slate-600">Acumulable</span>
                                    <p class="text-[10px] text-slate-400 leading-tight mt-0.5">Puede combinarse con otras promociones acumulables. Los descuentos se suman.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Discount Type -->
                <div v-show="paso === 2" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h3 class="text-lg font-black text-slate-800 mb-6">Tipo de Descuento</h3>

                    <div class="grid grid-cols-3 gap-3 mb-6">
                        <button v-for="(label, key) in discountTypes" :key="key"
                            @click="formulario.discount_type = key; formulario.value = null; formulario.discount_config = key === 'x_for_y' ? { x: 3, y: 2 } : key === 'bundle' ? { items: 2, price: '' } : null;"
                            class="p-4 rounded-xl border-2 text-left transition-all"
                            :class="formulario.discount_type === key
                                ? 'border-sky-500 bg-sky-50 text-sky-700'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'">
                            <div class="font-bold text-sm">{{ label }}</div>
                        </button>
                    </div>

                    <!-- Percent -->
                    <div v-if="formulario.discount_type === 'percent'" class="max-w-xs">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Porcentaje de descuento *</label>
                        <div class="relative">
                            <input v-model="formulario.value" type="number" step="0.01" min="0" max="100"
                                class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700 pr-8"
                                :class="fieldClass('value')" placeholder="Ej: 25">
                            <span class="absolute right-4 top-2.5 text-slate-400 font-bold text-sm">%</span>
                        </div>
                        <p v-if="errors.value" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors.value }}</p>
                    </div>

                    <!-- Fixed Amount -->
                    <div v-if="formulario.discount_type === 'fixed_amount'" class="max-w-xs">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Monto de descuento *</label>
                        <div class="relative">
                            <input v-model="formulario.value" type="number" step="0.01" min="0"
                                class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700 pr-8"
                                :class="fieldClass('value')" placeholder="Ej: 500">
                            <span class="absolute right-4 top-2.5 text-slate-400 font-bold text-sm">$</span>
                        </div>
                        <p v-if="errors.value" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors.value }}</p>
                    </div>

                    <!-- Fixed Price -->
                    <div v-if="formulario.discount_type === 'fixed_price'" class="max-w-xs">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio final *</label>
                        <div class="relative">
                            <input v-model="formulario.value" type="number" step="0.01" min="0"
                                class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700 pr-8"
                                :class="fieldClass('value')" placeholder="Ej: 1500">
                            <span class="absolute right-4 top-2.5 text-slate-400 font-bold text-sm">$</span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">El producto se venderá a este precio final durante la promoción.</p>
                        <p v-if="errors.value" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors.value }}</p>
                    </div>

                    <!-- 2x1 -->
                    <div v-if="formulario.discount_type === '2x1'" class="max-w-md">
                        <div class="p-4 bg-sky-50 rounded-xl border border-sky-200">
                            <p class="text-sm font-bold text-sky-700">Promoción 2x1</p>
                            <p class="text-xs text-sky-600 mt-1">El cliente paga 1 y lleva 2 productos (se aplica el descuento del precio más bajo).</p>
                        </div>
                    </div>

                    <!-- Bundle / Combo -->
                    <div v-if="formulario.discount_type === 'bundle'" class="max-w-md space-y-4">
                        <div class="p-4 bg-amber-50 rounded-xl border border-amber-200">
                            <p class="text-sm font-bold text-amber-700">Combo</p>
                            <p class="text-xs text-amber-600 mt-1">Configurá un combo con precio especial.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Cantidad de productos en el combo *</label>
                            <input v-model="formulario.discount_config.items" type="number" min="2"
                                class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                :class="fieldClass('discount_config.items')" placeholder="Ej: 3">
                            <p v-if="errors['discount_config.items']" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors['discount_config.items'] }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio del combo *</label>
                            <div class="relative">
                                <input v-model="formulario.discount_config.price" type="number" step="0.01" min="0"
                                    class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700 pr-8"
                                    :class="fieldClass('discount_config.price')" placeholder="Ej: 2000">
                                <span class="absolute right-4 top-2.5 text-slate-400 font-bold text-sm">$</span>
                            </div>
                            <p v-if="errors['discount_config.price']" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors['discount_config.price'] }}</p>
                        </div>
                    </div>

                    <!-- X for Y -->
                    <div v-if="formulario.discount_type === 'x_for_y'" class="max-w-md space-y-4">
                        <div class="p-4 bg-purple-50 rounded-xl border border-purple-200">
                            <p class="text-sm font-bold text-purple-700">X por Y</p>
                            <p class="text-xs text-purple-600 mt-1">El cliente lleva X productos y paga solo Y.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Lleva (X) *</label>
                                <input v-model="formulario.discount_config.x" type="number" min="1"
                                    class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                    :class="fieldClass('discount_config.x')" placeholder="Ej: 3">
                                <p v-if="errors['discount_config.x']" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors['discount_config.x'] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Paga (Y) *</label>
                                <input v-model="formulario.discount_config.y" type="number" min="1"
                                    class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                    :class="fieldClass('discount_config.y')" placeholder="Ej: 2">
                                <p v-if="errors['discount_config.y']" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors['discount_config.y'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Target -->
                <div v-show="paso === 3" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h3 class="text-lg font-black text-slate-800 mb-6">
                        {{ formulario.type === 'MANUAL' ? 'Productos incluidos' : 'Reglas automáticas' }}
                    </h3>

                    <!-- Manual: Product Selector -->
                    <div v-if="formulario.type === 'MANUAL'">
                        <ProductoSelector
                            v-model="formulario.product_ids"
                            :categories="categories"
                            :brands="brands"
                            :initial-products="promotionProducts"
                        />
                        <p v-if="errors.product_ids" class="text-rose-500 text-[10px] mt-2 font-bold">{{ errors.product_ids }}</p>
                    </div>

                    <!-- Auto: Rules Builder -->
                    <div v-else>
                        <div v-if="formulario.rules.length === 0" class="text-center py-8 text-slate-400">
                            <p class="mb-4">No hay reglas definidas. Agregá al menos una.</p>
                        </div>

                        <div v-for="(rule, index) in formulario.rules" :key="index"
                            class="bg-slate-50 rounded-xl p-4 mb-3 border border-slate-200">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-black text-slate-500 uppercase tracking-widest">Regla #{{ index + 1 }}</span>
                                <button @click="eliminarRegla(index)" class="text-rose-500 hover:text-rose-700 text-xs font-bold">
                                    Eliminar
                                </button>
                            </div>

                            <div class="grid grid-cols-4 gap-3">
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase mb-1 block">Condición</label>
                                    <select v-model="rule.condition_type" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold text-slate-700">
                                        <option v-for="(label, key) in conditionTypes" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase mb-1 block">Operador</label>
                                    <select v-model="rule.operator" class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold text-slate-700">
                                        <option v-for="(label, key) in operators" :key="key" :value="key">{{ label }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase mb-1 block">Valor</label>
                                    <input v-model="rule.value" type="text"
                                        class="w-full bg-white border rounded-lg px-3 py-2 text-sm font-bold text-slate-700"
                                        :class="errors[`rules.${index}.value`] ? 'border-rose-500' : 'border-slate-200'"
                                        placeholder="Ej: 15 (ID de categoría)">
                                    <p v-if="errors[`rules.${index}.value`]" class="text-rose-500 text-[10px] mt-0.5 font-bold">{{ errors[`rules.${index}.value`] }}</p>
                                </div>
                                <div>
                                    <label class="text-[10px] font-black text-slate-500 uppercase mb-1 block">Dto. %</label>
                                    <input v-model="rule.action_value" type="number" step="0.01" min="0"
                                        class="w-full bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold text-slate-700"
                                        placeholder="10">
                                </div>
                            </div>
                        </div>

                        <button @click="agregarRegla"
                            class="text-sky-600 hover:text-sky-800 text-sm font-bold flex items-center gap-2 mt-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Agregar regla
                        </button>
                        <p v-if="errors.rules" class="text-rose-500 text-[10px] mt-2 font-bold">{{ errors.rules }}</p>
                    </div>
                </div>

                <!-- Step 4: Preview -->
                <div v-show="paso === 4" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h3 class="text-lg font-black text-slate-800 mb-6">Vista Previa</h3>

                    <div v-if="previewLoading" class="text-center py-8 text-slate-400">
                        <svg class="animate-spin h-6 w-6 text-sky-500 mx-auto mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Calculando...
                    </div>

                    <div v-else-if="previewData && previewData.total_products > 0">
                        <div class="grid grid-cols-3 gap-4 mb-6">
                            <div class="bg-sky-50 rounded-xl p-4 text-center">
                                <div class="text-2xl font-black text-sky-700">{{ previewData.total_products }}</div>
                                <div class="text-xs font-bold text-sky-500">Productos</div>
                            </div>
                            <div class="bg-emerald-50 rounded-xl p-4 text-center">
                                <div class="text-2xl font-black text-emerald-700">{{ formatPrice(previewData.original_price) }}</div>
                                <div class="text-xs font-bold text-emerald-500">Precio Original</div>
                            </div>
                            <div class="bg-amber-50 rounded-xl p-4 text-center">
                                <div class="text-2xl font-black text-amber-700">{{ formatPrice(previewData.final_price) }}</div>
                                <div class="text-xs font-bold text-amber-500">Precio Final</div>
                            </div>
                        </div>

                        <div v-if="previewData.discount_amount > 0" class="text-center mb-4">
                            <span class="inline-block px-4 py-1.5 bg-green-50 border border-green-200 rounded-full text-xs font-bold text-green-700">
                                Descuento total: {{ formatPrice(previewData.discount_amount) }}
                                <span v-if="previewData.discount_label" class="ml-1">({{ previewData.discount_label }})</span>
                            </span>
                        </div>

                        <div v-if="previewData.product_previews?.length" class="mt-4">
                            <h4 class="text-sm font-bold text-slate-600 mb-3">Productos afectados</h4>
                            <div class="max-h-64 overflow-y-auto space-y-2">
                                <div v-for="pp in previewData.product_previews" :key="pp.product_id"
                                    class="flex items-center justify-between bg-slate-50 rounded-lg px-4 py-2 text-sm">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-700 truncate">{{ pp.name }}</div>
                                        <div class="text-[10px] text-slate-400">{{ pp.barcode }}</div>
                                    </div>
                                    <span v-if="pp.error" class="text-rose-500 text-xs font-bold flex-shrink-0 ml-2">{{ pp.error }}</span>
                                    <span v-else class="font-mono text-slate-600 flex-shrink-0 ml-2">
                                        {{ formatPrice(pp.original_price) }} → <span class="text-emerald-600 font-bold">{{ formatPrice(pp.final_price) }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="previewData.warnings?.length" class="mt-4 p-3 bg-amber-50 rounded-xl border border-amber-200">
                            <p v-for="w in previewData.warnings" :key="w" class="text-amber-700 text-xs font-bold">⚠ {{ w }}</p>
                        </div>
                    </div>

                    <div v-else class="text-center py-8">
                        <p class="text-slate-400 mb-3">
                            {{ previewData?.warnings?.length ? previewData.warnings[0] : 'No hay datos para mostrar.' }}
                        </p>
                        <button @click="cargarPreview" class="text-sky-600 font-bold text-sm hover:text-sky-800">
                            Recalcular
                        </button>
                    </div>
                </div>

                <!-- Step 5: Confirm -->
                <div v-show="paso === 5" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h3 class="text-lg font-black text-slate-800 mb-6">Confirmar</h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-500">Nombre</span>
                            <span class="font-bold text-slate-700">{{ formulario.name }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-500">Tipo</span>
                            <span class="font-bold text-slate-700">{{ formulario.type === 'MANUAL' ? 'Manual' : 'Automática' }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-500">Descuento</span>
                            <span class="font-bold text-slate-700">{{ discountTypes[formulario.discount_type] }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-500">Vigencia</span>
                            <span class="font-bold text-slate-700">{{ formulario.starts_at }} → {{ formulario.ends_at }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-500">Productos / Reglas</span>
                            <span class="font-bold text-slate-700">
                                {{ formulario.type === 'MANUAL' ? `${productosSeleccionados} producto(s)` : `${formulario.rules.length} regla(s)` }}
                            </span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-slate-500">Exclusiva / Acumulable</span>
                            <span class="font-bold text-slate-700">
                                {{ formulario.exclusive ? 'Exclusiva' : 'No exclusiva' }} /
                                {{ formulario.cumulative ? 'Acumulable' : 'No acumulable' }}
                            </span>
                        </div>
                    </div>

                    <div v-if="formulario.errors.product_ids" class="mt-4 p-3 bg-rose-50 rounded-xl">
                        <p class="text-rose-700 text-xs font-bold">{{ formulario.errors.product_ids }}</p>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex justify-between mt-8">
                    <button v-if="paso > 1" @click="retroceder"
                        class="px-6 py-3 rounded-xl font-bold text-slate-500 hover:text-slate-700 border border-slate-200 hover:border-slate-300 transition-all text-sm">
                        ← Anterior
                    </button>
                    <div v-else></div>

                    <button v-if="paso < totalPasos" @click="avanzar"
                        :disabled="!canContinue"
                        class="px-8 py-3 rounded-xl font-bold text-white transition-all text-sm"
                        :class="canContinue ? 'bg-sky-600 hover:bg-sky-700 shadow-sm' : 'bg-slate-300 cursor-not-allowed'">
                        Siguiente →
                    </button>

                    <button v-else @click="guardar"
                        :disabled="formulario.processing"
                        class="px-8 py-3 rounded-xl font-bold text-white transition-all text-sm bg-emerald-600 hover:bg-emerald-700 shadow-sm"
                        :class="{'opacity-50 cursor-not-allowed': formulario.processing}">
                        {{ formulario.processing ? 'Guardando...' : 'Confirmar y Guardar' }}
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
