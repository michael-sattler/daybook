<!DOCTYPE html>
<html lang="en">
<?php include __DIR__ . '/pagehead.php'; ?>
<body>
<?php include __DIR__ . '/topbar.php'; ?>
<?= $content ?>
<?= $pageScripts ?? '' ?>
</body>
</html>
