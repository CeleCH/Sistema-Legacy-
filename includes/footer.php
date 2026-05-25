<?php
// includes/footer.php
$is_module_dir = (strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false);
$base_path = $is_module_dir ? '../' : './';
?>
    </div> <!-- .container-fluid -->
</main> <!-- .content-body -->
</div> <!-- .wrapper -->

<!-- Footer Institucional -->
<footer class="footer bg-light border-top py-3 text-center text-muted">
    <div class="container-fluid">
        <span class="fs-8">&copy; 2026 Colegio Futuro Digital - Sistema de Gestión Académica Monolítico. Todos los derechos reservados.</span>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js para estadísticas -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Custom JavaScript -->
<script src="<?php echo $base_path; ?>assets/js/main.js"></script>
</body>
</html>
