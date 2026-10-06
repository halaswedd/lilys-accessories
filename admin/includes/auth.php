<?php
// Include this at the top of every protected admin page.
require_once __DIR__ . '/../../config/db.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/admin/login.php');
    exit;
}