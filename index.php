<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-card.php';
require_once __DIR__ . '/includes/package-card.php';

// ---------- Categories ----------
$categories = $pdo->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
$subsByCat  = [];
foreach ($pdo->query('SELECT * FROM subcategories WHERE is_active = 1 ORDER BY sort_order, id') as $s) {
    $subsByCat[$s['category_id']][] = $s;
}

// ---------- Offers + prices ----------
$offers      = get_active_offers($pdo);
$sale        = get_sale_prices($pdo);
$bogoMap     = get_bogo_map($pdo);
$offerIdsMap = get_offer_ids_by_product($pdo);

$colorsByProduct = load_colors($pdo);
$packages        = load_packages($pdo, $colorsByProduct);

// ---------- Products (all of them: the filter runs in the browser, no reload) ----------
$products = $pdo->query(
    'SELECT p.* FROM products p
     JOIN categories c ON c.id = p.category_id AND c.is_active = 1
     ORDER BY p.is_available DESC, p.created_at DESC, p.id DESC'
)->fetchAll();

$bestSellers = array_slice(array_values(array_filter($products, function ($p) {
    return (int) $p['is_best_seller'] === 1;
})), 0, 8);

function card(array $p, array $sale, array $bogoMap, array $offerIdsMap, array $colorsByProduct): string {
    $id = (int) $p['id'];
    return render_product_card(
        $p,
        $sale[$id] ?? null,
        $colorsByProduct[$id] ?? [],
        $bogoMap[$id] ?? null,
        $offerIdsMap[$id] ?? []
    );
}

// ---------- Text under the logo (Custom Italian Charm Bracelets) ----------
$featureTitle = defined('FEATURE_TITLE') ? trim(FEATURE_TITLE) : 'Custom Italian Charm Bracelets';
$featureSub   = defined('FEATURE_SUB')   ? trim(FEATURE_SUB)   : 'Stainless Steel • Gold Plated';
$featureText  = defined('FEATURE_TEXT')  ? trim(FEATURE_TEXT)  : 'Design yours with your favorite charms';

// ---------- Data for shop.js ----------
$shopData = ['categories' => [], 'offers' => []];
foreach ($categories as $c) {
    $subs = [];
    foreach ($subsByCat[$c['id']] ?? [] as $s) $subs[] = ['id' => (int) $s['id'], 'name' => $s['name']];
    $shopData['categories'][] = ['id' => (int) $c['id'], 'name' => $c['name'], 'subs' => $subs];
}
foreach ($offers as $o) $shopData['offers'][] = ['id' => (int) $o['id'], 'title' => $o['title']];

$pageTitle = SITE_NAME . ' · Bracelets, Necklaces & more';
require __DIR__ . '/includes/header.php';
?>

<main>

<!-- ================= HERO ================= -->
<section class="hero">
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>

    <div class="hero-inner">
        <div class="hero-badge">
            <div class="orbit">
                <i class="spark sp1">✦</i><i class="spark sp2">✦</i><i class="spark sp3">✦</i><i class="spark sp4">✧</i>
            </div>
            <div class="badge-disc">
                <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>">
            </div>
        </div>

        <?php if ($featureTitle !== ''): ?><h1 class="hero-title"><?= e($featureTitle) ?></h1><?php endif; ?>
        <?php if ($featureSub !== ''): ?><p class="hero-material"><span><?= e($featureSub) ?></span></p><?php endif; ?>
        <?php if ($featureText !== ''): ?><p class="hero-script"><?= e($featureText) ?></p><?php endif; ?>

        <a class="btn-primary" href="#shop">Shop now</a>
    </div>
</section>

<!-- ================= CATEGORIES ================= -->
<?php if ($categories): ?>
<section class="section fade-section" id="categories">
    <h2 class="section-title">Categories</h2>
    <div class="cat-row">
        <?php foreach ($categories as $c): ?>
            <a class="cat-item" href="?cat=<?= (int) $c['id'] ?>#shop" data-cat="<?= (int) $c['id'] ?>">
                <span class="cat-circle">
                    <?php if ($c['image']): ?>
                        <img src="<?= UPLOAD_URL ?>categories/<?= e($c['image']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span>✿</span>
                    <?php endif; ?>
                </span>
                <span class="cat-name"><?= e($c['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ================= OFFERS (only when there is a running offer) ================= -->
<?php if ($offers): ?>
<section class="section offers fade-section" id="offers">
    <?php foreach ($offers as $o): $isBogo = ($o['discount_type'] ?? '') === 'bogo'; ?>
        <a class="offer-card" href="?offer=<?= (int) $o['id'] ?>#shop" data-offer="<?= (int) $o['id'] ?>">
            <?php if ($o['image']): ?>
                <img class="offer-bg" src="<?= UPLOAD_URL ?>offers/<?= e($o['image']) ?>" alt="" loading="lazy">
            <?php endif; ?>
            <i class="offer-spark os1">✦</i><i class="offer-spark os2">✧</i><i class="offer-spark os3">✦</i>
            <i class="offer-spark os4">✧</i><i class="offer-spark os5">✦</i>
            <div class="offer-text">
                <span class="offer-kicker"><?= e($o['title']) ?></span>
                <strong class="offer-big"><?= e(offer_big_text($o)) ?></strong>
                <?php if ($isBogo): ?><span class="offer-chip">Free</span><?php endif; ?>
                <?php if ($o['description']): ?><span class="offer-desc"><?= e($o['description']) ?></span><?php endif; ?>
                <?php if ($o['end_date']): ?><span class="offer-until">Until <?= e(date('j M', strtotime($o['end_date']))) ?></span><?php endif; ?>
                <span class="offer-cta">Shop the offer</span>
            </div>
        </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<!-- ================= PACKAGES (only when the owner created some) ================= -->
<?php if ($packages): ?>
<section class="section fade-section" id="packages">
    <h2 class="section-title">Packages</h2>
    <p class="section-sub">Sets made for you, at a special price</p>
    <div class="grid">
        <?php foreach ($packages as $pkg) echo render_package_card($pkg); ?>
    </div>
</section>
<?php endif; ?>

<!-- ================= BEST SELLERS ================= -->
<?php if ($bestSellers): ?>
<section class="section fade-section" id="best-sellers">
    <h2 class="section-title">Best Sellers</h2>
    <div class="grid">
        <?php foreach ($bestSellers as $p) echo card($p, $sale, $bogoMap, $offerIdsMap, $colorsByProduct); ?>
    </div>
</section>
<?php endif; ?>

<!-- ================= SHOP ================= -->
<section class="section fade-section" id="shop">
    <h2 class="section-title" id="shopTitle">All pieces</h2>

    <div class="chips" id="shopChips">
        <a class="chip active" href="./#shop" data-cat="0">All</a>
        <?php foreach ($categories as $c): ?>
            <a class="chip" href="?cat=<?= (int) $c['id'] ?>#shop" data-cat="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="chips chips-sub" id="shopSubChips" hidden></div>

    <div class="grid" id="shopGrid">
        <?php foreach ($products as $p) echo card($p, $sale, $bogoMap, $offerIdsMap, $colorsByProduct); ?>
    </div>
    <p class="empty" id="shopEmpty" <?= $products ? 'hidden' : '' ?>>Nothing here yet. Check back soon ✿</p>
</section>

</main>

<script>window.SHOP = <?= json_encode($shopData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php require __DIR__ . '/includes/footer.php'; ?>