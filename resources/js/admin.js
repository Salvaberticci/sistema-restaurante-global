/**
 * Contadores animados de los indicadores del panel.
 *
 * Un número que aparece de golpe obliga a leerlo dos veces: una para ver cuánto
 * es y otra para compararlo con el de al lado. El mismo número bajando desde su
 * valor final se lee una sola vez.
 *
 * Sin librería: son veinte líneas y no vale la pena meter treinta kilobytes de
 * JavaScript en una dependencia que solo hace esto.
 *
 * Se respeta `prefers-reduced-motion`: ahí el número aparece directo, sin
 * animação, que es lo que pidió quien la desactivó.
 */

/**
 * @param {Element} elemento  elemento con data-srg-contador
 */
function animarContador(elemento) {
    const destino = parseFloat(elemento.dataset.srgContador ?? '0')
    const decimales = parseInt(elemento.dataset.srgDecimales ?? '0', 10)
    const prefijo = elemento.dataset.srgPrefijo ?? ''
    const sufijo = elemento.dataset.srgSufijo ?? ''

    const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches

    if (reducido) {
        elemento.textContent = prefijo + formatear(destino, decimales) + sufijo
        return
    }

    const duracion = 900
    const inicio = performance.now()

    function paso(ahora) {
        const transcurrido = Math.min(1, (ahora - inicio) / duracion)

        // Salida suave: rápido al principio, frenando al final. Una recta lineal
        // se percibe como un contador mecánico.
        const avance = 1 - Math.pow(1 - transcurrido, 3)

        elemento.textContent = prefijo + formatear(destino * avance, decimales) + sufijo

        if (transcurrido < 1) {
            requestAnimationFrame(paso)
        }
    }

    requestAnimationFrame(paso)
}

/** Agrupa miles con el separador del navegador, que ya está en su locale. */
function formatear(valor, decimales) {
    return valor.toLocaleString(undefined, {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales,
    })
}

function animarContadores(raiz) {
    raiz.querySelectorAll('[data-srg-contador]').forEach((el) => {
        if (el.dataset.srgAnimado === '1') return
        el.dataset.srgAnimado = '1'
        animarContador(el)
    })
}

// Filament y Livewire reemplazan el DOM en vez de recargarlo, así que los
// contadores nuevos aparecen después. Un MutationObserver los engancha sin
// tener que acordarse de llamarlos a mano en cada recarga.
const observador = new MutationObserver((mutaciones) => {
    for (const mutacion of mutaciones) {
        for (const nodo of mutacion.addedNodes) {
            if (!(nodo instanceof Element)) continue
            animarContadores(nodo)
        }
    }
})

function iniciar() {
    animarContadores(document)

    if (observador.isConnected === false) {
        observador.observe(document.body, { childList: true, subtree: true })
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar)
} else {
    iniciar()
}

// Livewire navega sin recargar; el observer no lo nota porque no cambia el body.
document.addEventListener('livewire:navigated', iniciar)