</div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.datatables.net/v/bs5/dt-2.0.7/b-3.0.2/fh-4.0.1/r-3.0.3/datatables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.10/build/pdfmake.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.10/build/vfs_fonts.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    
    <!-- Script básico para DataTables -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar DataTables
        const tables = document.querySelectorAll('table.data-table');
        tables.forEach(table => {
            if ($.fn.DataTable.isDataTable(table)) {
                return; // Ya está inicializada
            }
            
            try {
                new DataTable(table, {
                    responsive: true,
                    fixedHeader: true,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                    },
                    layout: {
                        topStart: {
                            buttons: ['copy', 'csv', 'excel', 'pdf', 'print']
                        }
                    }
                });
            } catch (e) {
                console.log('Error inicializando DataTable:', e);
            }
        });
        
        // Tooltips de Bootstrap
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
    
    // Función global para mostrar notificaciones
    function showNotification(type, title, message) {
        Swal.fire({
            icon: type,
            title: title,
            text: message,
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
    }
    
    // Función para confirmar eliminaciones
    function confirmDelete(callback, message = '¿Estás seguro de que quieres eliminar este elemento?') {
        Swal.fire({
            title: '¿Confirmar eliminación?',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed && callback) {
                callback();
            }
        });
    }
    </script>
</body>
</html>