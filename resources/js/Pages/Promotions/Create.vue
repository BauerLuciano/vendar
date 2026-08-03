<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ProductoSelector from './Componentes/ProductoSelector.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, reactive, computed, watch } from 'vue';
import Swal from 'sweetalert2';
import axios from 'axios';
import AlertaAyuda from '@/Components/AlertaAyuda.vue';

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
    rules: props.promotionRules?.map(r => {
        let value = r.value;
        let _value2 = '';
        if (r.operator === 'entre' && value && value.includes(',')) {
            const parts = value.split(',');
            value = parts[0];
            _value2 = parts[1] || '';
        }
        return {
            condition_type: r.condition_type,
            operator: r.operator,
            value,
            _selectedName: '',
            _productQuery: '',
            _value2,
        };
    }) ?? [],
});

const previewData = ref(null);
const previewLoading = ref(false);
const searchResults = reactive({});
const searching = reactive({});

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

const conditionConfig = {
    product:        { label: 'Producto',        valueLabel: 'Producto',     hasOperator: false },
    category:       { label: 'Categoría',       valueLabel: 'Categoría',    hasOperator: false },
    brand:          { label: 'Marca',           valueLabel: 'Marca',        hasOperator: false },
    stock:          { label: 'Stock',           valueLabel: 'Cantidad',     hasOperator: true },
    expiry_date:    { label: 'Vencimiento',     valueLabel: 'Días',         hasOperator: true },
    margin:         { label: 'Margen',          valueLabel: 'Porcentaje',   hasOperator: true },
    product_margin: { label: 'Precio de venta', valueLabel: 'Precio',       hasOperator: true },
};

const condOps = {
    product: [
        { key: '=', label: 'es' },
        { key: '!=', label: 'no es' },
    ],
    category: [
        { key: '=', label: 'es' },
        { key: '!=', label: 'no es' },
    ],
    brand: [
        { key: '=', label: 'es' },
        { key: '!=', label: 'no es' },
    ],
    stock: [
        { key: '<', label: 'menor que' },
        { key: '<=', label: 'menor o igual que' },
        { key: '>', label: 'mayor que' },
        { key: '>=', label: 'mayor o igual que' },
        { key: '=', label: 'igual a' },
        { key: 'entre', label: 'entre' },
    ],
    expiry_date: [
        { key: '<', label: 'vence dentro de' },
        { key: '>', label: 'vence después de' },
        { key: '=', label: 'vence exactamente en' },
        { key: '<=', label: 'vence a lo sumo en' },
        { key: '>=', label: 'vence en al menos' },
        { key: 'entre', label: 'vence entre' },
    ],
    margin: [
        { key: '>', label: 'mayor que' },
        { key: '>=', label: 'mayor o igual que' },
        { key: '<', label: 'menor que' },
        { key: '<=', label: 'menor o igual que' },
        { key: '=', label: 'igual a' },
        { key: 'entre', label: 'está entre' },
    ],
    product_margin: [
        { key: '>', label: 'mayor que' },
        { key: '>=', label: 'mayor o igual que' },
        { key: '<', label: 'menor que' },
        { key: '<=', label: 'menor o igual que' },
        { key: '=', label: 'igual a' },
        { key: 'entre', label: 'está entre' },
    ],
};

const getOpsFor = (type) => condOps[type] || [];
const getLabelFor = (type) => conditionConfig[type]?.label || type;
const getOperatorLabel = (type, opKey) => (condOps[type] || []).find(o => o.key === opKey)?.label || opKey;
const hasVisibleOperator = (type) => conditionConfig[type]?.hasOperator ?? true;
const getValueLabelFor = (type) => conditionConfig[type]?.valueLabel || 'Valor';
const getPlaceholderFor = (type) => {
    const m = { product: 'Buscar producto...', category: 'Seleccionar...', brand: 'Seleccionar...', stock: 'Ej: 10', expiry_date: 'Ej: 7', margin: 'Ej: 30', product_margin: 'Ej: 1500' };
    return m[type] || '';
};
const getSuffixFor = (type) => {
    const m = { stock: 'u.', expiry_date: 'días', margin: '%', product_margin: '$' };
    return m[type] || null;
};
const isEntityType = (type) => ['product', 'category', 'brand'].includes(type);
const isRangeOperator = (op) => op === 'entre';
const getInputmodeFor = (type) => ['margin', 'product_margin'].includes(type) ? 'decimal' : 'numeric';

const fechaMinima = computed(() => {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const h = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    return `${y}-${m}-${d}T${h}:${min}`;
});

const fechaFinMinima = computed(() => formulario.starts_at || fechaMinima.value);

watch(() => formulario.starts_at, (newStart) => {
    if (formulario.ends_at && newStart && formulario.ends_at <= newStart) {
        setError('ends_at', 'La fecha de finalización debe ser posterior a la fecha de inicio');
    }
});

const getStepFor = (type) => {
    const m = { stock: 1, expiry_date: 1, margin: '0.01', product_margin: '0.01' };
    return m[type] || '0.01';
};
const getMinFor = (type) => {
    const m = { stock: 1, expiry_date: 1, margin: 0, product_margin: 0 };
    return m[type] ?? 0;
};
const getHelpTextFor = (type) => {
    const m = {
        expiry_date: 'Ingrese la cantidad de días hasta el vencimiento.',
        margin: 'El margen se calcula sobre el costo.',
        product_margin: 'Comparará el precio de venta actual.',
    };
    return m[type] || null;
};

const getDiscountMessageText = () => {
    const dt = formulario.discount_type;
    const val = formulario.value;
    if (dt === 'percent') return `Se aplicará un ${val}% de descuento.`;
    if (dt === 'fixed_amount') return `Se descontarán $${Number(val).toLocaleString('es-AR')}.`;
    if (dt === 'fixed_price') return `El producto tendrá un precio final de $${Number(val).toLocaleString('es-AR')}.`;
    if (dt === '2x1') return 'Al cumplir las condiciones se aplicará una promoción 2x1.';
    if (dt === 'bundle') return 'Al cumplir las condiciones se aplicará un descuento de Combo.';
    if (dt === 'x_for_y') return 'Al cumplir las condiciones se aplicará una promoción X por Y.';
    return '';
};

const getDiscountDescription = () => {
    const dt = formulario.discount_type;
    const val = formulario.value;
    const cfg = formulario.discount_config;
    switch (dt) {
        case 'percent': return `${val}% de descuento`;
        case 'fixed_amount': return `Descuento fijo de $${Number(val).toLocaleString('es-AR')}`;
        case 'fixed_price': return `Precio fijo de $${Number(val).toLocaleString('es-AR')}`;
        case '2x1': return 'Lleva 2 y paga 1';
        case 'x_for_y': return `Lleva ${cfg?.x} y paga ${cfg?.y}`;
        case 'bundle': {
            const items = cfg?.items || '?';
            const price = cfg?.price ? `$${Number(cfg.price).toLocaleString('es-AR')}` : '?';
            return `Combo de ${items} productos por ${price}`;
        }
        default: return discountTypes[dt] || dt;
    }
};

const ruleConditionText = (rule) => {
    const val = rule.value;
    if (!val && val !== 0) return '';
    const type = rule.condition_type;
    const suffix = getSuffixFor(type);
    const opLabel = getOperatorLabel(type, rule.operator);

    if (type === 'product') {
        return rule._selectedName || `Producto #${val}`;
    }
    if (type === 'category') {
        return props.categories?.find(c => String(c.id) === String(val))?.nombreCategoria || `Categoría #${val}`;
    }
    if (type === 'brand') {
        return props.brands?.find(b => String(b.id) === String(val))?.nombre || `Marca #${val}`;
    }
    if (isRangeOperator(rule.operator)) {
        return `${getLabelFor(type)} entre ${val} y ${rule._value2}${suffix ? ' ' + suffix : ''}`;
    }
    return `${getLabelFor(type)} ${opLabel} ${val}${suffix ? ' ' + suffix : ''}`;
};

const resetRuleValue = (rule) => {
    rule.value = '';
    rule._value2 = '';
    rule._selectedName = '';
    rule._productQuery = '';
    rule.operator = condOps[rule.condition_type]?.[0]?.key || '=';
};

const searchProductsForRule = async (query, ruleIndex) => {
    if (!query || query.length < 2) { searchResults[ruleIndex] = []; searching[ruleIndex] = false; return; }
    searching[ruleIndex] = true;
    try {
        const { data } = await axios.get(route('promotions.search-products'), { params: { search: query, per_page: 8 } });
        searchResults[ruleIndex] = (data.data || []).filter(p => !formulario.product_ids.includes(p.id));
    } catch { searchResults[ruleIndex] = []; }
    searching[ruleIndex] = false;
};

let searchTimeouts = {};
const onProductSearch = (query, ruleIndex) => {
    clearTimeout(searchTimeouts[ruleIndex]);
    searchTimeouts[ruleIndex] = setTimeout(() => searchProductsForRule(query, ruleIndex), 350);
};

const selectProductForRule = (rule, product, ruleIndex) => {
    rule.value = String(product.id);
    const marca = product.marca?.nombreMarca || product.marca?.nombre || '';
    const cat = product.categoria?.nombreCategoria || '';
    rule._selectedName = product.nombre + (marca ? ` (${marca})` : '') + (cat ? ` - ${cat}` : '');
    searchResults[ruleIndex] = [];
    rule._productQuery = '';
};

const getConditionExplanation = (rule) => {
    const type = rule.condition_type;
    const op = rule.operator;
    const val = rule.value;
    if (!val && val !== 0) return '';

    if (isRangeOperator(op)) {
        const val2 = Number(rule._value2);
        if (!val2) return '';
        const suffix = { stock: 'unidades', expiry_date: 'días', margin: '%', product_margin: '' }[type] || '';
        return `Esta promoción aplica para ${getLabelFor(type).toLowerCase()} entre ${val} y ${val2}${suffix ? ' ' + suffix : ''}.`;
    }

    switch (type) {
        case 'product': {
            const name = rule._selectedName || `ID ${val}`;
            return op === '='
                ? `Esta promoción aplica solo al producto "${name}".`
                : `Esta promoción aplica a todos los productos excepto "${name}".`;
        }
        case 'category': {
            const catName = props.categories?.find(c => String(c.id) === String(val))?.nombreCategoria || `ID ${val}`;
            return op === '='
                ? `Esta promoción aplica a todos los productos de la categoría "${catName}".`
                : `Esta promoción aplica a todos los productos excepto los de la categoría "${catName}".`;
        }
        case 'brand': {
            const brandName = props.brands?.find(b => String(b.id) === String(val))?.nombre || `ID ${val}`;
            return op === '='
                ? `Esta promoción aplica a todos los productos de la marca "${brandName}".`
                : `Esta promoción aplica a todos los productos excepto los de la marca "${brandName}".`;
        }
        case 'stock': {
            const naturalOp = { '>': 'mayor a', '>=': 'mayor o igual a', '<': 'menor a', '<=': 'menor o igual a', '=': 'igual a' }[op] || op;
            return `Esta promoción aplica a productos con stock ${naturalOp} ${val} unidades.`;
        }
        case 'expiry_date': {
            const expiryOp = { '<': 'vencen dentro de', '>': 'vencen después de', '=': 'vencen exactamente en', '<=': 'vencen a lo sumo en', '>=': 'vencen en al menos' }[op] || op;
            return `Esta promoción aplica a productos cuyos lotes ${expiryOp} ${val} días.`;
        }
        case 'margin': {
            const naturalOp = { '>': 'mayor a', '>=': 'mayor o igual a', '<': 'menor a', '<=': 'menor o igual a', '=': 'igual a' }[op] || op;
            return `Esta promoción aplica a productos con margen ${naturalOp} ${val}%.`;
        }
        case 'product_margin': {
            const naturalOp = { '>': 'mayor a', '>=': 'mayor o igual a', '<': 'menor a', '<=': 'menor o igual a', '=': 'igual a' }[op] || op;
            return `Esta promoción aplica a productos con precio de venta ${naturalOp} $${Number(val).toLocaleString('es-AR')}.`;
        }
        default:
            return '';
    }
};

function validarPaso() {
    clearErrors();
    switch (paso.value) {
        case 1: {
            let ok = true;
            if (!formulario.name?.trim()) { setError('name', 'El nombre es obligatorio'); ok = false; }
            if (!formulario.starts_at) {
                setError('starts_at', 'La fecha de inicio es obligatoria'); ok = false;
            } else if (formulario.starts_at < fechaMinima.value) {
                setError('starts_at', 'La fecha de inicio no puede ser anterior al día de hoy'); ok = false;
            }
            if (!formulario.ends_at) {
                setError('ends_at', 'La fecha de finalización es obligatoria'); ok = false;
            } else if (formulario.starts_at && formulario.ends_at <= formulario.starts_at) {
                setError('ends_at', 'La fecha de finalización debe ser posterior a la fecha de inicio'); ok = false;
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
                    setError('rules', 'Debe agregar al menos una condición');
                    return false;
                }
                for (let i = 0; i < formulario.rules.length; i++) {
                    const r = formulario.rules[i];
                    if (!r.value?.toString().trim()) {
                        setError(`rules.${i}.value`, `Completá el campo ${getValueLabelFor(r.condition_type).toLowerCase()}`);
                        return false;
                    }
                    if (isEntityType(r.condition_type)) {
                        if (isNaN(Number(r.value))) {
                            setError(`rules.${i}.value`, 'Seleccioná un elemento válido');
                            return false;
                        }
                        if (r.condition_type === 'product' && !r._selectedName) {
                            setError(`rules.${i}.value`, 'Buscá y seleccioná un producto');
                            return false;
                        }
                    }
                    if (isRangeOperator(r.operator)) {
                        if (!r._value2?.toString().trim()) {
                            setError(`rules.${i}.value`, 'Completá el segundo valor del rango');
                            return false;
                        }
                        if (Number(r.value) >= Number(r._value2)) {
                            setError(`rules.${i}.value`, 'El primer valor debe ser menor al segundo');
                            return false;
                        }
                    }
                    if (r.condition_type === 'expiry_date' && !isRangeOperator(r.operator) && Number(r.value) <= 0) {
                        setError(`rules.${i}.value`, 'La cantidad de días debe ser mayor a 0');
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
            return formulario.rules.length > 0 && formulario.rules.every(r => r.value?.toString().trim());
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
        operator: condOps.category?.[0]?.key || '=',
        value: '',
        _selectedName: '',
        _productQuery: '',
        _value2: '',
    });
};

const eliminarRegla = (index) => {
    formulario.rules.splice(index, 1);
    delete searchResults[index];
    delete searching[index];
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

    formulario.rules = formulario.rules.map(r => {
        const clean = { ...r };
        if (isRangeOperator(clean.operator) && clean._value2) {
            clean.value = `${clean.value},${clean._value2}`;
        }
        clean.action_type = 'discount_percent';
        clean.action_value = formulario.value ?? 0;
        delete clean._selectedName;
        delete clean._productQuery;
        delete clean._value2;
        return clean;
    });

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
                            <input v-model="formulario.name" type="text" maxlength="255"
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
                                    <option value="AUTO">Automática (por condiciones)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Prioridad</label>
                                <input v-model="formulario.priority" type="number" min="0" step="1" inputmode="numeric"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 focus:ring-sky-500 focus:border-sky-500 transition-all font-bold text-slate-700"
                                    placeholder="0 (mayor = más importante)">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Inicio *</label>
                                <input v-model="formulario.starts_at" type="datetime-local"
                                    :min="fechaMinima"
                                    class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                    :class="fieldClass('starts_at')">
                                <p v-if="errors.starts_at" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors.starts_at }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Fin *</label>
                                <input v-model="formulario.ends_at" type="datetime-local"
                                    :min="fechaFinMinima"
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
                                    <p class="text-xs font-medium text-slate-600 leading-tight mt-0.5">No puede combinarse con otras promociones. Si esta promoción está activa, las demás no aplican.</p>
                                </div>
                            </label>

                            <label class="flex items-start gap-2 cursor-pointer">
                                <input v-model="formulario.cumulative" type="checkbox" class="rounded text-sky-600 focus:ring-sky-500 mt-0.5">
                                <div>
                                    <span class="text-xs font-bold text-slate-600">Acumulable</span>
                                    <p class="text-xs font-medium text-slate-600 leading-tight mt-0.5">Puede combinarse con otras promociones acumulables. Los descuentos se suman.</p>
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
                            <input v-model="formulario.value" type="number" step="0.01" min="0" max="100" inputmode="decimal"
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
                            <input v-model="formulario.value" type="number" step="0.01" min="0" inputmode="decimal"
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
                            <input v-model="formulario.value" type="number" step="0.01" min="0" inputmode="decimal"
                                class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700 pr-8"
                                :class="fieldClass('value')" placeholder="Ej: 1500">
                            <span class="absolute right-4 top-2.5 text-slate-400 font-bold text-sm">$</span>
                        </div>
                        <p class="text-xs font-medium text-slate-600 mt-1">El producto se venderá a este precio final durante la promoción.</p>
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
                            <input v-model="formulario.discount_config.items" type="number" min="2" step="1" inputmode="numeric"
                                class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                :class="fieldClass('discount_config.items')" placeholder="Ej: 3">
                            <p v-if="errors['discount_config.items']" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors['discount_config.items'] }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Precio del combo *</label>
                            <div class="relative">
                                <input v-model="formulario.discount_config.price" type="number" step="0.01" min="0" inputmode="decimal"
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
                                <input v-model="formulario.discount_config.x" type="number" min="1" step="1" inputmode="numeric"
                                    class="w-full border rounded-xl px-4 py-2.5 transition-all font-bold text-slate-700"
                                    :class="fieldClass('discount_config.x')" placeholder="Ej: 3">
                                <p v-if="errors['discount_config.x']" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors['discount_config.x'] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Paga (Y) *</label>
                                <input v-model="formulario.discount_config.y" type="number" min="1" step="1" inputmode="numeric"
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
                        {{ formulario.type === 'MANUAL' ? 'Productos incluidos' : 'Condiciones de la promoción' }}
                    </h3>

                    <!-- Discount summary card (AUTO only) -->
                    <div v-if="formulario.type === 'AUTO'" class="mb-6">
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-black text-emerald-500 uppercase tracking-widest mb-1">Resultado de esta promoción</p>
                                    <p class="text-sm font-bold text-emerald-800">{{ getDiscountMessageText() }}</p>
                                    <p class="text-[10px] text-emerald-500 mt-1">Configurado en el Paso 2.</p>
                                </div>
                                <button @click="paso = 2" class="text-[10px] font-bold text-emerald-600 hover:text-emerald-800 bg-emerald-100 hover:bg-emerald-200 rounded-lg px-3 py-1.5 transition-colors flex-shrink-0 ml-4">
                                    Modificar descuento
                                </button>
                            </div>
                        </div>
                        <AlertaAyuda class="mt-3">Las siguientes condiciones determinan cuándo esta promoción estará activa.</AlertaAyuda>
                    </div>

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

                        <!-- Explanation -->
                        <AlertaAyuda v-if="formulario.rules.length > 0" class="mb-5">Esta promoción se activará únicamente cuando se cumplan <strong class="text-amber-900">TODAS</strong> las condiciones.</AlertaAyuda>

                        <!-- Empty state -->
                        <AlertaAyuda v-if="formulario.rules.length === 0" titulo="Agregá la primera condición">Definí cuándo se aplicará el descuento.</AlertaAyuda>

                        <!-- Rules list -->
                        <div v-for="(rule, index) in formulario.rules" :key="index"
                            class="bg-white rounded-2xl border border-slate-200 mb-4 p-5 space-y-4">

                            <!-- Header: condition type selector + delete -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <select v-model="rule.condition_type" @change="resetRuleValue(rule)"
                                        class="bg-transparent border-0 text-sm font-black text-slate-700 focus:ring-0 cursor-pointer p-0 pr-5 appearance-none"
                                        style="background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2212%22%20height%3D%2212%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394a3b8%22%20stroke-width%3D%222%22%3E%3Cpolyline%20points%3D%226%209%2012%2015%2018%209%22%3E%3C%2Fpolyline%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0 center;">
                                        <option v-for="(cfg, key) in conditionConfig" :key="key" :value="key">{{ cfg.label }}</option>
                                    </select>
                                </div>
                                <button @click="eliminarRegla(index)" class="text-slate-300 hover:text-rose-500 transition-colors p-1 rounded-lg hover:bg-rose-50">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <!-- Operator (only for numeric conditions) -->
                            <div v-if="hasVisibleOperator(rule.condition_type)">
                                <select v-model="rule.operator"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all">
                                    <option v-for="op in getOpsFor(rule.condition_type)" :key="op.key" :value="op.key">{{ op.label }}</option>
                                </select>
                            </div>

                            <!-- Value input (contextual per condition type) -->

                            <!-- Product autocomplete -->
                            <div v-if="rule.condition_type === 'product'" class="relative">
                                <input :value="rule._selectedName || rule._productQuery || ''"
                                    @input="(e) => { rule._productQuery = e.target.value; rule.value = ''; rule._selectedName = ''; onProductSearch(e.target.value, index); }"
                                    type="text" placeholder="Buscar producto por nombre o código..."
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                    :class="errors[`rules.${index}.value`] ? 'border-rose-500' : ''" />
                                <div v-if="searching[index]" class="absolute right-3 top-3">
                                    <svg class="animate-spin h-5 w-5 text-sky-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                </div>
                                <div v-if="searchResults[index]?.length" class="absolute z-20 w-full mt-1 bg-white border border-slate-200 rounded-xl shadow-lg max-h-48 overflow-y-auto">
                                    <button v-for="p in searchResults[index]" :key="p.id"
                                        @click="selectProductForRule(rule, p, index)"
                                        class="w-full text-left px-4 py-3 hover:bg-sky-50 transition-colors border-b border-slate-50 last:border-0">
                                        <div class="text-sm font-bold text-slate-700">{{ p.nombre }}</div>
                                        <div class="flex items-center gap-2 text-[10px] text-slate-400 mt-0.5">
                                            <span class="font-mono">{{ p.codigo_barras }}</span>
                                            <span v-if="p.marca?.nombreMarca" class="text-slate-500">· {{ p.marca.nombreMarca }}</span>
                                            <span v-if="p.categoria?.nombreCategoria" class="text-sky-500">· {{ p.categoria.nombreCategoria }}</span>
                                        </div>
                                    </button>
                                </div>
                                <p v-if="errors[`rules.${index}.value`]" class="text-rose-500 text-[10px] mt-1.5 font-bold">{{ errors[`rules.${index}.value`] }}</p>
                            </div>

                            <!-- Category select -->
                            <div v-else-if="rule.condition_type === 'category'">
                                <select v-model="rule.value"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                    :class="errors[`rules.${index}.value`] ? 'border-rose-500' : ''">
                                    <option value="">Seleccionar categoría...</option>
                                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.nombreCategoria }}</option>
                                </select>
                                <p v-if="errors[`rules.${index}.value`]" class="text-rose-500 text-[10px] mt-1.5 font-bold">{{ errors[`rules.${index}.value`] }}</p>
                            </div>

                            <!-- Brand select -->
                            <div v-else-if="rule.condition_type === 'brand'">
                                <select v-model="rule.value"
                                    class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                    :class="errors[`rules.${index}.value`] ? 'border-rose-500' : ''">
                                    <option value="">Seleccionar marca...</option>
                                    <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.nombre }}</option>
                                </select>
                                <p v-if="errors[`rules.${index}.value`]" class="text-rose-500 text-[10px] mt-1.5 font-bold">{{ errors[`rules.${index}.value`] }}</p>
                            </div>

                            <!-- Numeric input (stock, days, margin, price) -->
                            <div v-else class="space-y-1">
                                <template v-if="isRangeOperator(rule.operator)">
                                    <div class="flex items-center gap-3">
                                        <div class="relative flex-1">
                                            <input v-model="rule.value" type="number"
                                                :step="getStepFor(rule.condition_type)" :min="getMinFor(rule.condition_type)"
                                                :inputmode="getInputmodeFor(rule.condition_type)"
                                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                                :class="getSuffixFor(rule.condition_type) ? 'pr-10' : ''"
                                                placeholder="Desde" />
                                            <span v-if="getSuffixFor(rule.condition_type)" class="absolute right-3 top-3 text-slate-400 text-xs font-bold">{{ getSuffixFor(rule.condition_type) }}</span>
                                        </div>
                                        <span class="text-xs font-bold text-slate-400">y</span>
                                        <div class="relative flex-1">
                                            <input v-model="rule._value2" type="number"
                                                :step="getStepFor(rule.condition_type)" :min="getMinFor(rule.condition_type)"
                                                :inputmode="getInputmodeFor(rule.condition_type)"
                                                class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                                :class="getSuffixFor(rule.condition_type) ? 'pr-10' : ''"
                                                placeholder="Hasta" />
                                            <span v-if="getSuffixFor(rule.condition_type)" class="absolute right-3 top-3 text-slate-400 text-xs font-bold">{{ getSuffixFor(rule.condition_type) }}</span>
                                        </div>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="relative">
                                        <input v-model="rule.value" type="number"
                                            :step="getStepFor(rule.condition_type)" :min="getMinFor(rule.condition_type)"
                                            :inputmode="getInputmodeFor(rule.condition_type)"
                                            class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-700 focus:ring-2 focus:ring-sky-500 focus:border-transparent transition-all"
                                            :class="getSuffixFor(rule.condition_type) ? 'pr-10' : ''"
                                            :placeholder="getPlaceholderFor(rule.condition_type)" />
                                        <span v-if="getSuffixFor(rule.condition_type)" class="absolute right-3 top-3 text-slate-400 text-xs font-bold">{{ getSuffixFor(rule.condition_type) }}</span>
                                    </div>
                                </template>
                                <p v-if="getHelpTextFor(rule.condition_type)" class="text-xs font-medium text-slate-600 leading-tight mt-1">{{ getHelpTextFor(rule.condition_type) }}</p>
                                <p v-if="errors[`rules.${index}.value`]" class="text-rose-500 text-[10px] mt-1 font-bold">{{ errors[`rules.${index}.value`] }}</p>
                            </div>

                            <!-- Explanation card -->
                            <div v-if="getConditionExplanation(rule)"
                                class="bg-sky-50/80 border border-sky-100 rounded-xl px-4 py-3">
                                <p class="text-xs text-sky-700 leading-relaxed">{{ getConditionExplanation(rule) }}</p>
                            </div>

                        </div>

                        <!-- Add condition button -->
                        <button @click="agregarRegla"
                            class="w-full py-3.5 border-2 border-dashed border-slate-200 rounded-2xl text-sky-600 hover:text-sky-800 hover:border-sky-300 text-sm font-bold flex items-center justify-center gap-2 transition-all">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                            Agregar otra condición
                        </button>

                        <!-- Natural language summary -->
                        <div v-if="formulario.rules.length > 0 && formulario.rules.every(r => r.value?.toString().trim())"
                            class="mt-6 bg-gradient-to-br from-slate-50 to-slate-100/50 border border-slate-200 rounded-2xl p-5 space-y-3">

                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Esta promoción se activará cuando:</p>

                            <div class="space-y-2">
                                <div v-for="(rule, idx) in formulario.rules" :key="idx"
                                    class="flex items-start gap-2.5">
                                    <span class="flex-shrink-0 w-5 h-5 bg-emerald-100 rounded-full flex items-center justify-center mt-0.5">
                                        <svg class="w-3 h-3 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </span>
                                    <div>
                                        <span class="text-xs font-bold text-slate-700">{{ ruleConditionText(rule) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="border-t border-slate-200 pt-3 mt-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-slate-400">↓</span>
                                    <span class="text-xs font-bold text-emerald-700">{{ getDiscountMessageText() }}</span>
                                </div>
                            </div>
                        </div>

                        <p v-if="errors.rules" class="text-rose-500 text-[10px] mt-3 font-bold">{{ errors.rules }}</p>
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
                        <!-- Summary cards -->
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

                        <div v-if="previewData.discount_amount > 0" class="text-center mb-5">
                            <span class="inline-block px-4 py-1.5 bg-green-50 border border-green-200 rounded-full text-xs font-bold text-green-700">
                                Ahorro: {{ formatPrice(previewData.discount_amount) }}
                            </span>
                        </div>

                        <!-- ===== PERCENT / FIXED AMOUNT ===== -->
                        <template v-if="['percent', 'fixed_amount'].includes(previewData.discount_type)">
                            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Simulación</p>
                                </div>
                                <div class="p-5 space-y-3">
                                    <div class="text-xs font-bold text-slate-400 uppercase">Cliente compra:</div>
                                    <div v-for="pp in previewData.product_previews" :key="pp.product_id" class="flex items-center gap-2 text-sm">
                                        <span class="w-1.5 h-1.5 bg-sky-400 rounded-full flex-shrink-0"></span>
                                        <span class="font-bold text-slate-700">{{ pp.name }} ×{{ pp.quantity }}</span>
                                    </div>
                                    <div class="border-t border-slate-100 pt-3 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">Precio</span>
                                            <span class="font-bold text-slate-700">{{ formatPrice(previewData.original_price) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">{{ previewData.discount_type === 'percent' ? 'Descuento' : 'Descuento fijo' }}</span>
                                            <span class="font-bold text-rose-600">{{ previewData.discount_type === 'percent' ? formulario.value + '%' : formatPrice(previewData.discount_amount) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm border-t border-slate-100 pt-2">
                                            <span class="font-black text-slate-700">Total</span>
                                            <span class="font-black text-emerald-700 text-base">{{ formatPrice(previewData.final_price) }}</span>
                                        </div>
                                        <div v-if="previewData.discount_amount > 0" class="flex justify-between text-sm">
                                            <span class="text-slate-500">Ahorro</span>
                                            <span class="font-bold text-green-600">{{ formatPrice(previewData.discount_amount) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- ===== FIXED PRICE ===== -->
                        <template v-else-if="previewData.discount_type === 'fixed_price'">
                            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Simulación</p>
                                </div>
                                <div class="p-5 space-y-3">
                                    <div class="text-xs font-bold text-slate-400 uppercase">Cliente compra:</div>
                                    <div v-for="pp in previewData.product_previews" :key="pp.product_id" class="flex items-center gap-2 text-sm">
                                        <span class="w-1.5 h-1.5 bg-sky-400 rounded-full flex-shrink-0"></span>
                                        <span class="font-bold text-slate-700">{{ pp.name }} ×{{ pp.quantity }}</span>
                                    </div>
                                    <div class="border-t border-slate-100 pt-3 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">Precio normal</span>
                                            <span class="font-bold text-slate-700">{{ formatPrice(previewData.original_price) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">Precio promocional</span>
                                            <span class="font-bold text-emerald-700">{{ formatPrice(previewData.final_price) }}</span>
                                        </div>
                                        <div v-if="previewData.discount_amount > 0" class="flex justify-between text-sm border-t border-slate-100 pt-2">
                                            <span class="text-slate-500">Ahorro</span>
                                            <span class="font-bold text-green-600">{{ formatPrice(previewData.discount_amount) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- ===== X FOR Y (including 2x1) ===== -->
                        <template v-else-if="previewData.discount_type === 'x_for_y'">
                            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Simulación</p>
                                </div>
                                <div class="p-5 space-y-3">
                                    <div class="text-xs font-bold text-slate-400 uppercase">Cliente lleva:</div>
                                    <div v-for="pp in previewData.product_previews" :key="pp.product_id" class="flex items-center gap-2 text-sm">
                                        <span class="w-1.5 h-1.5 bg-sky-400 rounded-full flex-shrink-0"></span>
                                        <span class="font-bold text-slate-700">{{ pp.name }} ×{{ pp.quantity }}</span>
                                    </div>
                                    <div class="border-t border-slate-100 pt-3 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">Subtotal</span>
                                            <span class="font-bold text-slate-700">{{ previewData.product_previews[0]?.quantity }} × {{ formatPrice(previewData.product_previews[0]?.unit_price) }} = {{ formatPrice(previewData.original_price) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">Promoción aplicada</span>
                                            <span class="font-bold text-sky-700">Lleva {{ previewData.product_previews[0]?.quantity }} y paga {{ formulario.discount_config?.y }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm border-t border-slate-100 pt-2">
                                            <span class="font-black text-slate-700">Total a pagar</span>
                                            <span class="font-black text-emerald-700 text-base">{{ formatPrice(previewData.final_price) }}</span>
                                        </div>
                                        <div v-if="previewData.discount_amount > 0" class="flex justify-between text-sm">
                                            <span class="text-slate-500">Ahorro</span>
                                            <span class="font-bold text-green-600">{{ formatPrice(previewData.discount_amount) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- ===== BUNDLE ===== -->
                        <template v-else-if="previewData.discount_type === 'bundle'">
                            <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest">Simulación</p>
                                </div>
                                <div class="p-5 space-y-3">
                                    <div class="text-xs font-bold text-slate-400 uppercase">Productos incluidos:</div>
                                    <div v-for="pp in previewData.product_previews.filter(p => !p.is_summary)" :key="pp.product_id" class="flex items-center gap-2 text-sm">
                                        <span class="w-1.5 h-1.5 bg-sky-400 rounded-full flex-shrink-0"></span>
                                        <span class="font-bold text-slate-700">{{ pp.name }}</span>
                                        <span class="text-xs text-slate-400 ml-auto">{{ formatPrice(pp.original_price) }}</span>
                                    </div>
                                    <div class="border-t border-slate-100 pt-3 space-y-2">
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">Precio individual</span>
                                            <span class="font-bold text-slate-700">{{ formatPrice(previewData.original_price) }}</span>
                                        </div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-slate-500">Precio del combo</span>
                                            <span class="font-bold text-emerald-700">{{ formatPrice(previewData.final_price) }}</span>
                                        </div>
                                        <div v-if="previewData.discount_amount > 0" class="flex justify-between text-sm border-t border-slate-100 pt-2">
                                            <span class="text-slate-500">Ahorro</span>
                                            <span class="font-bold text-green-600">{{ formatPrice(previewData.discount_amount) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Explanation -->
                        <div v-if="previewData.explanation" class="mt-5 p-4 bg-sky-50 border border-sky-100 rounded-xl">
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-sky-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm text-sky-700">{{ previewData.explanation }}</p>
                            </div>
                        </div>

                        <div v-if="previewData.warnings?.length" class="mt-4 p-3 bg-amber-50 rounded-xl border border-amber-200">
                            <p v-for="w in previewData.warnings" :key="w" class="text-amber-700 text-xs font-bold">
                                <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                                {{ w }}
                            </p>
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
                            <span class="font-bold text-slate-700">{{ getDiscountDescription() }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-500">Vigencia</span>
                            <span class="font-bold text-slate-700">{{ formulario.starts_at }} → {{ formulario.ends_at }}</span>
                        </div>
                        <div class="flex justify-between py-2 border-b border-slate-100">
                            <span class="text-slate-500">Productos / Condiciones</span>
                            <span class="font-bold text-slate-700">
                                {{ formulario.type === 'MANUAL' ? `${productosSeleccionados} producto(s)` : `${formulario.rules.length} condicion(es)` }}
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
