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

if (typeof document !== 'undefined') {
    document.querySelectorAll('.sidebar-brand[aria-controls]').forEach((toggle) => {
        const sidebar = toggle.closest('.sidebar');
        const navigation = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!sidebar || !navigation) return;

        const mobileQuery = window.matchMedia('(max-width: 760px)');
        const syncNavigation = () => {
            toggle.setAttribute('aria-expanded', String(!mobileQuery.matches || sidebar.classList.contains('sidebar-open')));
        };

        toggle.addEventListener('click', () => {
            if (!mobileQuery.matches) return;
            const isOpen = sidebar.classList.toggle('sidebar-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
        });

        mobileQuery.addEventListener('change', () => {
            sidebar.classList.remove('sidebar-open');
            syncNavigation();
        });
        syncNavigation();
    });
}
