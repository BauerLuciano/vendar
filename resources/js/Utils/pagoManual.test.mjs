import test from 'node:test';
import assert from 'node:assert/strict';
import {
    MODAL_PAGO_MANUAL_HTML,
    inicializarModalPagoManual,
    mensajeErrorPago,
    mensajeExitoPago,
} from './pagoManual.js';

const crearElemento = (tag) => ({
    tagName: String(tag).toUpperCase(),
    value: '',
    max: '',
    textContent: '',
    hijos: [],
    appendChild(hijo) {
        this.hijos.push(hijo);
    },
    replaceChildren() {
        this.hijos = [];
    },
    get innerHTML() {
        return this.textContent;
    },
    set innerHTML(valor) {
        throw new Error(`No se debe usar innerHTML para datos dinámicos: ${valor}`);
    },
});

const crearDocumento = () => {
    const elementos = new Map();

    return {
        elementos,
        getElementById(id) {
            return elementos.get(id) ?? null;
        },
        createElement(tag) {
            return crearElemento(tag);
        },
    };
};

const prepararDocumento = () => {
    const documento = crearDocumento();

    documento.elementos.set('b3-comercio-nombre', crearElemento('strong'));
    documento.elementos.set('b3-importe', crearElemento('input'));
    documento.elementos.set('b3-metodo', crearElemento('select'));
    documento.elementos.set('b3-fecha', crearElemento('input'));

    return documento;
};

test('el modal no interpola el nombre del comercio en el HTML estático', () => {
    const payload = '<img src=x onerror="globalThis.__xssEjecutado = true">';

    assert.equal(MODAL_PAGO_MANUAL_HTML.includes(payload), false);
    assert.match(MODAL_PAGO_MANUAL_HTML, /id="b3-comercio-nombre"/);
    assert.match(MODAL_PAGO_MANUAL_HTML, /id="b3-metodo"/);
});

test('un nombre malicioso se asigna como texto y no como HTML', () => {
    const payload = '<img src=x onerror="globalThis.__xssEjecutado = true">';
    const documento = prepararDocumento();

    globalThis.__xssEjecutado = false;

    inicializarModalPagoManual({
        documento,
        nombre: payload,
        importe: 8000,
        hoy: '2026-06-10',
        metodos: ['efectivo', 'transferencia'],
    });

    const nombre = documento.elementos.get('b3-comercio-nombre');

    assert.equal(nombre.textContent, payload);
    assert.equal(nombre.hijos.length, 0);
    assert.equal(globalThis.__xssEjecutado, false);

    const metodo = documento.elementos.get('b3-metodo');

    assert.equal(metodo.hijos.length, 2);
    assert.equal(metodo.hijos[0].textContent, 'Efectivo');
    assert.equal(metodo.hijos[1].textContent, 'Transferencia');
    assert.equal(documento.elementos.get('b3-importe').value, 8000);
    assert.equal(documento.elementos.get('b3-fecha').max, '2026-06-10');
    assert.equal(documento.elementos.get('b3-fecha').value, '2026-06-10');
});

test('el feedback muestra el error de negocio y el flash de éxito', () => {
    assert.equal(
        mensajeErrorPago({ pago: 'Ya existe un pago aprobado para ese período.' }),
        'Ya existe un pago aprobado para ese período.',
    );

    assert.equal(
        mensajeErrorPago({
            importe: 'El importe es obligatorio.',
            pago: 'Error de negocio.',
        }),
        'El importe es obligatorio.\nError de negocio.',
    );

    assert.equal(
        mensajeErrorPago({ pago: ['Primer error.', 'Segundo error.'] }),
        'Primer error.\nSegundo error.',
    );

    assert.equal(
        mensajeErrorPago({}),
        'No se pudo registrar el pago. Intentá nuevamente.',
    );

    assert.equal(
        mensajeExitoPago({ props: { flash: { exito: 'Pago #12 registrado.' } } }),
        'Pago #12 registrado.',
    );

    assert.equal(
        mensajeExitoPago({ flash: { exito: 'Pago #13 registrado.' } }),
        'Pago #13 registrado.',
    );

    assert.equal(
        mensajeExitoPago({}),
        'Pago registrado correctamente.',
    );
});
