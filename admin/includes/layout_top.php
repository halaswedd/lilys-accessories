<?php
// Expects: $pageTitle (string), $active (string: dashboard|categories|products|packages|offers)
$pageTitle = $pageTitle ?? 'Admin';
$active    = $active ?? '';
$nav = [
    'dashboard'  => ['Dashboard',  'index.php'],
    'products'   => ['Products',   'products.php'],
    'offers'     => ['Offers',     'offers.php'],
    'packages'   => ['Packages',   'packages.php'],
    'categories' => ['Categories', 'categories.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(SITE_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>"></a>
    <nav>
        <?php foreach ($nav as $key => [$label, $href]): ?>
            <a href="<?= $href ?>" class="<?= $active === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
        <a href="logout.php" class="logout">Logout</a>
    </nav>
</header>
<main class="container">