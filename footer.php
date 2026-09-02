<!-- footer.php -->
<!-- Bootstrap 5 Bundle JS with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Dynamic Custom Scripts -->
<?php if (isset($page_scripts) && is_array($page_scripts)): ?>
    <?php foreach ($page_scripts as $script): ?>
        <script src="<?php echo htmlspecialchars($script); ?>"></script>
    <?php endforeach; ?>
<?php elseif (isset($page_js)): ?>
    <script src="<?php echo htmlspecialchars($page_js); ?>"></script>
<?php endif; ?>
</body>
</html>
