<?php
// =========================================
// Lily's Accessories - Site Configuration
// =========================================

// --- Database (XAMPP defaults) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'jewelry_store');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is empty

// --- Site ---
define('SITE_NAME', "Lily's Accessories");
define('BASE_URL', '/lilys-accessories');   // change to '' on a live domain root

// --- Store info (edit here, no admin settings needed) ---
// WhatsApp: orders are sent to this number.
// Digits only, with country code, no + or spaces, no 0 after 961.  Example: 71 123 456 -> '96171123456'
define('WHATSAPP_NUMBER',  '96171270966');
define('INSTAGRAM',        'lilys_accesories___');   // username or full link, e.g. 'lilys.accessories'

// --- Uploads ---
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// --- Errors (turn OFF on live hosting) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}