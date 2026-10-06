<?php
// Renders one product card.
// render_product_card($product, $salePriceOrNull, $colors, $bogoOrNull, $offerIds)
// $colors = [ {name, code, image, thumb, main}, ... ]  (see load_colors() in functions.php)
function render_product_card(array $p, ?float $sale, array $colors, ?array $bogo = null, array $offerIds = []): string {
    $available = (int) $p['is_available'] === 1;
    $price     = (float) $p['price'];
    $final     = $sale ?? $price;
    $bogoText  = $bogo ? 'Buy ' . (int) $bogo['buy'] . ' Get ' . (int) $bogo['get'] . ' FREE' : '';

    // The "main" color is the one shown first (its photo is the card photo)
    $defaultColor = '';
    foreach ($colors as $c) {
        if (!empty($c['main'])) { $defaultColor = $c['name']; break; }
    }
    if ($defaultColor === '' && $colors) $defaultColor = $colors[0]['name'];

    $imageUrl = $p['image'] ? UPLOAD_URL . 'products/' . $p['image'] : '';
    foreach ($colors as $c) {
        if ($c['name'] === $defaultColor && $c['image']) $imageUrl = $c['image'];
    }

    $data = [
        'id'           => (int) $p['id'],
        'name'         => $p['name'],
        'description'  => $p['description'] ?? '',
        'price'        => $final,
        'oldPrice'     => $sale !== null ? $price : null,
        'image'        => $imageUrl,
        'colors'       => $colors,
        'defaultColor' => $defaultColor,
        'available'    => $available,
        'bogo'         => $bogo ? ['buy' => (int) $bogo['buy'], 'get' => (int) $bogo['get']] : null,
    ];
    $maxBubbles = 5;

    ob_start(); ?>
    <article class="product <?= $available ? '' : 'is-out' ?>"
             data-cat="<?= (int) $p['category_id'] ?>"
             data-sub="<?= (int) ($p['subcategory_id'] ?? 0) ?>"
             data-offers="<?= e(implode(',', $offerIds)) ?>"
             data-product="<?= e(json_encode($data, JSON_UNESCAPED_UNICODE)) ?>">
        <button type="button" class="product-media js-details" aria-label="View details of <?= e($p['name']) ?>">
            <?php if ($imageUrl): ?>
                <img src="<?= e($imageUrl) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
            <?php else: ?>
                <span class="placeholder">✿</span>
            <?php endif; ?>
            <?php if ($available && $bogo): ?>
                <span class="tag tag-sale">Buy <?= (int) $bogo['buy'] ?> Get <?= (int) $bogo['get'] ?></span>
            <?php elseif ($available && $sale !== null): ?>
                <span class="tag tag-sale">Sale</span>
            <?php endif; ?>
            <?php if (!$available): ?><span class="tag tag-out">Not available</span><?php endif; ?>
        </button>
        <div class="product-body">
            <?php if ($colors): ?>
                <div class="card-colors" role="group" aria-label="Colors">
                    <?php foreach (array_slice($colors, 0, $maxBubbles) as $c): ?>
                        <button type="button" class="card-swatch <?= $c['name'] === $defaultColor ? 'selected' : '' ?>"
                                data-color="<?= e($c['name']) ?>" title="<?= e($c['name']) ?>" aria-label="<?= e($c['name']) ?>">
                            <?php if ($c['thumb']): ?>
                                <img src="<?= e($c['thumb']) ?>" alt="" loading="lazy">
                            <?php elseif ($c['code']): ?>
                                <i style="background:<?= e($c['code']) ?>"></i>
                            <?php else: ?>
                                <span><?= e(mb_strtoupper(mb_substr($c['name'], 0, 1))) ?></span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                    <?php if (count($colors) > $maxBubbles): ?>
                        <button type="button" class="card-more js-details" aria-label="More colors">+<?= count($colors) - $maxBubbles ?></button>
                    <?php endif; ?>
                </div>
                <div class="card-color-label"><?= e($defaultColor) ?></div>
            <?php endif; ?>
            <h3 class="product-name"><?= e($p['name']) ?></h3>
            <div class="product-price">
                <?php if ($sale !== null): ?>
                    <span class="now"><?= money($sale) ?></span>
                    <span class="was"><?= money($price) ?></span>
                <?php else: ?>
                    <span class="now"><?= money($price) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($bogoText && $available): ?>
                <div class="product-bogo">🎁 <?= e($bogoText) ?></div>
            <?php endif; ?>
            <div class="product-actions">
                <button type="button" class="btn-add js-add" <?= $available ? '' : 'disabled' ?>>
                    <?= $available ? 'Add to cart' : 'Sold out' ?>
                </button>
                <button type="button" class="btn-details js-details">Details</button>
            </div>
        </div>
    </article>
    <?php
    return ob_get_clean();
}