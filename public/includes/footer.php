<?php
// public/includes/footer.php - Shared footer
?>
<!-- ====== FOOTER SCRIPTS ====== -->

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart.js (for charts) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- Settings JS -->
<script src="<?php echo BASE_URL; ?>assets/js/settings.js"></script>

<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>assets/js/script.js"></script>

<!-- ====== AUTO-CLOSE ALERTS ====== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const closeBtn = alert.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.click();
            } else {
                alert.style.display = 'none';
            }
        }, 5000);
    });
});
</script>

</body>
</html>