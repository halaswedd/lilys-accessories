<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$stats = [
    'Total Products'     => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'Available Products' => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_available = 1')->fetchColumn(),
    'Not Available'      => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_available = 0')->fetchColumn(),
    'Best Sellers'       => (int) $pdo->query('SELECT COUNT(*) FROM products WHERE is_best_seller = 1')->fetchColumn(),
    'Active Offers'      => (int) $pdo->query('SELECT COUNT(*) FROM offers o WHERE ' . ACTIVE_OFFER_SQL)->fetchColumn(),
];

$pageTitle = 'Dashboard';
$active    = 'dashboard';
require __DIR__ . '/includes/layout_top.php';
?>

<h1>Dashboard</h1>

<div class="grid">
    <?php foreach ($stats as $label => $value): ?>
        <div class="card stat"><b><?= $value ?></b><?= e($label) ?></div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>