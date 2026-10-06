<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$ok   = $_SESSION['flash_ok'] ?? '';
$err  = '';
unset($_SESSION['flash_ok']);

$form = null;   // when set, the add/edit form is shown

// =========================================
// Handle actions
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            case 'save_product':
                $id          = (int) ($_POST['id'] ?? 0);
                $name        = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $price       = (float) ($_POST['price'] ?? 0);
                $categoryId  = (int) ($_POST['category_id'] ?? 0);
                $subId       = (int) ($_POST['subcategory_id'] ?? 0);
                $available   = isset($_POST['is_available']) ? 1 : 0;
                $best        = isset($_POST['is_best_seller']) ? 1 : 0;

                if ($name === '')  throw new Exception('Product name is required.');
                if ($price < 0)    throw new Exception('Price cannot be negative.');

                $c = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
                $c->execute([$categoryId]);
                if (!$c->fetchColumn()) throw new Exception('Please choose a category.');

                // Subcategory must belong to the chosen category
                $subValue = null;
                if ($subId) {
                    $s = $pdo->prepare('SELECT COUNT(*) FROM subcategories WHERE id = ? AND category_id = ?');
                    $s->execute([$subId, $categoryId]);
                    if ($s->fetchColumn()) $subValue = $subId;
                }

                // Image
                $oldImage = null;
                if ($id) {
                    $o = $pdo->prepare('SELECT image FROM products WHERE id = ?');
                    $o->execute([$id]);
                    $oldImage = $o->fetchColumn() ?: null;
                }
                $newImage = upload_image($_FILES['image'] ?? [], 'products');
                $image    = $newImage ?: $oldImage;

                // ----- Colors: just a name + an optional photo for each color -----
                $existingColorImgs = [];
                if ($id) {
                    $q = $pdo->prepare('SELECT image FROM product_colors WHERE product_id = ? AND image IS NOT NULL');
                    $q->execute([$id]);
                    $existingColorImgs = $q->fetchAll(PDO::FETCH_COLUMN);
                }
                $colorRows     = [];
                $newColorFiles = [];
                try {
                    $cNames  = $_POST['color_name'] ?? [];
                    $cOld    = $_POST['color_old_image'] ?? [];
                    $mainIdx = isset($_POST['main_color']) ? (int) $_POST['main_color'] : -1;
                    $cFiles = $_FILES['color_image'] ?? null;
                    foreach ($cNames as $i => $cn) {
                        $cn = trim($cn);
                        if ($cn === '') continue;
                        $file = [];
                        if ($cFiles && isset($cFiles['name'][$i])) {
                            $file = [
                                'name' => $cFiles['name'][$i], 'type' => $cFiles['type'][$i],
                                'tmp_name' => $cFiles['tmp_name'][$i], 'error' => $cFiles['error'][$i],
                                'size' => $cFiles['size'][$i],
                            ];
                        }
                        $img = upload_image($file, 'colors');
                        if ($img) $newColorFiles[] = $img;
                        $old = basename((string) ($cOld[$i] ?? ''));
                        if (!$img && $old !== '' && in_array($old, $existingColorImgs, true)) $img = $old;
                        $colorRows[] = ['name' => $cn, 'image' => $img ?: null, 'main' => ((int) $i === $mainIdx) ? 1 : 0];
                    }
                    // if no color was marked as main, the first one is
                    if ($colorRows && !array_sum(array_column($colorRows, 'main'))) $colorRows[0]['main'] = 1;
                } catch (Exception $colorEx) {
                    foreach ($newColorFiles as $f) delete_upload($f, 'colors');
                    delete_upload($newImage, 'products');
                    throw $colorEx;
                }

                if ($id) {
                    $pdo->prepare('UPDATE products SET category_id=?, subcategory_id=?, name=?, description=?, price=?, image=?, is_available=?, is_best_seller=? WHERE id=?')
                        ->execute([$categoryId, $subValue, $name, $description ?: null, $price, $image, $available, $best, $id]);
                    if ($newImage) delete_upload($oldImage, 'products');
                } else {
                    $pdo->prepare('INSERT INTO products (category_id, subcategory_id, name, description, price, image, is_available, is_best_seller) VALUES (?,?,?,?,?,?,?,?)')
                        ->execute([$categoryId, $subValue, $name, $description ?: null, $price, $image, $available, $best]);
                    $id = (int) $pdo->lastInsertId();
                }

                // Colors: replace all
                $pdo->prepare('DELETE FROM product_colors WHERE product_id = ?')->execute([$id]);
                $ins = $pdo->prepare('INSERT INTO product_colors (product_id, color_name, image, is_main) VALUES (?,?,?,?)');
                foreach ($colorRows as $cr) {
                    $ins->execute([$id, $cr['name'], $cr['image'], $cr['main']]);
                }
                // remove the photos of colors that were deleted or replaced
                $keepImgs = array_filter(array_column($colorRows, 'image'));
                foreach ($existingColorImgs as $f) {
                    if (!in_array($f, $keepImgs, true)) delete_upload($f, 'colors');
                }

                $_SESSION['flash_ok'] = 'Product saved.';
                header('Location: products.php');
                exit;

            case 'toggle_available':
                $back = (strpos($_POST['back'] ?? '', 'products.php') === 0) ? $_POST['back'] : 'products.php';
                $pdo->prepare('UPDATE products SET is_available = 1 - is_available WHERE id = ?')
                    ->execute([(int) ($_POST['id'] ?? 0)]);
                header('Location: ' . $back);
                exit;

            case 'toggle_best':
                $back = (strpos($_POST['back'] ?? '', 'products.php') === 0) ? $_POST['back'] : 'products.php';
                $pdo->prepare('UPDATE products SET is_best_seller = 1 - is_best_seller WHERE id = ?')
                    ->execute([(int) ($_POST['id'] ?? 0)]);
                header('Location: ' . $back);
                exit;

            case 'delete_product':
                $id = (int) ($_POST['id'] ?? 0);
                $o = $pdo->prepare('SELECT image FROM products WHERE id = ?');
                $o->execute([$id]);
                delete_upload($o->fetchColumn() ?: null, 'products');
                $ci = $pdo->prepare('SELECT image FROM product_colors WHERE product_id = ? AND image IS NOT NULL');
                $ci->execute([$id]);
                foreach ($ci->fetchAll(PDO::FETCH_COLUMN) as $f) delete_upload($f, 'colors');
                $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
                $_SESSION['flash_ok'] = 'Product deleted.';
                header('Location: products.php');
                exit;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $ex) {
        $err = $ex->getMessage();
        if (($action ?? '') === 'save_product') {
            // Re-show the form with what the user typed
            $form = [
                'id' => (int) ($_POST['id'] ?? 0),
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'price' => $_POST['price'] ?? '',
                'category_id' => (int) ($_POST['category_id'] ?? 0),
                'subcategory_id' => (int) ($_POST['subcategory_id'] ?? 0),
                'image' => null,
                'is_available' => isset($_POST['is_available']) ? 1 : 0,
                'is_best_seller' => isset($_POST['is_best_seller']) ? 1 : 0,
                'colors' => [],
            ];
            foreach (($_POST['color_name'] ?? []) as $i => $cn) {
                $form['colors'][] = [
                    'color_name' => $cn,
                    'image'      => basename((string) ($_POST['color_old_image'][$i] ?? '')) ?: null,
                    'is_main'    => ((int) ($_POST['main_color'] ?? -1) === (int) $i) ? 1 : 0,
                ];
            }
        }
    }
}

// =========================================
// Load data
// =========================================
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order, id')->fetchAll();

$subsByCat = [];
foreach ($pdo->query('SELECT id, category_id, name FROM subcategories ORDER BY sort_order, id') as $s) {
    $subsByCat[$s['category_id']][] = ['id' => (int) $s['id'], 'name' => $s['name']];
}

// Open the form?
if ($form === null && isset($_GET['new'])) {
    $form = [
        'id' => 0, 'name' => '', 'description' => '', 'price' => '',
        'category_id' => (int) ($_GET['cat'] ?? 0), 'subcategory_id' => 0, 'image' => null,
        'is_available' => 1, 'is_best_seller' => 0, 'colors' => [],
    ];
}
if ($form === null && isset($_GET['edit'])) {
    $p = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $p->execute([(int) $_GET['edit']]);
    $row = $p->fetch();
    if ($row) {
        $col = $pdo->prepare('SELECT color_name, image, is_main FROM product_colors WHERE product_id = ? ORDER BY id');
        $col->execute([$row['id']]);
        $row['colors'] = $col->fetchAll();
        $row['subcategory_id'] = (int) $row['subcategory_id'];
        $form = $row;
    }
}

// List
$filterCat = (int) ($_GET['cat'] ?? 0);
$products = [];
if ($form === null) {
    $sql = 'SELECT p.*, c.name AS category_name, s.name AS subcategory_name,
                   (SELECT pc.image FROM product_colors pc
                    WHERE pc.product_id = p.id AND pc.image IS NOT NULL
                    ORDER BY pc.is_main DESC, pc.id LIMIT 1) AS main_color_image
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN subcategories s ON s.id = p.subcategory_id';
    $params = [];
    if ($filterCat) { $sql .= ' WHERE p.category_id = ?'; $params[] = $filterCat; }
    $sql .= ' ORDER BY p.created_at DESC, p.id DESC';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $products = $st->fetchAll();
}

$pageTitle = 'Products';
$active    = 'products';
require __DIR__ . '/includes/layout_top.php';
?>

<?php if ($ok):  ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<?php if ($form !== null): ?>
<!-- ================= ADD / EDIT FORM ================= -->

<h1><?= $form['id'] ? 'Edit product' : 'Add product' ?></h1>

<form method="post" enctype="multipart/form-data" class="card" id="productForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_product">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">

    <label>Name</label>
    <input type="text" name="name" value="<?= e($form['name']) ?>" required>

    <label>Description</label>
    <textarea name="description" rows="4"><?= e($form['description']) ?></textarea>

    <div class="row">
        <div>
            <label>Price ($)</label>
            <input type="number" name="price" step="0.01" min="0" value="<?= e($form['price']) ?>" required>
        </div>
        <div>
            <label>Category</label>
            <select name="category_id" id="categorySelect" required>
                <option value="">Choose…</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $form['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Subcategory (optional)</label>
            <select name="subcategory_id" id="subcategorySelect"></select>
        </div>
    </div>

    <label>Main image <span class="muted">(optional if you add color photos below)</span></label>
    <?php if (!empty($form['image'])): ?>
        <img class="thumb" src="<?= UPLOAD_URL ?>products/<?= e($form['image']) ?>" alt="" style="margin-bottom:8px">
    <?php endif; ?>
    <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
    <div class="muted">JPG, PNG or WEBP. Every photo is cropped to the same square size automatically.<?= $form['id'] ? ' Leave empty to keep the current image.' : '' ?></div>

    <label>Colors (optional)</label>
    <div class="muted" style="margin-bottom:8px">Type the color name and add a photo of the product in that color. Mark one color as <b>Main</b>: its photo is the one shown on the card first. Customers can tap any color to see its photo.</div>
    <div id="colors"></div>
    <button type="button" class="btn light small" id="addColor">+ Add color</button>

    <label class="check"><input type="checkbox" name="is_available" <?= $form['is_available'] ? 'checked' : '' ?>> Available</label>
    <label class="check"><input type="checkbox" name="is_best_seller" <?= $form['is_best_seller'] ? 'checked' : '' ?>> Best seller</label>

    <div class="actions">
        <button class="btn" type="submit">Save product</button>
        <a class="btn light" href="products.php">Cancel</a>
    </div>
</form>

<?php if ($form['id']): ?>
<form method="post" onsubmit="return confirm('Delete this product permanently?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_product">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <button class="btn danger small" type="submit">Delete product</button>
</form>
<?php endif; ?>

<script>
const subsByCat   = <?= json_encode($subsByCat, JSON_UNESCAPED_UNICODE) ?>;
const currentSub  = <?= (int) $form['subcategory_id'] ?>;
const startColors = <?= json_encode($form['colors'], JSON_UNESCAPED_UNICODE) ?>;
const COLOR_URL   = <?= json_encode(UPLOAD_URL . 'colors/') ?>;

const catSel = document.getElementById('categorySelect');
const subSel = document.getElementById('subcategorySelect');

function fillSubs(selected) {
    subSel.innerHTML = '<option value="0">None</option>';
    (subsByCat[catSel.value] || []).forEach(s => {
        const o = document.createElement('option');
        o.value = s.id; o.textContent = s.name;
        if (s.id === selected) o.selected = true;
        subSel.appendChild(o);
    });
}
catSel.addEventListener('change', () => fillSubs(0));
fillSubs(currentSub);

const box = document.getElementById('colors');
function addColor(name = '', image = '', isMain = false) {
    const row = document.createElement('div');
    row.className = 'color-card';
    row.innerHTML =
        '<div class="row color-top">' +
            '<input type="text" name="color_name[]" placeholder="Color name, e.g. Red">' +
            '<label class="color-main"><input type="radio" name="main_color"> Main</label>' +
            '<button type="button" class="btn danger small shrink" aria-label="Remove color">✕</button>' +
        '</div>' +
        '<div class="color-photo">' +
            '<img class="color-prev" alt="" hidden>' +
            '<div><input type="file" name="color_image[]" accept="image/jpeg,image/png,image/webp">' +
            '<div class="muted">Photo for this color</div></div>' +
        '</div>' +
        '<input type="hidden" name="color_old_image[]" value="">';
    const nameInput = row.querySelector('input[type=text]');
    const fileInput = row.querySelector('input[type=file]');
    const prev      = row.querySelector('.color-prev');
    const oldInput  = row.querySelector('input[type=hidden]');
    const radio     = row.querySelector('input[type=radio]');

    nameInput.value = name;
    radio.checked = !!isMain;
    if (image) {
        oldInput.value = image;
        prev.src = COLOR_URL + image;
        prev.hidden = false;
    }
    fileInput.addEventListener('change', () => {
        const f = fileInput.files && fileInput.files[0];
        if (!f) return;
        const r = new FileReader();
        r.onload = () => { prev.src = r.result; prev.hidden = false; };
        r.readAsDataURL(f);
    });
    row.querySelector('button').addEventListener('click', () => {
        row.remove();
        ensureMain();
    });
    box.appendChild(row);
    ensureMain();
}

// there is always one "Main" color when there is at least one color
function ensureMain() {
    const radios = box.querySelectorAll('input[type=radio]');
    if (radios.length && !Array.from(radios).some(r => r.checked)) radios[0].checked = true;
}

// the server needs to know WHICH row is the main one (its position)
document.getElementById('productForm').addEventListener('submit', () => {
    box.querySelectorAll('.color-card').forEach((row, i) => {
        row.querySelector('input[type=radio]').value = i;
    });
});

document.getElementById('addColor').addEventListener('click', () => addColor());
startColors.forEach(c => addColor(c.color_name, c.image || '', parseInt(c.is_main, 10) === 1));
ensureMain();
</script>

<?php else: ?>
<!-- ================= LIST ================= -->

<div class="row" style="align-items:center;margin-bottom:16px">
    <h1 style="margin:0">Products</h1>
    <div class="shrink"><a class="btn" href="products.php?new=1<?= $filterCat ? '&cat=' . $filterCat : '' ?>">+ Add product</a></div>
</div>

<form method="get" style="margin-bottom:16px">
    <select name="cat" onchange="this.form.submit()">
        <option value="0">All categories</option>
        <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= $filterCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
</form>

<?php if (!$products): ?>
    <div class="card muted">No products yet. Click “Add product” to create the first one.</div>
<?php endif; ?>

<?php $back = 'products.php' . ($filterCat ? '?cat=' . $filterCat : ''); ?>
<?php foreach ($products as $p): ?>
    <div class="card">
        <div class="cat-head">
            <?php if ($p['main_color_image']): ?>
                <img src="<?= UPLOAD_URL ?>colors/<?= e($p['main_color_image']) ?>" alt="">
            <?php elseif ($p['image']): ?>
                <img src="<?= UPLOAD_URL ?>products/<?= e($p['image']) ?>" alt="">
            <?php else: ?>
                <div class="thumb empty">✿</div>
            <?php endif; ?>
            <div class="cat-title">
                <strong><?= e($p['name']) ?></strong>
                <?php if ($p['is_best_seller']): ?><span class="badge on">★ Best seller</span><?php endif; ?>
                <div class="muted">
                    $<?= number_format((float) $p['price'], 2) ?> ·
                    <?= e($p['category_name']) ?><?= $p['subcategory_name'] ? ' → ' . e($p['subcategory_name']) : '' ?>
                </div>
            </div>
        </div>

        <div class="actions">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_available">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn small <?= $p['is_available'] ? '' : 'light' ?>" type="submit">
                    <?= $p['is_available'] ? '🟢 Available' : '🔴 Not available' ?>
                </button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_best">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <input type="hidden" name="back" value="<?= e($back) ?>">
                <button class="btn light small" type="submit"><?= $p['is_best_seller'] ? '★ Remove best seller' : '☆ Make best seller' ?></button>
            </form>
            <a class="btn light small" href="products.php?edit=<?= (int) $p['id'] ?>">Edit</a>
        </div>
    </div>
<?php endforeach; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>