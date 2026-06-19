import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const RUTAS_POS = ['pos.', 'pos.abrir_turno', 'pos.index'];

function enPos() {
    const current = route().current();
    if (!current) return false;
    return RUTAS_POS.some(r => current.startsWith(r));
}

export function useAtajosTeclado() {
    const handleKeydown = (e) => {
        if (enPos()) return;

        switch (e.key) {
            case 'F1':
                e.preventDefault();
                router.visit(route('dashboard'));
                break;
            case 'F5':
                e.preventDefault();
                router.visit(route('pos.index'));
                break;
            case 'F6':
                e.preventDefault();
                router.visit(route('caja-diaria.index'));
                break;
            case 'F7':
                e.preventDefault();
                router.visit(route('ventas.index'));
                break;
        }
    };

    onMounted(() => window.addEventListener('keydown', handleKeydown));
    onUnmounted(() => window.removeEventListener('keydown', handleKeydown));
}
