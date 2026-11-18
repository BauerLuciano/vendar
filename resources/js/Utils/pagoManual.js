export const MODAL_PAGO_MANUAL_HTML = `
    <div class="text-left space-y-3">
        <p class="text-sm text-slate-600">Comercio: <strong id="b3-comercio-nombre"></strong></p>
        <p class="text-xs text-amber-700 bg-amber-50 rounded-lg p-2">El precio comercial todavía no está confirmado. Este registro sólo debe usarse cuando el negocio habilite los cobros.</p>
        <label class="block text-xs font-semibold text-slate-600">Importe recibido
            <input id="b3-importe" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
        </label>
        <label class="block text-xs font-semibold text-slate-600">Método de pago
            <select id="b3-metodo" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></select>
        </label>
        <label class="block text-xs font-semibold text-slate-600">Referencia / comprobante
            <input id="b3-referencia" type="text" maxlength="255" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
        </label>
        <label class="block text-xs font-semibold text-slate-600">Fecha de pago
            <input id="b3-fecha" type="date" class="mt-1 w-full rounded-lg border-slate-300 text-sm" />
        </label>
    </div>
`;

/**
 * Completa el modal con datos dinámicos sin interpretarlos como HTML.
 * El nombre del comercio puede venir del registro público, así que sólo se
 * asigna como texto y nunca se interpola en el string `html` de SweetAlert.
 */
export const inicializarModalPagoManual = ({
    documento = document,
    nombre,
    importe,
    hoy,
    metodos = [],
}) => {
    const nombreEl = documento.getElementById('b3-comercio-nombre');
    if (nombreEl) {
        nombreEl.textContent = nombre ?? '';
    }

    const importeEl = documento.getElementById('b3-importe');
    if (importeEl) {
        importeEl.value = importe ?? '';
    }

    const fechaEl = documento.getElementById('b3-fecha');
    if (fechaEl) {
        fechaEl.max = hoy ?? '';
        fechaEl.value = hoy ?? '';
    }

    const metodoEl = documento.getElementById('b3-metodo');
    if (metodoEl) {
        metodoEl.replaceChildren();

        metodos.forEach((metodo) => {
            const opcion = documento.createElement('option');
            opcion.value = metodo;
            opcion.textContent = String(metodo).charAt(0).toUpperCase() + String(metodo).slice(1);
            metodoEl.appendChild(opcion);
        });
    }
};

export const mensajeErrorPago = (errors = {}) => {
    const mensajes = Object.values(errors ?? {})
        .flat()
        .filter((mensaje) => mensaje !== null && mensaje !== undefined && String(mensaje).trim() !== '')
        .map((mensaje) => String(mensaje));

    return mensajes.length > 0
        ? mensajes.join('\n')
        : 'No se pudo registrar el pago. Intentá nuevamente.';
};

export const mensajeExitoPago = (page) => {
    const mensaje = page?.props?.flash?.exito ?? page?.flash?.exito;

    return typeof mensaje === 'string' && mensaje.trim() !== ''
        ? mensaje
        : 'Pago registrado correctamente.';
};
