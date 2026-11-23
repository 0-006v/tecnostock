/*
 * TecnoStock - validaciones del cliente (JavaScript puro).
 * Espejan EXACTAMENTE las funciones validar_producto() y validar_movimiento()
 * de includes/funciones.php. El servidor siempre vuelve a validar.
 */
(function () {
  'use strict';

  var ENTERO = /^-?\d+$/;
  var CODIGO = /^[A-Za-z0-9_-]{3,30}$/;

  // ---------- Utilidades de interfaz ----------
  function mostrarError(campo, mensaje) {
    limpiarError(campo);
    campo.classList.add('is-invalid');
    var div = document.createElement('div');
    div.className = 'invalid-feedback d-block js-error';
    div.textContent = mensaje;
    campo.parentNode.appendChild(div);
  }

  function limpiarError(campo) {
    campo.classList.remove('is-invalid');
    var prev = campo.parentNode.querySelectorAll('.js-error');
    prev.forEach(function (n) { n.remove(); });
  }

  function valor(form, nombre) {
    var el = form.elements[nombre];
    return el ? String(el.value).trim() : '';
  }

  // ---------- Reglas (idénticas a PHP) ----------
  function validarProducto(form) {
    var errores = {};
    var codigo = valor(form, 'codigo');
    var nombre = valor(form, 'nombre');
    var desc = valor(form, 'descripcion');
    var cat = valor(form, 'id_categoria');
    var precio = valor(form, 'precio');

    if (codigo === '') errores.codigo = 'El código es obligatorio.';
    else if (!CODIGO.test(codigo)) errores.codigo = 'El código debe tener 3 a 30 caracteres (letras, números, guion).';

    if (nombre === '') errores.nombre = 'El nombre es obligatorio.';
    else if (nombre.length < 3 || nombre.length > 100) errores.nombre = 'El nombre debe tener entre 3 y 100 caracteres.';

    if (desc.length > 255) errores.descripcion = 'La descripción no puede superar 255 caracteres.';

    if (cat === '' || !/^\d+$/.test(cat)) errores.id_categoria = 'Seleccione una categoría.';

    if (precio === '' || isNaN(Number(precio))) errores.precio = 'El precio es obligatorio y debe ser numérico.';
    else if (Number(precio) < 0) errores.precio = 'El precio no puede ser negativo.';
    else if (Number(precio) > 99999999.99) errores.precio = 'El precio es demasiado alto.';

    [['stock_actual', 'El stock actual'], ['stock_minimo', 'El stock mínimo']].forEach(function (par) {
      var v = valor(form, par[0]);
      if (v === '' || !ENTERO.test(v)) errores[par[0]] = par[1] + ' es obligatorio y debe ser un entero.';
      else if (parseInt(v, 10) < 0) errores[par[0]] = par[1] + ' no puede ser negativo.';
      else if (parseInt(v, 10) > 1000000) errores[par[0]] = par[1] + ' no puede superar 1.000.000.';
    });
    return errores;
  }

  function validarMovimiento(form) {
    var errores = {};
    var prod = valor(form, 'id_producto');
    var cant = valor(form, 'cantidad');
    var obs = valor(form, 'observacion');
    var tipoEl = form.querySelector('input[name="tipo"]:checked');
    var tipo = tipoEl ? tipoEl.value : '';

    if (prod === '' || !/^\d+$/.test(prod)) errores.id_producto = 'Seleccione un producto.';
    if (tipo !== 'entrada' && tipo !== 'salida') errores.tipo = 'Seleccione el tipo de movimiento.';

    if (cant === '' || !ENTERO.test(cant)) errores.cantidad = 'La cantidad es obligatoria y debe ser un entero.';
    else if (parseInt(cant, 10) <= 0) errores.cantidad = 'La cantidad debe ser mayor que cero.';
    else if (parseInt(cant, 10) > 1000000) errores.cantidad = 'La cantidad no puede superar 1.000.000.';
    else if (tipo === 'salida' && prod !== '') {
      // Alerta previa: salida mayor al stock disponible (el servidor lo vuelve a comprobar con la fila bloqueada).
      var opt = form.elements['id_producto'].selectedOptions[0];
      var stock = opt ? parseInt(opt.getAttribute('data-stock'), 10) : NaN;
      if (!isNaN(stock) && parseInt(cant, 10) > stock) {
        errores.cantidad = 'Stock insuficiente: la salida de ' + parseInt(cant, 10) + ' supera el stock disponible (' + stock + ').';
      }
    }

    if (obs === '') errores.observacion = 'La observación es obligatoria.';
    else if (obs.length > 255) errores.observacion = 'La observación no puede superar 255 caracteres.';
    return errores;
  }

  function aplicar(form, errores) {
    Array.prototype.forEach.call(form.elements, function (el) { if (el.name) limpiarError(el); });
    var primero = null;
    Object.keys(errores).forEach(function (nombre) {
      var el = form.elements[nombre];
      if (el && el.nodeType === 1) {
        mostrarError(el, errores[nombre]);
        if (!primero) primero = el;
      } else if (el && el.length) { // grupo de radios
        mostrarError(el[0], errores[nombre]);
        if (!primero) primero = el[0];
      }
    });
    if (primero) primero.focus();
    return Object.keys(errores).length === 0;
  }

  // ---------- Enlaces a los formularios ----------
  var fp = document.getElementById('form-producto');
  if (fp) {
    fp.addEventListener('submit', function (ev) { if (!aplicar(fp, validarProducto(fp))) ev.preventDefault(); });
    fp.addEventListener('input', function (ev) { if (ev.target.name) limpiarError(ev.target); });
  }

  var fm = document.getElementById('form-movimiento');
  if (fm) {
    var ayuda = document.getElementById('ayuda-stock');
    var actualizarAyuda = function () {
      var opt = fm.elements['id_producto'].selectedOptions[0];
      var tipoEl = fm.querySelector('input[name="tipo"]:checked');
      if (opt && opt.value !== '' && tipoEl && tipoEl.value === 'salida') {
        ayuda.textContent = 'Stock disponible: ' + opt.getAttribute('data-stock');
      } else {
        ayuda.textContent = '';
      }
    };
    fm.addEventListener('submit', function (ev) { if (!aplicar(fm, validarMovimiento(fm))) ev.preventDefault(); });
    fm.addEventListener('change', actualizarAyuda);
    fm.addEventListener('input', function (ev) { if (ev.target.name) limpiarError(ev.target); });
    actualizarAyuda();
  }

  // ---------- Confirmación antes de desactivar / reactivar ----------
  document.querySelectorAll('.form-estado').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      var accion = f.getAttribute('data-accion');
      var nombre = f.getAttribute('data-nombre');
      var msg = accion === 'desactivar'
        ? '¿Desactivar "' + nombre + '"? No admitirá nuevos movimientos; su historial se conserva.'
        : '¿Reactivar "' + nombre + '"?';
      if (!window.confirm(msg)) ev.preventDefault();
    });
  });

  // ---------- Buscador en tiempo real (nombre, código o categoría) ----------
  var buscador = document.getElementById('buscador');
  var tabla = document.getElementById('tabla-productos');
  if (buscador && tabla) {
    buscador.addEventListener('input', function () {
      var t = buscador.value.trim().toLowerCase();
      tabla.querySelectorAll('tbody tr[data-texto]').forEach(function (fila) {
        fila.style.display = fila.getAttribute('data-texto').indexOf(t) !== -1 ? '' : 'none';
      });
    });
  }
})();
