<?php
// Renders one package card.  $pkg comes from load_packages() in functions.php
function render_package_card(array $pkg): string {
    $data = [
        'id'          => $pkg['id'],
        'name'        => $pkg['name'],
        'description' => $pkg['description'],
        'price'       => $pkg['price'],
        'regular'     => $pkg['regular'],
        'save'        => $pkg['save'],
        'image'       => $pkg['image'],
        'available'   => $pkg['available'],
        'items'       => $pkg['items'],
    ];
    // group repeated products: "2 × Heart Charm"
    $groups = [];
    foreach ($pkg['items'] as $it) {
        $k = $it['id'];
        if (!isset($groups[$k])) $groups[$k] = ['name' => $it['name'], 'n' => 0];
        $groups[$k]['n']++;
    }
    $groups = array_values($groups);
    $shown  = array_slice($groups, 0, 3);
    $more   = count($groups) - count($shown);

    ob_start(); ?>
    <article class="product pkg-card <?= $pkg['available'] ? '' : 'is-out' ?>" data-package="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>">
        <button type="button" class="product-media js-pkg-view" aria-label="View package <?= e($pkg['name']) ?>">
            <?php if ($pkg['image']): ?>
                <img src="<?= e($pkg['image']) ?>" alt="<?= e($pkg['name']) ?>" loading="lazy">
            <?php else: ?>
                <span class="placeholder">✨</span>
            <?php endif; ?>
            <?php if ($pkg['available'] && $pkg['save'] > 0): ?>
                <span class="tag tag-sale">Save <?= money($pkg['save']) ?></span>
            <?php endif; ?>
            <?php if (!$pkg['available']): ?><span class="tag tag-out">Not available</span><?php endif; ?>
        </button>
        <div class="product-body">
            <h3 class="product-name">✨ <?= e($pkg['name']) ?></h3>
            <div class="pkg-includes">
                <span>Includes:</span>
                <ul>
                    <?php foreach ($shown as $g): ?><li><?= $g['n'] > 1 ? (int) $g['n'] . ' × ' : '' ?><?= e($g['name']) ?></li><?php endforeach; ?>
                    <?php if ($more > 0): ?><li class="more">+ <?= (int) $more ?> more</li><?php endif; ?>
                </ul>
            </div>
            <div class="product-price">
                <span class="now"><?= money($pkg['price']) ?></span>
                <?php if ($pkg['save'] > 0): ?><span class="was"><?= money($pkg['regular']) ?></span><?php endif; ?>
            </div>
            <?php if ($pkg['save'] > 0): ?><div class="pkg-save">Save <?= money($pkg['save']) ?></div><?php endif; ?>
            <div class="product-actions">
                <button type="button" class="btn-add js-pkg-add" <?= $pkg['available'] ? '' : 'disabled' ?>>
                    <?= $pkg['available'] ? 'Add to cart' : 'Sold out' ?>
                </button>
                <button type="button" class="btn-details js-pkg-view">View package</button>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}