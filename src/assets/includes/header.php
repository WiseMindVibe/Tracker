<!DOCTYPE html>
<html lang="en">
<script>
(function () {
    try {
        var k = 'tracker-theme';
        var s = localStorage.getItem(k);
        if (s === 'light' || s === 'dark') {
            document.documentElement.setAttribute('data-theme', s);
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (e) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
})();
</script>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="./src/assets/includes/topbar/topbar.css">
    <link rel="stylesheet" href="./src/assets/includes/topbar/theme.css">
</head>

<body>

    <?php
    include_once __DIR__ . "/topbar/topbar.php";
    ?>
    <script src="./src/assets/includes/topbar/theme-toggle.js" defer></script>