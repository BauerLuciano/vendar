export const formatearMoneda = (valor) => {
    const n = parseFloat(valor || 0);
    const signo = n < 0 ? '-' : '';
    const formateado = Math.abs(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `$ ${signo}${formateado}`;
};

export const formatearDinero = (monto) => {
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(monto);
};

export const formatearFecha = (f) => {
    if (!f) return '';
    return new Date(f).toLocaleString('es-AR', {
        day: '2-digit', month: '2-digit', year: 'numeric',
        hour: '2-digit', minute: '2-digit', hour12: false
    });
};

export const formatearFechaCorta = (f) => {
    if (!f) return '';
    return new Date(f).toLocaleDateString('es-AR');
};

export const formatearHora = (f) => {
    if (!f) return '';
    return new Date(f).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
};

export const calcularTotalActual = (bal) => {
    if (!bal) return 0;
    return parseFloat(bal.esperado_efectivo || 0) +
        parseFloat(bal.esperado_mp || 0) +
        parseFloat(bal.esperado_transf || 0);
};
