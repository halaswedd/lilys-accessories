<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$ok  = $_SESSION['flash_ok'] ?? '';
$err = '';
unset($_SESSION['flash_ok']);

$fields = [
    'hero_tagline'     => ['Homepage phrase',   'Little details, big sparkle', 'Shown under the logo on the homepage.'],
    'instagram'        => ['Instagram',         'username or full link',       ''],
    'phone'            => ['Phone (optional)',  '',                            ''],
    'address'          => ['Address (optional)', '',                           ''],
    'delivery_message' => ['Delivery message',  'Delivery available all over Lebanon', 'Shown in the footer.'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($fields as $key => $meta) {
        $val = trim($_POST[$key] ?? '');
        $stmt->execute([$key, $val]);
    }
    $_SESSION['flash_ok'] = 'Settings saved.';
    header('Location: settings.php');
    exit;
}

$settings  = load_settings($pdo);
$pageTitle = 'Settings';
$active    = 'settings';
require __DIR__ . '/includes/layout_top.php';
?>

<h1>Settings</h1>

<?php if ($ok):  ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<form method="post" class="card">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => [$label, $placeholder, $hint]): ?>
        <label><?= e($label) ?></label>
        <input type="text" name="<?= e($key) ?>" value="<?= e($settings[$key] ?? '') ?>" placeholder="<?= e($placeholder) ?>">
        <?php if ($hint): ?><div class="muted"><?= e($hint) ?></div><?php endif; ?>
    <?php endforeach; ?>
    <div class="actions"><button class="btn" type="submit">Save settings</button></div>
</form>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>