<?php
// includes/page_header.php - Shared landing page header
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= $metaDesc ?? 'Vyala Software TaskPad - India\'s leading task management software for teams and professionals.' ?>">
    <title><?= $pageTitle ?? 'Vyala Software TaskPad' ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary:       #2563eb;
            --primary-dark:  #1d4ed8;
            --primary-light: #eff6ff;
            --accent:        #f97316;
            --text-dark:     #111827;
            --text-body:     #374151;
            --text-muted:    #6b7280;
            --border:        #e5e7eb;
            --bg-light:      #f9fafb;
            --font:          'Outfit', sans-serif;
            --tr:            all 0.22s ease;
            --radius:        10px;
            --shadow:        0 4px 16px rgba(0,0,0,0.06);
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font);
            background: #fff;
            color: var(--text-body);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* â”€â”€ HEADER â”€â”€ */
        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 72px;
            height: 68px;
            background: #fff;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .brand-logo {
            display: flex; align-items: center; gap: 8px;
            text-decoration: none; user-select: none; flex-shrink: 0;
        }
        .brand-logo svg { flex-shrink: 0; }
        .logo-text-wrapper { display: flex; flex-direction: column; line-height: 1.1; }
        .logo-name { font-size: 16px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.3px; }
        .logo-name span { color: var(--primary); }
        .logo-sub { font-size: 8.5px; font-weight: 700; letter-spacing: 2.5px; text-transform: uppercase; color: var(--primary); margin-top: 1px; }

        .nav-links {
            display: flex; align-items: center; list-style: none; gap: 4px;
        }
        .nav-links > li > a {
            display: flex; align-items: center; gap: 4px;
            padding: 8px 12px; font-size: 14px; font-weight: 500;
            color: var(--text-body); text-decoration: none;
            border-radius: 6px; transition: var(--tr); white-space: nowrap;
        }
        .nav-links > li > a:hover,
        .nav-links > li > a.active { color: var(--primary); background: var(--primary-light); }
        .nav-links > li > a i { width: 13px; height: 13px; }

        .nav-right {
            display: flex; align-items: center; gap: 10px; flex-shrink: 0;
        }
        .btn-demo {
            background: var(--primary); color: #fff;
            padding: 9px 22px; border-radius: 22px;
            font-size: 13.5px; font-weight: 600; text-decoration: none;
            transition: var(--tr); box-shadow: 0 2px 8px rgba(37,99,235,0.25);
        }
        .btn-demo:hover { background: var(--primary-dark); }

        .account-wrapper { position: relative; }
        .btn-account {
            display: flex; align-items: center; gap: 5px;
            border: 1.5px solid var(--primary); color: var(--primary);
            background: transparent; padding: 8px 18px; border-radius: 22px;
            font-size: 13.5px; font-weight: 600; cursor: pointer;
            transition: var(--tr); font-family: var(--font);
        }
        .btn-account:hover { background: var(--primary-light); }
        .btn-account i { width: 13px; height: 13px; }
        .account-drop {
            display: none; position: absolute; right: 0; top: calc(100% + 8px);
            background: #fff; border: 1px solid var(--border); border-radius: 10px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.1); overflow: hidden;
            width: 180px; z-index: 300;
        }
        .account-drop.open { display: block; }
        .account-drop a {
            display: flex; align-items: center; gap: 10px;
            padding: 13px 16px; font-size: 13.5px; font-weight: 500;
            color: var(--text-body); text-decoration: none; transition: var(--tr);
        }
        .account-drop a:hover { background: var(--primary-light); color: var(--primary); }
        .account-drop a i { width: 15px; height: 15px; }

        @media (max-width: 768px) {
            .site-header { padding: 0 20px; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>

<header class="site-header">
    <a href="index.php" class="brand-logo">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" fill="#eff6ff"/>
            <path d="m9 12 2 2 4-4"/>
        </svg>
        <div class="logo-text-wrapper">
            <div class="logo-name">Vyala Software <span>TaskPad</span></div>
            <div class="logo-sub">Be Organized</div>
        </div>
    </a>

    <ul class="nav-links">
        <li><a href="index.php#features">Features</a></li>
        <li><a href="index.php#industries">Industries</a></li>
        <li><a href="index.php#pricing">Pricing</a></li>
        <li><a href="guide.php" class="<?= $currentPage==='guide'?'active':'' ?>">Guide</a></li>
        <li><a href="contact.php" class="<?= $currentPage==='contact'?'active':'' ?>">Contact Us</a></li>
    </ul>

    <div class="nav-right">
        <a href="demo.php" class="btn-demo <?= $currentPage==='demo'?'active':'' ?>">Request Demo</a>
        <div class="account-wrapper">
            <button class="btn-account" id="acc-btn">Account <i data-lucide="chevron-down"></i></button>
            <div class="account-drop" id="acc-drop">
                <a href="login.php"><i data-lucide="log-in"></i> Login</a>
                <a href="login.php?tab=signup"><i data-lucide="user-plus"></i> Sign Up</a>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    const accBtn = document.getElementById('acc-btn');
    const accDrop = document.getElementById('acc-drop');
    if (accBtn && accDrop) {
        accBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            accDrop.classList.toggle('open');
        });
        document.addEventListener('click', function() { accDrop.classList.remove('open'); });
    }
});
</script>

