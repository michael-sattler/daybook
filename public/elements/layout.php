<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/pagehead.php'; ?>
<body>
<?php include __DIR__ . '/topbar.php'; ?>
<?= $content ?>
<script src="/assets/js/topbar.js?v=<?= filemtime(__DIR__ . '/../assets/js/topbar.js') ?>"></script>
<?= $pageScripts ?? '' ?>
</body>
</html>
