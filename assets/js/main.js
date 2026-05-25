/**
 * assets/js/main.js
 * Funcionalidad JavaScript del lado del cliente para el Colegio Futuro Digital
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. Búsqueda Reactiva en Tablas
    const searchInput = document.getElementById('tableSearch');
    if (searchInput) {
        const tableBody = document.querySelector('.table-responsive tbody');
        if (tableBody) {
            const rows = tableBody.getElementsByTagName('tr');
            
            searchInput.addEventListener('keyup', function(e) {
                const query = e.target.value.toLowerCase();
                
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    // Ignorar la fila si es un mensaje de "no se encontraron resultados"
                    if (row.classList.contains('no-results')) continue;

                    let rowText = row.textContent.toLowerCase();
                    if (rowText.indexOf(query) > -1) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
        }
    }

    // 2. Auto-desvanecimiento de alertas después de 4 segundos
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 4000);
    });

    // 3. Validador genérico de formularios Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

/**
 * Función auxiliar para confirmar eliminaciones de registros
 * @param {string} mensaje 
 * @returns {boolean}
 */
function confirmarEliminacion(mensaje = '¿Está seguro de que desea eliminar este registro? Esta acción no se puede deshacer.') {
    return confirm(mensaje);
}
