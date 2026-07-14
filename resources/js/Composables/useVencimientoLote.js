import { computed } from 'vue';

export function useVencimientoLote() {
    const diasRestantes = (fecha) => {
        if (!fecha) return null;
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const vencimiento = new Date(fecha);
        vencimiento.setHours(0, 0, 0, 0);
        return Math.ceil((vencimiento - hoy) / (1000 * 60 * 60 * 24));
    };

    const textoVencimiento = (fecha) => {
        const dias = diasRestantes(fecha);
        if (dias === null) return '-';
        if (dias < 0) return `Hace ${Math.abs(dias)} días`;
        if (dias === 0) return 'Vence hoy';
        return `Faltan ${dias} días`;
    };

    const claseVencimiento = (fecha) => {
        const dias = diasRestantes(fecha);
        if (dias === null) return 'text-slate-400';
        if (dias < 0) return 'text-rose-600';
        if (dias <= 7) return 'text-rose-500';
        if (dias <= 15) return 'text-orange-500';
        if (dias <= 30) return 'text-amber-500';
        return 'text-emerald-600';
    };

    const claseBadgeVencimiento = (fecha) => {
        const dias = diasRestantes(fecha);
        if (dias === null) return 'bg-slate-100 text-slate-500 border-slate-200';
        if (dias < 0) return 'bg-rose-50 text-rose-700 border-rose-200';
        if (dias <= 7) return 'bg-rose-50 text-rose-600 border-rose-200';
        if (dias <= 15) return 'bg-orange-50 text-orange-600 border-orange-200';
        if (dias <= 30) return 'bg-amber-50 text-amber-600 border-amber-200';
        return 'bg-emerald-50 text-emerald-600 border-emerald-200';
    };

    const claseFechaBg = (fecha) => {
        const dias = diasRestantes(fecha);
        if (dias === null) return '';
        if (dias < 0) return 'bg-rose-50';
        if (dias <= 7) return 'bg-rose-50';
        if (dias <= 15) return 'bg-orange-50';
        if (dias <= 30) return 'bg-amber-50/50';
        return '';
    };

    const esVencido = (fecha) => diasRestantes(fecha) < 0;
    const esUrgente = (fecha) => { const d = diasRestantes(fecha); return d !== null && d >= 0 && d <= 7; };
    const esProximo = (fecha) => { const d = diasRestantes(fecha); return d !== null && d > 7 && d <= 30; };
    const esNormal = (fecha) => { const d = diasRestantes(fecha); return d !== null && d > 30; };

    return {
        diasRestantes,
        textoVencimiento,
        claseVencimiento,
        claseBadgeVencimiento,
        claseFechaBg,
        esVencido,
        esUrgente,
        esProximo,
        esNormal,
    };
}
