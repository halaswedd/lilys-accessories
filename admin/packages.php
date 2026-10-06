<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$ok  = $_SESSION['flash_ok'] ?? '';
$err = '';
unset($_SESSION['flash_ok']);

$form = null;   // when set, the create/edit form is shown

// =========================================
// Handle actions
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            case 'save_package':
                $id          = (int) ($_POST['id'] ?? 0);
                $name        = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price       = (float) ($_POST['price'] ?? 0);
                $activeFlag  = isset($_POST['is_active']) ? 1 : 0;
                $productIds  = array_values(array_unique(array_filter(array_map('intval', $_POST['products'] ?? []))));
                $qtyPost     = $_POST['qty'] ?? [];
                $qtyOf       = function ($pid) use ($qtyPost) { return max(1, min(10, (int) ($qtyPost[$pid] ?? 1))); };
                $units       = 0;
                foreach ($productIds as $pid) $units += $qtyOf($pid);

                if ($name === '')        throw new Exception('Package name is required.');
                if ($price <= 0)         throw new Exception('Package price must be more than 0.');
                if ($units < 2)          throw new Exception('A package needs at least 2 items (you can pick the same product twice using the quantity box).');

                $oldImage = null;
                if ($id) {
                    $o = $pdo->prepare('SELECT image FROM packages WHERE id = ?');
                    $o->execute([$id]);
                    $oldImage = $o->fetchColumn() ?: null;
                }
                $newImage = upload_image($_FILES['image'] ?? [], 'packages');
                $image    = $newImage ?: $oldImage;

                if ($id) {
                    $pdo->prepare('UPDATE packages SET name=?, description=?, price=?, image=?, is_active=? WHERE id=?')
                        ->execute([$name, $description ?: null, $price, $image, $activeFlag, $id]);
                    if ($newImage) delete_upload($oldImage, 'packages');
                } else {
                    $pdo->prepare('INSERT INTO packages (name, description, price, image, is_active) VALUES (?,?,?,?,?)')
                        ->execute([$name, $description ?: null, $price, $image, $activeFlag]);
                    $id = (int) $pdo->lastInsertId();
                }

                // Products in the package: replace all
                $pdo->prepare('DELETE FROM package_products WHERE package_id = ?')->execute([$id]);
                $ins = $pdo->prepare('INSERT IGNORE INTO package_products (package_id, product_id, qty)
                                      SELECT ?, id, ? FROM products WHERE id = ?');
                foreach ($productIds as $pid) {
                    $ins->execute([$id, $qtyOf($pid), $pid]);
                }

                $_SESSION['flash_ok'] = 'Package saved.';
                header('Location: packages.php');
                exit;

            case 'toggle_package':
                $pdo->prepare('UPDATE packages SET is_active = 1 - is_active WHERE id = ?')
                    ->execute([(int) ($_POST['id'] ?? 0)]);
                header('Location: packages.php');
                exit;

            case 'delete_package':
                $id = (int) ($_POST['id'] ?? 0);
                $o = $pdo->prepare('SELECT image FROM packages WHERE id = ?');
                $o->execute([$id]);
                delete_upload($o->fetchColumn() ?: null, 'packages');
                $pdo->prepare('DELETE FROM packages WHERE id = ?')->execute([$id]);
                $_SESSION['flash_ok'] = 'Package deleted.';
                header('Location: packages.php');
                exit;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $ex) {
        $err = $ex->getMessage();
        if (($action ?? '') === 'save_package') {
            // Re-show the form with what was typed
            $form = [
                'id' => (int) ($_POST['id'] ?? 0),
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'price' => $_POST['price'] ?? '',
                'image' => null,
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'products' => array_map('intval', $_POST['products'] ?? []),
                'qty' => array_map('intval', $_POST['qty'] ?? []),
            ];
        }
    }
}

// =========================================
// Load data
// =========================================
if ($form === null && isset($_GET['new'])) {
    $form = ['id' => 0, 'name' => '', 'description' => '', 'price' => '', 'image' => null, 'is_active' => 1, 'products' => [], 'qty' => []];
}
if ($form === null && isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM packages WHERE id = ?');
    $q->execute([(int) $_GET['edit']]);
    $row = $q->fetch();
    if ($row) {
        $p = $pdo->prepare('SELECT product_id, qty FROM package_products WHERE package_id = ?');
        $p->execute([$row['id']]);
        $row['products'] = [];
        $row['qty'] = [];
        foreach ($p->fetchAll() as $pr) {
            $row['products'][]            = (int) $pr['product_id'];
            $row['qty'][(int) $pr['product_id']] = (int) $pr['qty'];
        }
        $form = $row;
    }
}

$packages = [];
$productsByCat = [];
if ($form === null) {
    $packages = $pdo->query(
        'SELECT k.*,
                COALESCE(SUM(pp.qty), 0) AS product_count,
                COALESCE(SUM(pr.price * pp.qty), 0) AS regular
         FROM packages k
         LEFT JOIN package_products pp ON pp.package_id = k.id
         LEFT JOIN products pr ON pr.id = pp.product_id
         GROUP BY k.id
         ORDER BY k.created_at DESC, k.id DESC'
    )->fetchAll();
} else {
    $rows = $pdo->query(
        'SELECT p.id, p.name, p.price, c.name AS category_name
         FROM products p JOIN categories c ON c.id = p.category_id
         ORDER BY c.sort_order, c.id, p.name'
    )->fetchAll();
    foreach ($rows as $r) $productsByCat[$r['category_name']][] = $r;
}

$pageTitle = 'Packages';
$active    = 'packages';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($ok):  ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<?php if ($form !== null): ?>
<!-- ================= CREATE / EDIT FORM ================= -->

<h1><?= $form['id'] ? 'Edit package' : 'Create new package' ?></h1>

<form method="post" enctype="multipart/form-data" class="card" id="packageForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_package">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

    <label>Package name</label>
    <input type="text" name="name" value="<?= e($form['name']) ?>" placeholder="e.g. Summer Glow Set" required>

    <label>Description</label>
    <textarea name="description" rows="3" placeholder="e.g. Perfect combination for your summer look"><?= e($form['description']) ?></textarea>

    <label>Choose products <span class="muted">(use the &times; box if the package has the same product more than once)</span></label>
    <?php if (!$productsByCat): ?>
        <div class="muted">No products yet. Add products first.</div>
    <?php endif; ?>
    <?php foreach ($productsByCat as $catName => $list): ?>
        <div class="offer-group">
            <div class="offer-group-head"><strong><?= e($catName) ?></strong></div>
            <?php foreach ($list as $p): ?>
                <div class="pk-pick">
                    <label class="check">
                        <input type="checkbox" name="products[]" value="<?= (int) $p['id'] ?>" data-price="<?= e($p['price']) ?>"
                               <?= in_array((int) $p['id'], $form['products'], true) ? 'checked' : '' ?>>
                        <?= e($p['name']) ?> <span class="muted">· $<?= e(number_format((float) $p['price'], 2)) ?></span>
                    </label>
                    <span class="pk-qty-wrap">&times;
                        <input type="number" class="pk-qty" name="qty[<?= (int) $p['id'] ?>]" min="1" max="10"
                               value="<?= max(1, (int) ($form['qty'][(int) $p['id']] ?? 1)) ?>" aria-label="Quantity of <?= e($p['name']) ?> in the package">
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <label>Package price ($)</label>
    <input type="number" name="price" id="pkgPrice" step="0.01" min="0" value="<?= e($form['price']) ?>" placeholder="25" required>
    <div class="pkg-summary" id="pkgSummary">Select products to see their total.</div>

    <label>Package image (optional)</label>
    <?php if (!empty($form['image'])): ?>
        <img class="thumb" src="<?= UPLOAD_URL ?>packages/<?= e($form['image']) ?>" alt="" style="margin-bottom:8px">
    <?php endif; ?>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
    <div class="muted">Cropped to a square automatically. Without an image, the first product's photo is used.</div>

    <label class="check"><input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>> Show on the website</label>

    <div class="actions">
        <button class="btn" type="submit">Save package</button>
        <a class="btn light" href="packages.php">Cancel</a>
    </div>
</form>

<script>
(function () {
    const boxes = Array.from(document.querySelectorAll('input[name="products[]"]'));
    const price = document.getElementById('pkgPrice');
    const out   = document.getElementById('pkgSummary');
    const fmt = n => '$' + (Math.round(n * 100) / 100).toFixed(2).replace(/\.00$/, '');
    const qtyOf = b => b.closest('.pk-pick').querySelector('.pk-qty');

    function update() {
        let units = 0, total = 0;
        boxes.forEach(b => {
            const q = qtyOf(b);
            q.disabled = !b.checked;
            if (b.checked) {
                const n = Math.max(1, Math.min(10, parseInt(q.value, 10) || 1));
                units += n;
                total += n * parseFloat(b.dataset.price || 0);
            }
        });
        const p = parseFloat(price.value || 0);
        if (!units) { out.textContent = 'Select products to see their total.'; return; }
        let txt = units + ' item' + (units === 1 ? '' : 's') + ' \u00B7 bought separately: ' + fmt(total);
        if (p > 0) {
            txt += total > p ? ' \u00B7 customers save ' + fmt(total - p)
                 : total === p ? ' \u00B7 same as the separate price'
                 : ' \u00B7 the package costs MORE than buying them separately';
        }
        out.textContent = txt;
    }
    boxes.forEach(b => { b.addEventListener('change', update); qtyOf(b).addEventListener('input', update); });
    price.addEventListener('input', update);
    update();
})();
</script>

<?php else: ?>
<!-- ================= LIST ================= -->

<div class="row" style="align-items:center;margin-bottom:16px">
    <h1 style="margin:0">Packages</h1>
    <div class="shrink"><a class="btn" href="packages.php?new=1">+ Create Package</a></div>
</div>

<?php if (!$packages): ?>
    <div class="card muted">No packages yet. A package is a set of products sold together at one special price.</div>
<?php endif; ?>

<?php foreach ($packages as $k): ?>
    <div class="card">
        <div class="cat-head">
            <?php if ($k['image']): ?>
                <img src="<?= UPLOAD_URL ?>packages/<?= e($k['image']) ?>" alt="">
            <?php else: ?>
                <div class="thumb empty">✨</div>
            <?php endif; ?>
            <div class="cat-title">
                <strong><?= e($k['name']) ?></strong>
                <span class="badge <?= $k['is_active'] ? 'on' : 'off' ?>"><?= $k['is_active'] ? 'Live' : 'Hidden' ?></span>
                <div class="muted">
                    <?= (int) $k['product_count'] ?> Product<?= (int) $k['product_count'] === 1 ? '' : 's' ?> | $<?= e(number_format((float) $k['price'], 2)) ?>
                    <?php if ((float) $k['regular'] > (float) $k['price']): ?>
                        <br>Separately $<?= e(number_format((float) $k['regular'], 2)) ?> · save $<?= e(number_format((float) $k['regular'] - (float) $k['price'], 2)) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="actions">
            <a class="btn light small" href="packages.php?edit=<?= (int) $k['id'] ?>">Edit</a>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_package">
                <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                <button class="btn small <?= $k['is_active'] ? '' : 'light' ?>" type="submit"><?= $k['is_active'] ? '🟢 On' : '🔴 Off' ?></button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this package permanently?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_package">
                <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                <button class="btn danger small" type="submit">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($packages): ?>
    <div style="text-align:center;margin-top:8px"><a class="btn" href="packages.php?new=1">+ Create Package</a></div>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>