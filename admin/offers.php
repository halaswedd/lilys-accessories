<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$ok  = $_SESSION['flash_ok'] ?? '';
$err = '';
unset($_SESSION['flash_ok']);

$form = null;   // when set, the add/edit form is shown

// "2026-10-05T18:30" (from the browser) -> "2026-10-05 18:30:00", or null
function parse_dt(?string $v): ?string {
    $v = trim((string) $v);
    if ($v === '') return null;
    $t = strtotime($v);
    return $t ? date('Y-m-d H:i:s', $t) : null;
}

// =========================================
// Handle actions
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            case 'save_offer':
                $id    = (int) ($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $desc  = trim($_POST['description'] ?? '');
                $type  = in_array($_POST['discount_type'] ?? '', ['fixed', 'bogo'], true) ? $_POST['discount_type'] : 'percentage';
                $value = (float) ($_POST['discount_value'] ?? 0);
                $buy   = max(1, min(10, (int) ($_POST['buy_qty'] ?? 1)));
                $get   = max(1, min(10, (int) ($_POST['get_qty'] ?? 1)));
                if ($type === 'bogo') { $value = 0; } else { $buy = 1; $get = 1; }
                $start = parse_dt($_POST['start_date'] ?? '');
                $end   = parse_dt($_POST['end_date'] ?? '');
                $activeFlag = isset($_POST['is_active']) ? 1 : 0;
                $productIds = array_values(array_unique(array_filter(array_map('intval', $_POST['products'] ?? []))));

                if ($title === '') throw new Exception('Offer title is required.');
                if ($type !== 'bogo' && $value <= 0) throw new Exception('Discount value must be more than 0.');
                if ($type === 'percentage' && $value > 100) throw new Exception('A percentage discount cannot be more than 100.');
                if ($start && $end && strtotime($end) < strtotime($start)) {
                    throw new Exception('The end date must be after the start date.');
                }
                if (!$productIds) throw new Exception('Choose at least one product for this offer.');

                $oldImage = null;
                if ($id) {
                    $o = $pdo->prepare('SELECT image FROM offers WHERE id = ?');
                    $o->execute([$id]);
                    $oldImage = $o->fetchColumn() ?: null;
                }
                $newImage = upload_image($_FILES['image'] ?? [], 'offers');
                $image    = $newImage ?: $oldImage;

                if ($id) {
                    $pdo->prepare('UPDATE offers SET title=?, description=?, discount_type=?, discount_value=?, buy_qty=?, get_qty=?, image=?, start_date=?, end_date=?, is_active=? WHERE id=?')
                        ->execute([$title, $desc ?: null, $type, $value, $buy, $get, $image, $start, $end, $activeFlag, $id]);
                    if ($newImage) delete_upload($oldImage, 'offers');
                } else {
                    $pdo->prepare('INSERT INTO offers (title, description, discount_type, discount_value, buy_qty, get_qty, image, start_date, end_date, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$title, $desc ?: null, $type, $value, $buy, $get, $image, $start, $end, $activeFlag]);
                    $id = (int) $pdo->lastInsertId();
                }

                // Products in this offer: replace all
                $pdo->prepare('DELETE FROM offer_products WHERE offer_id = ?')->execute([$id]);
                $ins = $pdo->prepare('INSERT IGNORE INTO offer_products (offer_id, product_id)
                                      SELECT ?, id FROM products WHERE id = ?');
                foreach ($productIds as $pid) {
                    $ins->execute([$id, $pid]);
                }

                $_SESSION['flash_ok'] = 'Offer saved.';
                header('Location: offers.php');
                exit;

            case 'toggle_offer':
                $pdo->prepare('UPDATE offers SET is_active = 1 - is_active WHERE id = ?')
                    ->execute([(int) ($_POST['id'] ?? 0)]);
                header('Location: offers.php');
                exit;

            case 'delete_offer':
                $id = (int) ($_POST['id'] ?? 0);
                $o = $pdo->prepare('SELECT image FROM offers WHERE id = ?');
                $o->execute([$id]);
                delete_upload($o->fetchColumn() ?: null, 'offers');
                $pdo->prepare('DELETE FROM offers WHERE id = ?')->execute([$id]);
                $_SESSION['flash_ok'] = 'Offer deleted.';
                header('Location: offers.php');
                exit;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $ex) {
        $err = $ex->getMessage();
        if (($action ?? '') === 'save_offer') {
            // Re-show the form with what the user typed
            $form = [
                'id' => (int) ($_POST['id'] ?? 0),
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'discount_type' => $_POST['discount_type'] ?? 'percentage',
                'discount_value' => $_POST['discount_value'] ?? '',
                'buy_qty' => (int) ($_POST['buy_qty'] ?? 1),
                'get_qty' => (int) ($_POST['get_qty'] ?? 1),
                'image' => null,
                'start_date' => $_POST['start_date'] ?? '',
                'end_date' => $_POST['end_date'] ?? '',
                'is_active' => isset($_POST['is_active']) ? 1 : 0,
                'products' => array_map('intval', $_POST['products'] ?? []),
            ];
        }
    }
}

// =========================================
// Load data
// =========================================
if ($form === null && isset($_GET['new'])) {
    $form = [
        'id' => 0, 'title' => '', 'description' => '', 'discount_type' => 'percentage',
        'discount_value' => '', 'buy_qty' => 1, 'get_qty' => 1, 'image' => null,
        'start_date' => '', 'end_date' => '', 'is_active' => 1, 'products' => [],
    ];
}
if ($form === null && isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM offers WHERE id = ?');
    $q->execute([(int) $_GET['edit']]);
    $row = $q->fetch();
    if ($row) {
        $p = $pdo->prepare('SELECT product_id FROM offer_products WHERE offer_id = ?');
        $p->execute([$row['id']]);
        $row['products']   = array_map('intval', $p->fetchAll(PDO::FETCH_COLUMN));
        $row['start_date'] = $row['start_date'] ? date('Y-m-d\TH:i', strtotime($row['start_date'])) : '';
        $row['end_date']   = $row['end_date']   ? date('Y-m-d\TH:i', strtotime($row['end_date']))   : '';
        $form = $row;
    }
}

$offers = [];
$productsByCat = [];
if ($form === null) {
    $offers = $pdo->query(
        'SELECT o.*, COUNT(op.id) AS product_count
         FROM offers o LEFT JOIN offer_products op ON op.offer_id = o.id
         GROUP BY o.id ORDER BY o.created_at DESC'
    )->fetchAll();
} else {
    $rows = $pdo->query(
        'SELECT p.id, p.name, c.name AS category_name
         FROM products p JOIN categories c ON c.id = p.category_id
         ORDER BY c.sort_order, c.id, p.name'
    )->fetchAll();
    foreach ($rows as $r) $productsByCat[$r['category_name']][] = $r;
}

function offer_status(array $o): array {
    $now = time();
    if (!$o['is_active'])                                       return ['Disabled', 'off'];
    if ($o['start_date'] && strtotime($o['start_date']) > $now) return ['Scheduled', 'off'];
    if ($o['end_date'] && strtotime($o['end_date']) < $now)     return ['Expired', 'off'];
    if ((int) $o['product_count'] === 0)                        return ['No products', 'off'];
    return ['Live', 'on'];
}

$pageTitle = 'Offers';
$active    = 'offers';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($ok):  ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<?php if ($form !== null): ?>
<!-- ================= ADD / EDIT FORM ================= -->

<h1><?= $form['id'] ? 'Edit offer' : 'Create offer' ?></h1>

<form method="post" enctype="multipart/form-data" class="card">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_offer">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

    <label>Title</label>
    <input type="text" name="title" value="<?= e($form['title']) ?>" placeholder="e.g. WEEKEND SPECIAL" required>

    <label>Short description (optional)</label>
    <input type="text" name="description" value="<?= e($form['description']) ?>" placeholder="e.g. Selected pieces for 2 days only">

    <label>Offer type</label>
    <select name="discount_type" id="offerType">
        <option value="percentage" <?= $form['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage off (%)</option>
        <option value="fixed" <?= $form['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed amount off ($)</option>
        <option value="bogo" <?= $form['discount_type'] === 'bogo' ? 'selected' : '' ?>>Buy X Get Y free (e.g. Buy 1 Get 1)</option>
    </select>

    <div id="valueRow">
        <label>Discount value</label>
        <input type="number" name="discount_value" step="0.01" min="0" value="<?= e($form['discount_value']) ?>" placeholder="20">
    </div>

    <div class="row" id="bogoRow" hidden>
        <div>
            <label>Customer buys</label>
            <input type="number" name="buy_qty" min="1" max="10" value="<?= (int) ($form['buy_qty'] ?? 1) ?>">
        </div>
        <div>
            <label>Gets free</label>
            <input type="number" name="get_qty" min="1" max="10" value="<?= (int) ($form['get_qty'] ?? 1) ?>">
        </div>
    </div>
    <div class="muted" id="bogoHint" hidden>Works on each product separately: with Buy 1 Get 1, adding 2 of the same piece makes one of them free.</div>

    <div class="row">
        <div>
            <label>Starts (optional)</label>
            <input type="datetime-local" name="start_date" value="<?= e($form['start_date']) ?>">
        </div>
        <div>
            <label>Ends (optional)</label>
            <input type="datetime-local" name="end_date" value="<?= e($form['end_date']) ?>">
        </div>
    </div>
    <div class="muted">Leave dates empty to run the offer until you turn it off.</div>

    <label>Banner image (optional)</label>
    <?php if (!empty($form['image'])): ?>
        <img class="thumb" src="<?= UPLOAD_URL ?>offers/<?= e($form['image']) ?>" alt="" style="margin-bottom:8px">
    <?php endif; ?>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">

    <label class="check"><input type="checkbox" name="is_active" <?= $form['is_active'] ? 'checked' : '' ?>> Offer is on</label>

    <label>Products in this offer</label>
    <?php if (!$productsByCat): ?>
        <div class="muted">No products yet. Add products first.</div>
    <?php endif; ?>
    <?php foreach ($productsByCat as $catName => $list): ?>
        <div class="offer-group">
            <div class="offer-group-head">
                <strong><?= e($catName) ?></strong>
                <button type="button" class="btn light small js-toggle-group">Select all</button>
            </div>
            <?php foreach ($list as $p): ?>
                <label class="check">
                    <input type="checkbox" name="products[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $form['products'], true) ? 'checked' : '' ?>>
                    <?= e($p['name']) ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="actions">
        <button class="btn" type="submit">Save offer</button>
        <a class="btn light" href="offers.php">Cancel</a>
    </div>
</form>

<?php if ($form['id']): ?>
<form method="post" onsubmit="return confirm('Delete this offer permanently?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_offer">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <button class="btn danger small" type="submit">Delete offer</button>
</form>
<?php endif; ?>

<script>
(function () {
    const type = document.getElementById('offerType');
    function sync() {
        const bogo = type.value === 'bogo';
        document.getElementById('valueRow').hidden = bogo;
        document.getElementById('bogoRow').hidden = !bogo;
        document.getElementById('bogoHint').hidden = !bogo;
    }
    type.addEventListener('change', sync);
    sync();
})();
document.querySelectorAll('.js-toggle-group').forEach(btn => {
    btn.addEventListener('click', () => {
        const boxes = btn.closest('.offer-group').querySelectorAll('input[type=checkbox]');
        const allOn = Array.from(boxes).every(b => b.checked);
        boxes.forEach(b => b.checked = !allOn);
        btn.textContent = allOn ? 'Select all' : 'Unselect all';
    });
});
</script>

<?php else: ?>
<!-- ================= LIST ================= -->

<div class="row" style="align-items:center;margin-bottom:16px">
    <h1 style="margin:0">Offers</h1>
    <div class="shrink"><a class="btn" href="offers.php?new=1">+ Create offer</a></div>
</div>

<?php if (!$offers): ?>
    <div class="card muted">No offers yet. The Offers section stays hidden on the website until you create one.</div>
<?php endif; ?>

<?php foreach ($offers as $o): ?>
    <?php [$statusText, $statusClass] = offer_status($o); ?>
    <div class="card">
        <div class="cat-head">
            <?php if ($o['image']): ?>
                <img src="<?= UPLOAD_URL ?>offers/<?= e($o['image']) ?>" alt="">
            <?php else: ?>
                <div class="thumb empty">%</div>
            <?php endif; ?>
            <div class="cat-title">
                <strong><?= e($o['title']) ?></strong>
                <span class="badge <?= $statusClass ?>"><?= $statusText ?></span>
                <div class="muted">
                    <?= e(offer_label($o)) ?> · <?= (int) $o['product_count'] ?> product<?= (int) $o['product_count'] === 1 ? '' : 's' ?>
                    <?php if ($o['start_date'] || $o['end_date']): ?>
                        <br><?= $o['start_date'] ? date('d M Y H:i', strtotime($o['start_date'])) : 'now' ?>
                        → <?= $o['end_date'] ? date('d M Y H:i', strtotime($o['end_date'])) : 'no end' ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="actions">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_offer">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button class="btn small <?= $o['is_active'] ? '' : 'light' ?>" type="submit">
                    <?= $o['is_active'] ? '🟢 On' : '🔴 Off' ?>
                </button>
            </form>
            <a class="btn light small" href="offers.php?edit=<?= (int) $o['id'] ?>">Edit</a>
        </div>
    </div>
<?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>