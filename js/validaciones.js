// ============================================================
//  SalonHub - Utilidades de Validación Frontend
//  Archivo: /js/validaciones.js
//  Funciones reutilizables en todas las páginas
// ============================================================

/**
 * Muestra un mensaje de error debajo de un campo.
 * @param {string} idSpan  - ID del <span class="error-campo">
 * @param {string} mensaje - Texto del error
 */
function mostrarError(idSpan, mensaje) {
    const span = document.getElementById(idSpan);
    if (span) {
        span.textContent = mensaje;
        span.classList.add('activo');

        // Marcar el input relacionado como inválido
        const campoPadre = span.closest('.campo-grupo');
        if (campoPadre) {
            const input = campoPadre.querySelector('input, select, textarea');
            if (input) input.classList.add('input-invalido');
        }
    }
}

/**
 * Limpia los errores de una lista de spans.
 * @param {string[]} ids - Array de IDs de spans de error
 */
function limpiarErrores(ids) {
    ids.forEach(id => {
        const span = document.getElementById(id);
        if (span) {
            span.textContent = '';
            span.classList.remove('activo');
        }
    });

    // Limpiar también las clases de inputs inválidos
    document.querySelectorAll('.input-invalido').forEach(el => {
        el.classList.remove('input-invalido');
    });
}

/**
 * Valida formato de email.
 * @param {string} email
 * @returns {boolean}
 */
function esEmailValido(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

/**
 * Valida que una contraseña sea segura (8+, mayúscula, número).
 * @param {string} pass
 * @returns {{valido: boolean, mensaje: string}}
 */
function validarContrasena(pass) {
    if (pass.length < 8)          return { valido: false, mensaje: 'Mínimo 8 caracteres.' };
    if (!/[A-Z]/.test(pass))      return { valido: false, mensaje: 'Debe incluir al menos una mayúscula.' };
    if (!/[0-9]/.test(pass))      return { valido: false, mensaje: 'Debe incluir al menos un número.' };
    return { valido: true, mensaje: '' };
}

/**
 * Helper para hacer llamadas AJAX con fetch y FormData.
 * @param {string}   url     - Endpoint
 * @param {FormData} datos   - Datos a enviar
 * @returns {Promise<Object>} - Respuesta JSON parseada
 */
async function ajaxPost(url, datos) {
    try {
        const respuesta = await fetch(url, { method: 'POST', body: datos });
        if (!respuesta.ok) throw new Error('Error HTTP ' + respuesta.status);
        return await respuesta.json();
    } catch (err) {
        console.error('Error AJAX en ' + url + ':', err);
        return { exito: false, mensaje: 'Error de conexión.' };
    }
}

// ── Deshabilitar botón de submit mientras se procesa ────────
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function() {
            const btn = form.querySelector('[type="submit"]');
            if (btn && !form.hasAttribute('data-no-disable')) {
                // Solo deshabilitar si el formulario pasó validaciones (sin errores visibles)
                const erroresVisibles = form.querySelectorAll('.error-campo.activo');
                if (erroresVisibles.length === 0) {
                    btn.disabled = true;
                    btn.textContent = btn.textContent + ' ...';
                }
            }
        });
    });
});
