<?php
// Shared helpers. Requires config.php to be loaded first (for UPLOAD_DIR + session).

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void {
    // If the upload is bigger than PHP's post_max_size, PHP drops ALL form data
    if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
        http_response_code(413);
        die('The file is too big for the server (post_max_size = ' . ini_get('post_max_size')
            . '). Try a smaller image. <a href="">Go back</a>');
    }

    $sent    = $_POST['csrf'] ?? '';
    $session = $_SESSION['csrf'] ?? '';

    if ($session === '' || $sent === '' || !hash_equals($session, $sent)) {
        http_response_code(403);
        die('Session expired. Please <a href="login.php">log in again</a> and retry.');
    }
}

// ---------- Image upload ----------
// Returns the saved filename, or null if no file was chosen. Throws Exception on a bad file.
function upload_image(array $file, string $folder): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload failed. Try a smaller image.');
    }
    // Product / color photos are resized below, so big phone photos are fine
    $square = in_array($folder, ['products', 'colors', 'packages'], true);
    $maxMb  = $square ? 12 : 3;
    if ($file['size'] > $maxMb * 1024 * 1024) {
        throw new Exception('Image is too big (max ' . $maxMb . 'MB).');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new Exception('Only JPG, PNG or WEBP images are allowed.');
    }

    $dir = UPLOAD_DIR . $folder . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Product + color photos: every photo becomes the same square size (centered crop)
    if ($square) {
        $jpgName = bin2hex(random_bytes(8)) . '.jpg';
        if (save_square_image($file['tmp_name'], $mime, $dir . $jpgName, 800, 84)) {
            if ($folder === 'colors') {
                save_square_image($file['tmp_name'], $mime, $dir . 'thumb_' . $jpgName, 120, 80);   // small bubble for the cards
            }
            return $jpgName;
        }
        // (if the server has no image library, we fall through and save the original)
    }

    $name = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) {
        throw new Exception('Could not save the image.');
    }
    return $name;
}

function delete_upload(?string $filename, string $folder): void {
    if ($filename) {
        $base = basename($filename);
        foreach ([$base, 'thumb_' . $base] as $f) {
            $path = UPLOAD_DIR . $folder . '/' . $f;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

// Center-crops any photo to a square and saves it as a JPEG of $size x $size pixels.
// Returns false when the server cannot do it (no GD library).
function save_square_image(string $tmp, string $mime, string $dest, int $size = 800, int $quality = 84): bool {
    if (!function_exists('imagecreatetruecolor')) return false;

    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($tmp); break;
        case 'image/png':  $src = @imagecreatefrompng($tmp);  break;
        case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false; break;
        default: return false;
    }
    if (!$src) return false;

    // phone photos: respect the camera rotation
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $o = $exif['Orientation'] ?? 1;
        $rot = $o == 3 ? 180 : ($o == 6 ? -90 : ($o == 8 ? 90 : 0));
        if ($rot) {
            $r = imagerotate($src, $rot, 0);
            if ($r) $src = $r;
        }
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $x = (int) (($w - $side) / 2);
    $y = (int) (($h - $side) / 2);

    $out = imagecreatetruecolor($size, $size);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // transparent PNGs get a white background
    imagecopyresampled($out, $src, 0, 0, $x, $y, $size, $size, $side, $side);
    $ok = imagejpeg($out, $dest, $quality);

    imagedestroy($src);
    imagedestroy($out);
    return $ok;
}


// =========================================
// Public site helpers
// =========================================

function money($n): string {
    $n = (float) $n;
    return '$' . (floor($n) == $n ? number_format($n, 0) : number_format($n, 2));
}

function load_settings(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query('SELECT setting_key, setting_value FROM store_settings') as $r) {
        $out[$r['setting_key']] = $r['setting_value'];
    }
    return $out;
}

// SQL condition for offers that are running right now
const ACTIVE_OFFER_SQL = "o.is_active = 1
    AND (o.start_date IS NULL OR o.start_date <= NOW())
    AND (o.end_date   IS NULL OR o.end_date   >= NOW())";

// Active offers (only those that exist and are running) + how many products each has
function get_active_offers(PDO $pdo): array {
    return $pdo->query(
        "SELECT o.*, COUNT(op.id) AS product_count
         FROM offers o
         LEFT JOIN offer_products op ON op.offer_id = o.id
         WHERE " . ACTIVE_OFFER_SQL . "
         GROUP BY o.id
         HAVING product_count > 0
         ORDER BY o.created_at DESC"
    )->fetchAll();
}

// [product_id => sale_price] using the best (lowest) running offer for each product
function get_sale_prices(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT op.product_id, o.discount_type, o.discount_value, p.price
         FROM offers o
         JOIN offer_products op ON op.offer_id = o.id
         JOIN products p ON p.id = op.product_id
         WHERE o.discount_type <> 'bogo' AND " . ACTIVE_OFFER_SQL
    )->fetchAll();

    $sale = [];
    foreach ($rows as $r) {
        $price = (float) $r['price'];
        $value = (float) $r['discount_value'];
        $new   = $r['discount_type'] === 'percentage'
            ? $price * (1 - $value / 100)
            : $price - $value;
        $new = round(max(0, $new), 2);
        if ($new < $price && (!isset($sale[$r['product_id']]) || $new < $sale[$r['product_id']])) {
            $sale[$r['product_id']] = $new;
        }
    }
    return $sale;
}

// WhatsApp number from config (digits only), or '' if it is not set properly
function whatsapp_number(): string {
    $n = preg_replace('/\D/', '', defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '');
    return strlen($n) >= 10 ? $n : '';
}

function whatsapp_link(string $text = ''): string {
    $n = whatsapp_number();
    if ($n === '') return '';
    return 'https://wa.me/' . $n . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}


// URL of a file inside /assets with an automatic version (changes whenever the file changes),
// so browsers never keep serving an old CSS/JS file.
function asset(string $path): string {
    $file = __DIR__ . '/../assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : time();
    return BASE_URL . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}


// =========================================
// Offer text + "Buy X Get Y free" helpers
// =========================================

// Small label, e.g. "20% OFF", "$5 OFF", "Buy 1 Get 1 FREE"
function offer_label(array $o): string {
    if (($o['discount_type'] ?? '') === 'bogo') {
        return 'Buy ' . (int) ($o['buy_qty'] ?? 1) . ' Get ' . (int) ($o['get_qty'] ?? 1) . ' FREE';
    }
    $v = (float) $o['discount_value'];
    $n = floor($v) == $v ? number_format($v, 0) : number_format($v, 2);
    return ($o['discount_type'] ?? '') === 'percentage' ? $n . '% OFF' : '$' . $n . ' OFF';
}

// Big text on the offer banner: "20% OFF" or "Buy 1 Get 1" (the FREE goes in a chip)
function offer_big_text(array $o): string {
    if (($o['discount_type'] ?? '') === 'bogo') {
        return 'Buy ' . (int) ($o['buy_qty'] ?? 1) . ' Get ' . (int) ($o['get_qty'] ?? 1);
    }
    return offer_label($o);
}

// [product_id => ['buy' => 1, 'get' => 1]] for products inside a running "Buy X Get Y free" offer
function get_bogo_map(PDO $pdo): array {
    try {
        $rows = $pdo->query(
            "SELECT op.product_id, o.buy_qty, o.get_qty
             FROM offers o
             JOIN offer_products op ON op.offer_id = o.id
             WHERE o.discount_type = 'bogo' AND " . ACTIVE_OFFER_SQL
        )->fetchAll();
    } catch (PDOException $e) {
        return [];   // buy_qty / get_qty columns not added yet -> run the ALTER TABLE
    }
    $map = [];
    foreach ($rows as $r) {
        $buy = max(1, (int) $r['buy_qty']);
        $get = max(1, (int) $r['get_qty']);
        $ratio = $get / ($buy + $get);
        $pid = (int) $r['product_id'];
        if (!isset($map[$pid]) || $ratio > $map[$pid]['ratio']) {
            $map[$pid] = ['buy' => $buy, 'get' => $get, 'ratio' => $ratio];
        }
    }
    foreach ($map as $pid => $m) unset($map[$pid]['ratio']);
    return $map;
}

// [product_id => [offer ids]] for every running offer (used by the shop filter)
function get_offer_ids_by_product(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT op.product_id, o.id AS offer_id
         FROM offers o
         JOIN offer_products op ON op.offer_id = o.id
         WHERE " . ACTIVE_OFFER_SQL
    )->fetchAll();
    $map = [];
    foreach ($rows as $r) $map[(int) $r['product_id']][] = (int) $r['offer_id'];
    return $map;
}


// =========================================
// Colors of every product: [product_id => [ {name, code, image, thumb, main}, ... ]]
// =========================================
function load_colors(PDO $pdo): array {
    // works even before the newest columns were added to the database
    $variants = [
        'SELECT product_id, color_name, color_code, image, is_main FROM product_colors ORDER BY id',
        'SELECT product_id, color_name, color_code, image, 0 AS is_main FROM product_colors ORDER BY id',
        'SELECT product_id, color_name, color_code, NULL AS image, 0 AS is_main FROM product_colors ORDER BY id',
    ];
    $rows = [];
    foreach ($variants as $sql) {
        try { $rows = $pdo->query($sql)->fetchAll(); break; }
        catch (PDOException $e) { continue; }
    }

    $out = [];
    foreach ($rows as $r) {
        $img = $r['image'] ?? null;
        $thumb = '';
        if ($img) {
            $thumb = is_file(UPLOAD_DIR . 'colors/thumb_' . $img)
                ? UPLOAD_URL . 'colors/thumb_' . $img
                : UPLOAD_URL . 'colors/' . $img;
        }
        $out[(int) $r['product_id']][] = [
            'name'  => $r['color_name'],
            'code'  => $r['color_code'],
            'image' => $img ? UPLOAD_URL . 'colors/' . $img : '',
            'thumb' => $thumb,
            'main'  => (int) ($r['is_main'] ?? 0) === 1,
        ];
    }
    return $out;
}


// =========================================
// Packages: [ {id, name, description, price, regular, save, image, available, items:[...]}, ... ]
// "regular" = what the products cost separately, "save" = regular - package price
// =========================================
function load_packages(PDO $pdo, array $colorsByProduct = []): array {
    try {
        $pks = $pdo->query('SELECT * FROM packages WHERE is_active = 1 ORDER BY created_at DESC, id DESC')->fetchAll();
        if (!$pks) return [];
        try {
            $rows = $pdo->query(
                'SELECT pp.package_id, pp.qty, p.id, p.name, p.price, p.image, p.is_available
                 FROM package_products pp
                 JOIN products p ON p.id = pp.product_id
                 ORDER BY pp.id'
            )->fetchAll();
        } catch (PDOException $e2) {   // "qty" column not added yet -> every product counts once
            $rows = $pdo->query(
                'SELECT pp.package_id, 1 AS qty, p.id, p.name, p.price, p.image, p.is_available
                 FROM package_products pp
                 JOIN products p ON p.id = pp.product_id
                 ORDER BY pp.id'
            )->fetchAll();
        }
    } catch (PDOException $e) {
        return [];   // the packages tables were not created yet
    }

    $itemsByPackage = [];
    foreach ($rows as $r) {
        $pid    = (int) $r['id'];
        $colors = $colorsByProduct[$pid] ?? [];

        // same rule as the product card: the "main" color (or the first) is shown first
        $default = '';
        foreach ($colors as $c) { if (!empty($c['main'])) { $default = $c['name']; break; } }
        if ($default === '' && $colors) $default = $colors[0]['name'];
        $img = $r['image'] ? UPLOAD_URL . 'products/' . $r['image'] : '';
        foreach ($colors as $c) { if ($c['name'] === $default && $c['image']) $img = $c['image']; }

        // the same product twice = two entries (so the customer can pick a color for each one)
        $times = max(1, (int) ($r['qty'] ?? 1));
        for ($u = 0; $u < $times; $u++) {
            $itemsByPackage[(int) $r['package_id']][] = [
                'id'           => $pid,
                'name'         => $r['name'],
                'price'        => (float) $r['price'],
                'image'        => $img,
                'colors'       => $colors,
                'defaultColor' => $default,
                'available'    => (int) $r['is_available'] === 1,
            ];
        }
    }

    $out = [];
    foreach ($pks as $pk) {
        $items = $itemsByPackage[(int) $pk['id']] ?? [];
        if (!$items) continue;                       // every product was deleted -> hide the package
        $regular = 0.0;
        $available = true;
        foreach ($items as $it) {
            $regular += $it['price'];
            if (!$it['available']) $available = false;   // one product missing = package not available
        }
        $price = (float) $pk['price'];
        $out[] = [
            'id'          => (int) $pk['id'],
            'name'        => $pk['name'],
            'description' => $pk['description'] ?? '',
            'price'       => $price,
            'regular'     => round($regular, 2),
            'save'        => round(max(0, $regular - $price), 2),
            'image'       => $pk['image'] ? UPLOAD_URL . 'packages/' . $pk['image'] : ($items[0]['image'] ?? ''),
            'available'   => $available,
            'items'       => $items,
        ];
    }
    return $out;
}