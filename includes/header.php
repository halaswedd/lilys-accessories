<?php
// Optional: $pageTitle
$pageTitle = $pageTitle ?? SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="Handmade bracelets, necklaces and accessories. Custom Italian charm bracelets. Delivery all over Lebanon.">
    <meta name="theme-color" content="#F27C96">
    <script>
    (function () {
        document.documentElement.classList.add('js');
        // safety net: never leave sections hidden if scripts fail
        setTimeout(function () {
            var f = document.querySelectorAll('.fade-section');
            for (var i = 0; i < f.length; i++) f[i].classList.add('in');
        }, 4000);
    })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500&family=Mrs+Saint+Delafield&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
</head>
<body>

<header class="site-header" id="top">
    <a class="logo logo-round" href="<?= BASE_URL ?>/" aria-label="<?= e(SITE_NAME) ?>">
        <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="<?= e(SITE_NAME) ?>">
    </a>

    <nav class="site-nav">
        <a href="#categories">Categories</a>
        <a href="#shop">Shop</a>
    </nav>

    <div class="header-actions">
        <button type="button" class="menu-btn" id="menuBtn" aria-label="Menu" aria-haspopup="true" aria-expanded="false" aria-controls="siteMenu">
            <span class="menu-lines" aria-hidden="true"><i></i><i></i></span>
        </button>
        <button type="button" class="cart-btn" id="cartBtn" aria-label="Open cart">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 7h12l-1 12H7L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>
            <span class="cart-count" id="cartCount" hidden>0</span>
        </button>
    </div>

    <div class="site-menu" id="siteMenu" role="menu">
        <a href="#categories" role="menuitem">Categories</a>
        <a href="#shop" role="menuitem">Shop</a>
    </div>
</header>