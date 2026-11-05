<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, computed, reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
    usuario: Object,
    roles: Array,
    sucursales: Array,
    comercios: { type: Array, default: () => [] },
    esAdminGlobal: { type: Boolean, default: false },
    comercioActualId: { type: [Number, String], default: null }
});

const emit = defineEmits(['cerrar']);

const form = useForm({
    comercio_id: '',
    name: '',
    apellido: '',
    email: '',
    telefono: '',
    password: '',
    password_confirmation: '',
    branch_id: '',
    rol: '',
    sucursales: []
});

const erroresLocales = reactive({});
const tocados = reactive({});

const REGEX_NOMBRE = /^[\p{L}]+(?:[\s'\-][\p{L}]+)*$/u;
const REGEX_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const REGEX_TELEFONO = /^[0-9+\-()\s.]+$/;

const normalizarTexto = (valor) => (valor || '').trim().replace(/\s+/g, ' ');

const limpiarError = (campo) => {
    delete erroresLocales[campo];
    if (form.errors[campo]) form.clearErrors(campo);
};

const validarCampo = (campo) => {
    const valor = form[campo];

    switch (campo) {
        case 'name': {
            const nombre = normalizarTexto(valor);
            if (!nombre) return 'Ingresá un nombre válido.';
            if (nombre.length < 2 || nombre.length > 100) return 'El nombre debe tener entre 2 y 100 caracteres.';
            if (!REGEX_NOMBRE.test(nombre)) return 'Ingresá un nombre válido. Solo se permiten letras y espacios.';
            break;
        }
        case 'apellido': {
            const apellido = normalizarTexto(valor);
            if (!apellido) break;
            if (apellido.length < 2 || apellido.length > 100) return 'El apellido debe tener entre 2 y 100 caracteres.';
            if (!REGEX_NOMBRE.test(apellido)) return 'El apellido no puede contener números ni símbolos no permitidos.';
            break;
        }
        case 'email': {
            const email = (valor || '').trim();
            if (!email) return 'Ingresá un email válido.';
            if (!REGEX_EMAIL.test(email)) return 'Ingresá un email válido.';
            break;
        }
        case 'telefono': {
            const telefono = (valor || '').trim();
            if (!telefono) break;
            if (!REGEX_TELEFONO.test(telefono)) return 'El teléfono contiene caracteres no permitidos.';
            const digitos = telefono.replace(/\D/g, '');
            if (digitos.length < 8 || digitos.length > 15) return 'Ingresá un teléfono válido (entre 8 y 15 dígitos).';
            break;
        }
        case 'password':
            if (!props.usuario && !valor) return 'Ingresá una contraseña.';
            if (valor && valor.length < 8) return 'La contraseña debe tener al menos 8 caracteres.';
            break;
        case 'password_confirmation':
            if (form.password && form.password !== valor) return 'Las contraseñas no coinciden.';
            if (form.password && !valor) return 'Confirmá la contraseña.';
            break;
        case 'comercio_id':
            if (props.esAdminGlobal && !valor) return 'Seleccioná un comercio.';
            break;
        case 'branch_id':
            if (!valor) return 'Seleccioná una sucursal base.';
            break;
        case 'rol':
            if (!valor) return 'Seleccioná un rol.';
            break;
    }

    return '';
};

const validarCampoSiTocado = (campo) => {
    const error = validarCampo(campo);
    if (error) erroresLocales[campo] = error;
    else delete erroresLocales[campo];
};

const marcarTocado = (campo) => {
    tocados[campo] = true;
    validarCampoSiTocado(campo);
};

const alEscribir = (campo) => {
    limpiarError(campo);
    if (tocados[campo]) validarCampoSiTocado(campo);
    if (campo === 'password' && tocados.password_confirmation) {
        validarCampoSiTocado('password_confirmation');
    }
};

const errorDe = (campo) => erroresLocales[campo] || form.errors[campo] || '';

const errorSucursales = computed(() => {
    if (form.errors.sucursales) return form.errors.sucursales;
    const clave = Object.keys(form.errors).find((key) => key.startsWith('sucursales.'));
    return clave ? form.errors[clave] : '';
});

const validarFormulario = () => {
    const campos = ['name', 'apellido', 'email', 'telefono', 'password', 'password_confirmation', 'comercio_id', 'branch_id', 'rol'];
    let valido = true;

    campos.forEach((campo) => {
        const error = validarCampo(campo);
        if (error) {
            erroresLocales[campo] = error;
            valido = false;
        } else {
            delete erroresLocales[campo];
        }
    });

    return valido;
};

const sucursalesDelComercio = computed(() => {
    if (!props.esAdminGlobal) return props.sucursales;

    if (!form.comercio_id) return [];

    return props.sucursales.filter((sucursal) => String(sucursal.comercio_id) === String(form.comercio_id));
});

const sucursalesAdicionales = computed(() => {
    return sucursalesDelComercio.value.filter((sucursal) => String(sucursal.id) !== String(form.branch_id));
});

const sucursalBaseSeleccionada = computed(() => {
    return sucursalesDelComercio.value.find((sucursal) => String(sucursal.id) === String(form.branch_id)) || null;
});

const cambiarComercio = () => {
    form.branch_id = '';
    form.sucursales = [];
    limpiarError('comercio_id');
    limpiarError('branch_id');
};

watch(() => form.sucursales, (vals) => {
    if (form.branch_id && !vals.map(String).includes(String(form.branch_id)) && vals.length > 0) {
        form.branch_id = vals[0];
    } else if (vals.length === 0) {
        form.branch_id = '';
    }
}, { deep: true });

watch(() => form.branch_id, (nuevo) => {
    if (nuevo && !form.sucursales.map(String).includes(String(nuevo))) {
        form.sucursales.push(nuevo);
    }
});

watch(() => props.mostrar, (mostrando) => {
    if (!mostrando) return;

    Object.keys(erroresLocales).forEach((key) => delete erroresLocales[key]);
    Object.keys(tocados).forEach((key) => delete tocados[key]);
    form.clearErrors();

    if (props.usuario) {
        const apellido = props.usuario.apellido || '';
        let nombre = props.usuario.name || '';

        if (apellido && nombre.toLowerCase().endsWith(' ' + apellido.toLowerCase())) {
            nombre = nombre.slice(0, -(apellido.length + 1));
        }

        form.comercio_id = props.usuario.comercio_id
            || props.usuario.branch?.comercio_id
            || (props.esAdminGlobal ? '' : props.comercioActualId || '');
        form.name = nombre;
        form.apellido = apellido;
        form.email = props.usuario.email || '';
        form.telefono = props.usuario.telefono || '';
        form.password = '';
        form.password_confirmation = '';
        form.branch_id = props.usuario.branch_id || '';
        form.rol = props.usuario.roles?.length > 0 ? props.usuario.roles[0].name : '';
        form.sucursales = props.usuario.sucursales?.map((sucursal) => sucursal.id) || [];

        if (props.usuario.branch_id && !form.sucursales.map(String).includes(String(props.usuario.branch_id))) {
            form.sucursales.push(props.usuario.branch_id);
        }
    } else {
        form.reset();
        form.comercio_id = props.esAdminGlobal ? '' : (props.comercioActualId || '');
        form.sucursales = [];
    }
});

const guardar = () => {
    if (!validarFormulario()) {
        Swal.fire('Revisá los datos', 'Hay campos con errores. Corregilos antes de guardar.', 'warning');
        return;
    }

    form.name = normalizarTexto(form.name);
    form.apellido = normalizarTexto(form.apellido);
    form.email = (form.email || '').trim().toLowerCase();
    form.telefono = (form.telefono || '').trim();

    if (form.branch_id && !form.sucursales.map(String).includes(String(form.branch_id))) {
        form.sucursales.push(form.branch_id);
    }

    if (props.usuario) {
        Swal.fire({
            title: '¿Actualizar Usuario?',
            text: `Se van a modificar los datos de ${props.usuario.name}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0284c7',
            confirmButtonText: 'Sí, actualizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                form.put(route('usuarios.update', props.usuario.id), {
                    onSuccess: () => {
                        emit('cerrar');
                        Swal.fire('¡Actualizado!', 'Usuario modificado con éxito.', 'success');
                    },
                    onError: () => {
                        Swal.fire('Error', 'No se pudo actualizar el usuario. Revisá los datos e intentá de nuevo.', 'error');
                    }
                });
            }
        });
    } else {
        form.post(route('usuarios.store'), {
            onSuccess: () => {
                emit('cerrar');
                Swal.fire('¡Creado!', 'Nuevo usuario registrado en el sistema.', 'success');
            },
            onError: () => {
                Swal.fire('Error', 'No se pudo crear el usuario. Revisá los datos e intentá de nuevo.', 'error');
            }
        });
    }
};
</script>

<template>
    <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] overflow-y-auto animate-in zoom-in-95 duration-200">
            <div class="bg-[#0284c7] p-5 text-white flex justify-between items-center sticky top-0 z-10">
                <h3 class="font-bold uppercase tracking-widest text-sm">{{ usuario ? 'Editar Usuario' : 'Nuevo Usuario' }}</h3>
                <button @click="$emit('cerrar')" class="text-white hover:text-sky-200 text-xl font-bold">&times;</button>
            </div>

            <form @submit.prevent="guardar" class="p-6 space-y-4">
                <div v-if="esAdminGlobal">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Comercio</label>
                    <select v-model="form.comercio_id" @change="cambiarComercio" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" required>
                        <option value="" disabled>Seleccione el comercio...</option>
                        <option v-for="comercio in comercios" :key="comercio.id" :value="comercio.id">{{ comercio.nombre }}</option>
                    </select>
                    <span class="text-rose-500 text-xs" v-if="errorDe('comercio_id')">{{ errorDe('comercio_id') }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Nombre</label>
                        <input v-model="form.name" @input="alEscribir('name')" @blur="marcarTocado('name')" type="text" maxlength="100" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" placeholder="Ej: Juan Carlos" required>
                        <span class="text-rose-500 text-xs" v-if="errorDe('name')">{{ errorDe('name') }}</span>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Apellido <span class="text-slate-400 normal-case font-medium">(opcional)</span></label>
                        <input v-model="form.apellido" @input="alEscribir('apellido')" @blur="marcarTocado('apellido')" type="text" maxlength="100" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" placeholder="Ej: Pérez">
                        <span class="text-rose-500 text-xs" v-if="errorDe('apellido')">{{ errorDe('apellido') }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Email</label>
                        <input v-model="form.email" @input="alEscribir('email')" @blur="marcarTocado('email')" type="email" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" placeholder="usuario@comercio.com" required>
                        <span class="text-rose-500 text-xs" v-if="errorDe('email')">{{ errorDe('email') }}</span>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Teléfono <span class="text-slate-400 normal-case font-medium">(opcional)</span></label>
                        <input v-model="form.telefono" @input="alEscribir('telefono')" @blur="marcarTocado('telefono')" type="tel" maxlength="30" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" placeholder="Ej: +54 9 375 512-3456">
                        <span class="text-rose-500 text-xs" v-if="errorDe('telefono')">{{ errorDe('telefono') }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Sucursal Base</label>
                        <select v-model="form.branch_id" @change="alEscribir('branch_id')" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" required>
                            <option value="" disabled>Seleccione...</option>
                            <option v-for="sucursal in sucursalesDelComercio" :key="sucursal.id" :value="sucursal.id">{{ sucursal.nombre }}</option>
                        </select>
                        <span class="text-rose-500 text-xs" v-if="errorDe('branch_id')">{{ errorDe('branch_id') }}</span>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Rol / Perfil</label>
                        <select v-model="form.rol" @change="alEscribir('rol')" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500 font-bold text-sky-700" required>
                            <option value="" disabled>Seleccione...</option>
                            <option v-for="rol in roles" :key="rol.id" :value="rol.name">{{ rol.name }}</option>
                        </select>
                        <span class="text-rose-500 text-xs" v-if="errorDe('rol')">{{ errorDe('rol') }}</span>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <label class="block text-[11px] font-bold text-slate-500 uppercase mb-2">Sucursales Adicionales</label>
                    <span class="text-[10px] text-slate-400 block mb-3">
                        La sucursal base ya está incluida. Tildá otras sucursales si el usuario también debe tener acceso.
                    </span>

                    <p v-if="esAdminGlobal && !form.comercio_id" class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-3">
                        Seleccioná un comercio para ver sus sucursales disponibles.
                    </p>
                    <p v-else-if="sucursalesAdicionales.length === 0" class="text-xs text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-3">
                        No hay otras sucursales disponibles. Este usuario tendrá acceso únicamente a su sucursal base.
                    </p>
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label v-for="sucursal in sucursalesAdicionales" :key="sucursal.id"
                            class="flex items-center gap-2 p-2.5 rounded-lg border cursor-pointer transition-all text-sm"
                            :class="form.sucursales.map(String).includes(String(sucursal.id)) ? 'bg-sky-50 border-sky-300 text-sky-800 font-bold' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300'">
                            <input type="checkbox" :value="sucursal.id" v-model="form.sucursales" @change="alEscribir('sucursales')"
                                class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            {{ sucursal.nombre }}
                        </label>
                    </div>

                    <p v-if="sucursalBaseSeleccionada" class="text-[10px] text-sky-700 bg-sky-50 border border-sky-100 rounded-lg px-3 py-2 mt-3">
                        Sucursal base: <strong>{{ sucursalBaseSeleccionada.nombre }}</strong>
                    </p>
                    <span class="text-rose-500 text-xs" v-if="errorSucursales">{{ errorSucursales }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Contraseña</label>
                        <input v-model="form.password" @input="alEscribir('password')" @blur="marcarTocado('password')" type="password" autocomplete="new-password" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" :placeholder="usuario ? 'Dejar en blanco para no cambiarla' : 'Mínimo 8 caracteres'" :required="!usuario">
                        <span class="text-rose-500 text-xs" v-if="errorDe('password')">{{ errorDe('password') }}</span>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Confirmar Contraseña</label>
                        <input v-model="form.password_confirmation" @input="alEscribir('password_confirmation')" @blur="marcarTocado('password_confirmation')" type="password" autocomplete="new-password" class="w-full rounded-lg border-slate-200 text-sm focus:ring-sky-500" :placeholder="usuario ? 'Repetir solo si se cambia' : 'Repetir contraseña'" :required="!usuario">
                        <span class="text-rose-500 text-xs" v-if="errorDe('password_confirmation')">{{ errorDe('password_confirmation') }}</span>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-6 mt-2 border-t border-slate-100">
                    <button type="button" @click="$emit('cerrar')" class="px-5 py-2.5 text-sm font-bold text-slate-500 hover:bg-slate-100 rounded-lg transition-colors">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="px-5 py-2.5 bg-[#0284c7] text-white text-sm font-bold uppercase tracking-widest rounded-lg hover:bg-sky-700 disabled:opacity-50 transition-colors">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
