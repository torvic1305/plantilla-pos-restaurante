// Utilidades compartidas de la aplicación.
function mostrarMensaje(mensaje) {
    if (typeof window !== 'undefined' && window.alert) {
        window.alert(mensaje);
    } else {
        console.log(mensaje);
    }
}

function safeFetchJson(url, options = {}) {
    return fetch(url, options).then(response => {
        if (!response.ok) {
            throw new Error('Error en la petición: ' + response.status);
        }
        return response.json();
    });
}

function toggleLoading(button, isLoading) {
    if (!button) return;
    if (isLoading) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Cargando...';
    } else {
        button.disabled = false;
        button.textContent = button.dataset.originalText || button.textContent;
    }
}
