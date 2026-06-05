import axios from 'axios';
import Swal from 'sweetalert2';

export function useCajaDiaria() {
    const abrirCajaApi = async ({ caja_id, saldo_inicial_efectivo = 0, saldo_inicial_mp = 0 }) => {
        const payload = {
            caja: caja_id,
            saldo_inicial_efectivo,
            saldo_inicial_mp,
        };

        await axios.post('/api/sesiones-caja/abrir', payload);

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Turno iniciado correctamente',
            showConfirmButton: false,
            timer: 2000,
        });
    };

    return { abrirCajaApi };
}
