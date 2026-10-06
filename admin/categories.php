<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$ok  = $_SESSION['flash_ok']  ?? '';
$err = '';
unset($_SESSION['flash_ok']);

// =========================================
// Handle actions
// =========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            case 'add_category':
                $name = trim($_POST['name'] ?? '');
                if ($name === '') throw new Exception('Category name is required.');
                $image = upload_image($_FILES['image'] ?? [], 'categories');
                $next  = (int) $pdo->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM categories')->fetchColumn();
                $pdo->prepare('INSERT INTO categories (name, image, sort_order) VALUES (?,?,?)')
                    ->execute([$name, $image, $next]);
                $_SESSION['flash_ok'] = 'Category added.';
                break;

            case 'edit_category':
                $id    = (int) ($_POST['id'] ?? 0);
                $name  = trim($_POST['name'] ?? '');
                $sort  = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '') throw new Exception('Category name is required.');

                $old = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
                $old->execute([$id]);
                $oldImage = $old->fetchColumn();

                $newImage = upload_image($_FILES['image'] ?? [], 'categories');
                if ($newImage) {
                    delete_upload($oldImage, 'categories');
                    $image = $newImage;
                } else {
                    $image = $oldImage;
                }
                $pdo->prepare('UPDATE categories SET name=?, image=?, sort_order=?, is_active=? WHERE id=?')
                    ->execute([$name, $image, $sort, $active, $id]);
                $_SESSION['flash_ok'] = 'Category updated.';
                break;

            case 'delete_category':
                $id = (int) ($_POST['id'] ?? 0);
                $c = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
                $c->execute([$id]);
                if ((int) $c->fetchColumn() > 0) {
                    throw new Exception('This category still has products. Move or delete them first.');
                }
                $old = $pdo->prepare('SELECT image FROM categories WHERE id = ?');
                $old->execute([$id]);
                delete_upload($old->fetchColumn(), 'categories');
                $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
                $_SESSION['flash_ok'] = 'Category deleted.';
                break;

            case 'add_subcategory':
                $catId = (int) ($_POST['category_id'] ?? 0);
                $name  = trim($_POST['name'] ?? '');
                if ($name === '') throw new Exception('Subcategory name is required.');
                $n = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM subcategories WHERE category_id = ?');
                $n->execute([$catId]);
                $pdo->prepare('INSERT INTO subcategories (category_id, name, sort_order) VALUES (?,?,?)')
                    ->execute([$catId, $name, (int) $n->fetchColumn()]);
                $_SESSION['flash_ok'] = 'Subcategory added.';
                break;

            case 'edit_subcategory':
                $id     = (int) ($_POST['id'] ?? 0);
                $name   = trim($_POST['name'] ?? '');
                $sort   = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '') throw new Exception('Subcategory name is required.');
                $pdo->prepare('UPDATE subcategories SET name=?, sort_order=?, is_active=? WHERE id=?')
                    ->execute([$name, $sort, $active, $id]);
                $_SESSION['flash_ok'] = 'Subcategory updated.';
                break;

            case 'delete_subcategory':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM subcategories WHERE id = ?')->execute([$id]);
                $_SESSION['flash_ok'] = 'Subcategory deleted.';
                break;

            default:
                throw new Exception('Unknown action.');
        }

        // Post/Redirect/Get so refresh does not resubmit
        header('Location: categories.php');
        exit;

    } catch (Exception $ex) {
        $err = $ex->getMessage();
    }
}

// =========================================
// Load data
// =========================================
$categories = $pdo->query('SELECT * FROM categories ORDER BY sort_order, id')->fetchAll();

$subs = [];
foreach ($pdo->query('SELECT * FROM subcategories ORDER BY sort_order, id') as $s) {
    $subs[$s['category_id']][] = $s;
}

$productCounts = [];
foreach ($pdo->query('SELECT category_id, COUNT(*) AS c FROM products GROUP BY category_id') as $r) {
    $productCounts[$r['category_id']] = (int) $r['c'];
}

$pageTitle = 'Categories';
$active    = 'categories';
require __DIR__ . '/includes/layout_top.php';
?>

<h1>Categories</h1>

<?php if ($ok):  ?><div class="alert ok"><?= e($ok) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>

<!-- Add category -->
<div class="card">
    <h3>Add category</h3>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">
        <div class="row">
            <div>
                <label>Name</label>
                <input type="text" name="name" placeholder="e.g. Bracelets" required>
            </div>
            <div>
                <label>Image (optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="shrink"><button class="btn" type="submit">Add</button></div>
        </div>
    </form>
</div>

<!-- Category list -->
<?php foreach ($categories as $cat): ?>
    <?php $count = $productCounts[$cat['id']] ?? 0; ?>
    <div class="card">
        <div class="cat-head">
            <?php if ($cat['image']): ?>
                <img src="<?= UPLOAD_URL ?>categories/<?= e($cat['image']) ?>" alt="">
            <?php else: ?>
                <div class="thumb empty">✿</div>
            <?php endif; ?>
            <div class="cat-title">
                <strong><?= e($cat['name']) ?></strong>
                <span class="badge <?= $cat['is_active'] ? 'on' : 'off' ?>"><?= $cat['is_active'] ? 'Active' : 'Hidden' ?></span>
                <div class="muted"><?= $count ?> product<?= $count === 1 ? '' : 's' ?> · order <?= (int) $cat['sort_order'] ?></div>
            </div>
        </div>

        <details>
            <summary>Edit category</summary>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_category">
                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                <div class="row">
                    <div>
                        <label>Name</label>
                        <input type="text" name="name" value="<?= e($cat['name']) ?>" required>
                    </div>
                    <div class="shrink" style="width:90px">
                        <label>Order</label>
                        <input type="number" name="sort_order" value="<?= (int) $cat['sort_order'] ?>">
                    </div>
                </div>
                <label>Replace image (optional)</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
                <label class="check">
                    <input type="checkbox" name="is_active" <?= $cat['is_active'] ? 'checked' : '' ?>>
                    Show on website
                </label>
                <button class="btn small" type="submit">Save</button>
            </form>

            <form method="post" class="actions" onsubmit="return confirm('Delete this category and its subcategories?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                <button class="btn danger small" type="submit">Delete category</button>
            </form>
        </details>

        <!-- Subcategories -->
        <div class="sub-list">
            <h3>Subcategories</h3>

            <?php foreach ($subs[$cat['id']] ?? [] as $sub): ?>
                <div class="sub-item">
                    <form method="post" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit_subcategory">
                        <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
                        <input type="text" name="name" value="<?= e($sub['name']) ?>" required>
                        <input type="number" name="sort_order" value="<?= (int) $sub['sort_order'] ?>" title="Order">
                        <label class="check">
                            <input type="checkbox" name="is_active" <?= $sub['is_active'] ? 'checked' : '' ?>> Active
                        </label>
                        <button class="btn light small" type="submit">Save</button>
                    </form>
                    <form method="post" style="margin-top:6px" onsubmit="return confirm('Delete this subcategory?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_subcategory">
                        <input type="hidden" name="id" value="<?= (int) $sub['id'] ?>">
                        <button class="btn danger small" type="submit">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>

            <?php if (empty($subs[$cat['id']])): ?>
                <p class="muted">No subcategories yet.</p>
            <?php endif; ?>

            <form method="post" class="row" style="margin-top:10px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_subcategory">
                <input type="hidden" name="category_id" value="<?= (int) $cat['id'] ?>">
                <input type="text" name="name" placeholder="New subcategory (e.g. Gold)" required>
                <div class="shrink"><button class="btn small" type="submit">Add</button></div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>