<?php
// index.php (Landing Page)
require_once 'jwt.php';

// If already logged in, redirect to the dashboard
$jwtToken = $_COOKIE['vyala_taskpad_jwt_token'] ?? '';
if (verify_jwt($jwtToken)) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Vyala Software TaskPad - Task management software for teams and professionals. India's complete task tracking tool and daily task tracker for projects and workflows.">
    <title>Task Management Software For Teams &amp; Professionals - Vyala Software TaskPad</title>
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

        /* Ã¢â€â‚¬Ã¢â€â‚¬ HEADER Ã¢â€â‚¬Ã¢â€â‚¬ */
        header {
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
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            user-select: none;
            flex-shrink: 0;
        }
        .brand-logo svg { flex-shrink: 0; }
        .logo-text-wrapper { display: flex; flex-direction: column; line-height: 1.1; }
        .logo-name {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.3px;
        }
        .logo-name span { color: var(--primary); }
        .logo-sub {
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--primary);
            margin-top: 1px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            list-style: none;
            gap: 6px;
        }
        .nav-links > li { position: relative; }
        .nav-links > li > a {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 500;
            color: var(--text-body);
            text-decoration: none;
            border-radius: 6px;
            transition: var(--tr);
            white-space: nowrap;
        }
        .nav-links > li > a:hover { color: var(--primary); background: var(--primary-light); }
        .nav-links > li > a i { width: 13px; height: 13px; }

        /* Dropdown */
        .dropdown-menu {
            display: none;
            position: absolute;
            top: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.1);
            padding: 16px;
            min-width: 640px;
            z-index: 300;
            animation: dropFade 0.18s ease;
        }
        .nav-links > li:hover .dropdown-menu { display: grid; }
        .dropdown-menu.cols-2 { grid-template-columns: 1fr 1fr; gap: 4px; min-width: 340px; }
        .dropdown-menu.cols-3 { grid-template-columns: 1fr 1fr 1fr; gap: 4px; }
        .dropdown-section-title {
            grid-column: 1 / -1;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: 4px 8px 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 6px;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 7px;
            text-decoration: none;
            color: var(--text-body);
            font-size: 13px;
            font-weight: 500;
            transition: var(--tr);
        }
        .dropdown-item:hover { background: var(--primary-light); color: var(--primary); }
        .dropdown-item i { width: 15px; height: 15px; color: var(--primary); flex-shrink: 0; }

        @keyframes dropFade {
            from { opacity: 0; transform: translateX(-50%) translateY(-6px); }
            to   { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }
        .btn-demo {
            background: var(--primary);
            color: #fff;
            padding: 9px 22px;
            border-radius: 22px;
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--tr);
            box-shadow: 0 2px 8px rgba(37,99,235,0.25);
        }
        .btn-demo:hover { background: var(--primary-dark); }

        .account-wrapper { position: relative; }
        .btn-account {
            display: flex;
            align-items: center;
            gap: 5px;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            background: transparent;
            padding: 8px 18px;
            border-radius: 22px;
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--tr);
            font-family: var(--font);
        }
        .btn-account:hover { background: var(--primary-light); }
        .btn-account i { width: 13px; height: 13px; }
        .account-drop {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            width: 180px;
            z-index: 300;
            animation: dropFade 0.18s ease;
        }
        .account-drop.open { display: block; }
        .account-drop a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            font-size: 13.5px;
            font-weight: 500;
            color: var(--text-body);
            text-decoration: none;
            transition: var(--tr);
        }
        .account-drop a:hover { background: var(--primary-light); color: var(--primary); }
        .account-drop a i { width: 15px; height: 15px; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ HERO Ã¢â€â‚¬Ã¢â€â‚¬ */
        .hero {
            display: flex;
            align-items: flex-start;
            gap: 48px;
            padding: 64px 72px 72px;
            flex: 1;
            background: linear-gradient(160deg, #fff 60%, #eff6ff 100%);
        }
        .hero-left {
            flex: 1.15;
            display: flex;
            flex-direction: column;
            padding-top: 20px;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 20px;
            margin-bottom: 20px;
            width: fit-content;
            border: 1px solid #bfdbfe;
        }
        .hero-badge i { width: 13px; height: 13px; }
        .hero-left h1 {
            font-size: 42px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.2;
            letter-spacing: -0.5px;
            margin-bottom: 18px;
        }
        .hero-left h1 span { color: var(--primary); }
        .hero-left > p {
            font-size: 16px;
            color: var(--text-muted);
            line-height: 1.7;
            margin-bottom: 32px;
            max-width: 520px;
        }
        .hero-btns {
            display: flex;
            gap: 14px;
            margin-bottom: 36px;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: var(--primary);
            color: #fff;
            padding: 13px 30px;
            border-radius: 26px;
            font-size: 14.5px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--tr);
            box-shadow: 0 4px 14px rgba(37,99,235,0.3);
        }
        .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .btn-outline {
            border: 1.5px solid var(--primary);
            color: var(--primary);
            padding: 13px 30px;
            border-radius: 26px;
            font-size: 14.5px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--tr);
        }
        .btn-outline:hover { background: var(--primary-light); }

        .store-badges { display: flex; gap: 12px; flex-wrap: wrap; }
        .store-badge {
            display: flex;
            align-items: center;
            gap: 9px;
            background: #0f172a;
            color: #fff;
            padding: 8px 16px;
            border-radius: 9px;
            text-decoration: none;
            transition: var(--tr);
            min-width: 140px;
            border: 1px solid #1e293b;
        }
        .store-badge:hover { background: #1e293b; }
        .store-badge i { width: 20px; height: 20px; flex-shrink: 0; }
        .store-badge-text { display: flex; flex-direction: column; line-height: 1.15; }
        .store-badge-text .small { font-size: 8px; font-weight: 400; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.5px; }
        .store-badge-text .big   { font-size: 13px; font-weight: 700; }

        /* Hero right form */
        .hero-right {
            flex: 0.88;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 8px 40px rgba(37,99,235,0.08);
            padding: 32px 28px;
            flex-shrink: 0;
        }
        .form-title {
            font-size: 19px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 6px;
        }
        .form-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 22px;
        }
        .form-row { display: flex; gap: 14px; }
        .form-row .fg { flex: 1; }
        .fg { margin-bottom: 14px; }
        .fg input,
        .fg select,
        .fg textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 10px 13px;
            font-family: var(--font);
            font-size: 13.5px;
            color: var(--text-dark);
            background: #fff;
            outline: none;
            transition: var(--tr);
        }
        .fg input:focus, .fg select:focus, .fg textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .fg input::placeholder, .fg textarea::placeholder { color: #9ca3af; }
        .fg select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
        .fg select option:first-child { color: #9ca3af; }

        .phone-row { display: flex; gap: 10px; }
        .phone-row .cc-wrap { flex: 0 0 130px; }
        .phone-row .ph-wrap { flex: 1; }

        .captcha-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .captcha-left { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 500; }
        .captcha-left input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; accent-color: var(--primary); }
        .captcha-logo { display: flex; flex-direction: column; align-items: center; font-size: 8px; color: var(--text-muted); }
        .captcha-logo img { height: 22px; margin-bottom: 2px; }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 13px;
            font-family: var(--font);
            font-size: 14.5px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--tr);
            box-shadow: 0 4px 12px rgba(37,99,235,0.25);
            letter-spacing: 0.2px;
        }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-1px); }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ SECTION COMMONS Ã¢â€â‚¬Ã¢â€â‚¬ */
        section { padding: 80px 72px; border-top: 1px solid var(--border); }
        .sec-header { text-align: center; max-width: 640px; margin: 0 auto 52px; }
        .sec-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 5px 14px;
            border-radius: 20px;
            margin-bottom: 14px;
        }
        .sec-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.4px;
            margin-bottom: 14px;
            line-height: 1.25;
        }
        .sec-header h2 span { color: var(--primary); }
        .sec-header p {
            font-size: 15.5px;
            color: var(--text-muted);
            line-height: 1.65;
        }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ STATS STRIP Ã¢â€â‚¬Ã¢â€â‚¬ */
        .stats-strip {
            display: flex;
            justify-content: center;
            gap: 60px;
            padding: 36px 72px;
            background: var(--primary);
            text-align: center;
        }
        .stat-item .num { font-size: 34px; font-weight: 800; color: #fff; line-height: 1; }
        .stat-item .lbl { font-size: 13px; color: rgba(255,255,255,0.75); margin-top: 4px; font-weight: 500; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ FEATURES Ã¢â€â‚¬Ã¢â€â‚¬ */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
        }
        .feature-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 20px;
            text-align: center;
            transition: var(--tr);
        }
        .feature-card:hover {
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(37,99,235,0.12);
            transform: translateY(-4px);
        }
        .f-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }
        .f-icon i { width: 24px; height: 24px; }
        .feature-card h3 { font-size: 15px; font-weight: 700; color: var(--text-dark); margin-bottom: 9px; }
        .feature-card p  { font-size: 13px; color: var(--text-muted); line-height: 1.55; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ INDUSTRIES Ã¢â€â‚¬Ã¢â€â‚¬ */
        #industries { background: var(--bg-light); }
        .industries-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }
        .industry-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px;
            display: flex;
            gap: 16px;
            transition: var(--tr);
        }
        .industry-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
            transform: translateY(-3px);
        }
        .i-icon {
            width: 44px; height: 44px;
            border-radius: 10px;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .i-icon i { width: 20px; height: 20px; }
        .industry-body h3 { font-size: 15px; font-weight: 700; color: var(--text-dark); margin-bottom: 7px; }
        .industry-body p  { font-size: 13px; color: var(--text-muted); line-height: 1.55; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ PRICING Ã¢â€â‚¬Ã¢â€â‚¬ */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 26px;
            max-width: 1020px;
            margin: 0 auto;
        }
        .pricing-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 38px 28px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: var(--tr);
        }
        .pricing-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.07); }
        .pricing-card.featured {
            border: 2px solid var(--primary);
            box-shadow: 0 8px 32px rgba(37,99,235,0.14);
        }
        .pop-badge {
            position: absolute;
            top: -13px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 16px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
        }
        .plan-name { font-size: 18px; font-weight: 700; color: var(--text-dark); margin-bottom: 4px; }
        .plan-desc { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; }
        .plan-price { margin-bottom: 24px; }
        .plan-price .amount { font-size: 40px; font-weight: 800; color: var(--text-dark); }
        .plan-price .period { font-size: 14px; color: var(--text-muted); }
        .plan-features { list-style: none; display: flex; flex-direction: column; gap: 12px; margin-bottom: 28px; }
        .plan-features li { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: var(--text-body); }
        .plan-features li i { width: 16px; height: 16px; color: #10b981; flex-shrink: 0; }
        .plan-features li.disabled i { color: #d1d5db; }
        .plan-features li.disabled { color: var(--text-muted); }
        .btn-plan {
            margin-top: auto;
            display: block;
            text-align: center;
            padding: 12px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--tr);
        }
        .btn-plan.outline { border: 1.5px solid var(--primary); color: var(--primary); }
        .btn-plan.outline:hover { background: var(--primary-light); }
        .btn-plan.fill { background: var(--primary); color: #fff; box-shadow: 0 4px 12px rgba(37,99,235,0.25); }
        .btn-plan.fill:hover { background: var(--primary-dark); }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ BLOG Ã¢â€â‚¬Ã¢â€â‚¬ */
        #blog { background: var(--bg-light); }
        .blog-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .blog-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: var(--tr);
        }
        .blog-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.07); }
        .blog-img {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .blog-img i { width: 52px; height: 52px; }
        .blog-body { padding: 22px; flex: 1; display: flex; flex-direction: column; }
        .blog-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary);
            background: var(--primary-light);
            padding: 3px 10px;
            border-radius: 20px;
            margin-bottom: 12px;
            width: fit-content;
        }
        .blog-body h3 { font-size: 15.5px; font-weight: 700; color: var(--text-dark); margin-bottom: 9px; line-height: 1.4; }
        .blog-body p  { font-size: 13px; color: var(--text-muted); line-height: 1.55; margin-bottom: 16px; flex: 1; }
        .blog-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary);
            text-decoration: none;
            transition: var(--tr);
        }
        .blog-link i { width: 14px; height: 14px; transition: var(--tr); }
        .blog-link:hover { color: var(--primary-dark); }
        .blog-link:hover i { transform: translateX(3px); }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ FOOTER Ã¢â€â‚¬Ã¢â€â‚¬ */
        footer {
            background: #111827;
            color: rgba(255,255,255,0.6);
            padding: 48px 72px 30px;
        }
        .footer-top {
            display: grid;
            grid-template-columns: 1.4fr 1fr 1fr 1fr;
            gap: 48px;
            margin-bottom: 40px;
        }
        .footer-brand .brand-name {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .footer-brand .brand-name span { color: #60a5fa; }
        .footer-brand p { font-size: 13px; line-height: 1.6; margin-bottom: 16px; }
        .footer-social { display: flex; gap: 10px; flex-wrap: wrap; }
        .footer-social a {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.7);
            transition: var(--tr);
            text-decoration: none;
        }
        .footer-social a:hover { background: var(--primary); color: #fff; }
        .footer-social a i { width: 15px; height: 15px; }
        .footer-col h4 { font-size: 13px; font-weight: 700; color: #fff; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; }
        .footer-col ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .footer-col ul li a { font-size: 13px; color: rgba(255,255,255,0.55); text-decoration: none; transition: var(--tr); }
        .footer-col ul li a:hover { color: #fff; }
        .footer-divider { border: none; border-top: 1px solid rgba(255,255,255,0.08); margin-bottom: 22px; }
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
        }
        .footer-bottom a { color: rgba(255,255,255,0.5); text-decoration: none; }
        .footer-bottom a:hover { color: #fff; }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ WHATSAPP Ã¢â€â‚¬Ã¢â€â‚¬ */
        .wa-float {
            position: fixed;
            bottom: 28px;
            right: 28px;
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #25d366;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            z-index: 999;
            text-decoration: none;
            transition: var(--tr);
        }
        .wa-float:hover { transform: scale(1.08); }

        /* Ã¢â€â‚¬Ã¢â€â‚¬ RESPONSIVE Ã¢â€â‚¬Ã¢â€â‚¬ */
        @media (max-width: 1100px) {
            header { padding: 0 32px; }
            .nav-links { display: none; }
            .hero { flex-direction: column; padding: 40px 32px; }
            section { padding: 60px 32px; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .industries-grid { grid-template-columns: 1fr 1fr; }
            .pricing-grid { grid-template-columns: 1fr; max-width: 420px; }
            .blog-grid { grid-template-columns: 1fr; }
            .footer-top { grid-template-columns: 1fr 1fr; }
            .stats-strip { gap: 30px; padding: 30px 32px; flex-wrap: wrap; }
        }
        @media (max-width: 640px) {
            .hero-left h1 { font-size: 28px; }
            .industries-grid { grid-template-columns: 1fr; }
            .footer-top { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     HEADER NAVIGATION
     Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<header>
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
        <li class="has-dropdown">
            <a href="#features">Features <i data-lucide="chevron-down"></i></a>
            <div class="dropdown-menu cols-3">
                <span class="dropdown-section-title">Features</span>
                <a href="#features" class="dropdown-item"><i data-lucide="check-square"></i> Task Management</a>
                <a href="#features" class="dropdown-item"><i data-lucide="folder"></i> Project Management</a>
                <a href="#features" class="dropdown-item"><i data-lucide="message-circle"></i> Chat &amp; Discussions</a>
                <a href="#features" class="dropdown-item"><i data-lucide="paperclip"></i> Doc &amp; Attachments</a>
                <a href="#features" class="dropdown-item"><i data-lucide="git-branch"></i> Workflow Management</a>
                <a href="#features" class="dropdown-item"><i data-lucide="clock"></i> Time Sheet</a>
                <a href="#features" class="dropdown-item"><i data-lucide="bar-chart-2"></i> Reports</a>
                <a href="#features" class="dropdown-item"><i data-lucide="puzzle"></i> Integrations</a>
                <a href="#features" class="dropdown-item"><i data-lucide="users"></i> Attendance Management</a>
            </div>
        </li>
        <li class="has-dropdown">
            <a href="#industries">Industries <i data-lucide="chevron-down"></i></a>
            <div class="dropdown-menu cols-2">
                <span class="dropdown-section-title">Industries We Serve</span>
                <a href="#industries" class="dropdown-item"><i data-lucide="building-2"></i> Manufacturing</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="home"></i> Real Estate</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="calculator"></i> CA / CS / CFA</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="calendar"></i> Event Management</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="code-2"></i> IT Industry</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="graduation-cap"></i> Education</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="scale"></i> Law Firms</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="briefcase"></i> Consulting</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="heart-pulse"></i> Healthcare</a>
                <a href="#industries" class="dropdown-item"><i data-lucide="phone"></i> BPO &amp; KPO</a>
            </div>
        </li>
        <li><a href="index.php#pricing">Pricing</a></li>
        <li><a href="index.php#blog">Blog</a></li>
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="guide.php">Guide</a></li>
    </ul>

    <div class="nav-right">
        <a href="demo.php" class="btn-demo">Request Demo</a>
        <div class="account-wrapper">
            <button class="btn-account" id="acc-btn">Account <i data-lucide="chevron-down"></i></button>
            <div class="account-drop" id="acc-drop">
                <a href="login.php"><i data-lucide="log-in"></i> Login</a>
                <a href="login.php?tab=signup"><i data-lucide="user-plus"></i> Sign Up</a>
            </div>
        </div>
    </div>
</header>


<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     HERO SECTION
     Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â Ã¢â€¢Â  -->
<div class="hero">
    <div class="hero-left">
        <div class="hero-badge"><i data-lucide="star"></i> India's #1 Task Management Software</div>
        <h1>Task Management Software for <span>Teams &amp; Professionals</span></h1>
        <p>India's complete task management system with a task tracking tool and daily task tracker for tasks, projects, and workflows - built for growing businesses.</p>

        <div class="hero-btns">
            <a href="demo.php" class="btn-primary">Request A Demo</a>
            <a href="login.php?tab=signup" class="btn-outline">Start A Free Trial</a>
        </div>

        <div class="store-badges">
            <a href="#" class="store-badge">
                <i data-lucide="smartphone"></i>
                <div class="store-badge-text">
                    <span class="small">Download on the</span>
                    <span class="big">App Store</span>
                </div>
            </a>
            <a href="#" class="store-badge">
                <i data-lucide="play"></i>
                <div class="store-badge-text">
                    <span class="small">GET IT ON</span>
                    <span class="big">Google Play</span>
                </div>
            </a>
        </div>
    </div>

    <div class="hero-right">
        <div class="form-title">Request A Free Demo</div>
        <div class="form-subtitle">Fill in your details and our team will get in touch.</div>
        <form onsubmit="event.preventDefault(); alert('Demo request submitted! Our team will contact you shortly.');">
            <div class="form-row">
                <div class="fg"><input type="text" placeholder="Full Name *" required></div>
                <div class="fg"><input type="text" placeholder="Company Name *" required></div>
            </div>
            <div class="fg"><input type="email" placeholder="Your Email *" required></div>

            <div class="phone-row fg">
                <div class="cc-wrap">
                    <select>
                        <option value="+91" selected>India (+91)</option>
                        <option value="+1">United States (+1)</option>
                        <option value="+44">United Kingdom (+44)</option>
                        <option value="+61">Australia (+61)</option>
                        <option value="+971">UAE (+971)</option>
                        <option value="+65">Singapore (+65)</option>
                        <option value="+60">Malaysia (+60)</option>
                        <option value="+49">Germany (+49)</option>
                        <option value="+33">France (+33)</option>
                        <option value="+81">Japan (+81)</option>
                        <option value="+86">China (+86)</option>
                        <option value="+55">Brazil (+55)</option>
                        <option value="+7">Russia (+7)</option>
                        <option value="+27">South Africa (+27)</option>
                        <option value="+234">Nigeria (+234)</option>
                        <option value="+254">Kenya (+254)</option>
                        <option value="+880">Bangladesh (+880)</option>
                        <option value="+92">Pakistan (+92)</option>
                        <option value="+94">Sri Lanka (+94)</option>
                        <option value="+977">Nepal (+977)</option>
                        <option value="+95">Myanmar (+95)</option>
                        <option value="+66">Thailand (+66)</option>
                        <option value="+84">Vietnam (+84)</option>
                        <option value="+63">Philippines (+63)</option>
                        <option value="+62">Indonesia (+62)</option>
                        <option value="+82">South Korea (+82)</option>
                        <option value="+852">Hong Kong (+852)</option>
                        <option value="+886">Taiwan (+886)</option>
                        <option value="+966">Saudi Arabia (+966)</option>
                        <option value="+974">Qatar (+974)</option>
                        <option value="+965">Kuwait (+965)</option>
                        <option value="+973">Bahrain (+973)</option>
                        <option value="+968">Oman (+968)</option>
                        <option value="+962">Jordan (+962)</option>
                        <option value="+961">Lebanon (+961)</option>
                        <option value="+20">Egypt (+20)</option>
                        <option value="+212">Morocco (+212)</option>
                        <option value="+216">Tunisia (+216)</option>
                        <option value="+234">Nigeria (+234)</option>
                        <option value="+233">Ghana (+233)</option>
                        <option value="+251">Ethiopia (+251)</option>
                        <option value="+34">Spain (+34)</option>
                        <option value="+39">Italy (+39)</option>
                        <option value="+31">Netherlands (+31)</option>
                        <option value="+46">Sweden (+46)</option>
                        <option value="+47">Norway (+47)</option>
                        <option value="+45">Denmark (+45)</option>
                        <option value="+358">Finland (+358)</option>
                        <option value="+32">Belgium (+32)</option>
                        <option value="+41">Switzerland (+41)</option>
                        <option value="+43">Austria (+43)</option>
                        <option value="+48">Poland (+48)</option>
                        <option value="+420">Czech Republic (+420)</option>
                        <option value="+36">Hungary (+36)</option>
                        <option value="+30">Greece (+30)</option>
                        <option value="+40">Romania (+40)</option>
                        <option value="+1">Canada (+1)</option>
                        <option value="+52">Mexico (+52)</option>
                        <option value="+54">Argentina (+54)</option>
                        <option value="+57">Colombia (+57)</option>
                        <option value="+56">Chile (+56)</option>
                        <option value="+51">Peru (+51)</option>
                        <option value="+58">Venezuela (+58)</option>
                        <option value="+593">Ecuador (+593)</option>
                        <option value="+64">New Zealand (+64)</option>
                    </select>
                </div>
                <div class="ph-wrap">
                    <input type="tel" placeholder="Your Phone *" maxlength="15" required>
                </div>
            </div>

            <div class="fg">
                <select id="sel-users" onchange="this.style.color='#111827'" required style="color:#9ca3af;">
                    <option value="" disabled selected>Select Number of Users *</option>
                    <option value="1-20">1 - 20 Users</option>
                    <option value="21-50">21 - 50 Users</option>
                    <option value="51-100">51 - 100 Users</option>
                    <option value="101-250">101 - 250 Users</option>
                    <option value="251+">251+ Users</option>
                </select>
            </div>

            <div class="fg">
                <select id="sel-industry" onchange="this.style.color='#111827'" required style="color:#9ca3af;">
                    <option value="" disabled selected>Select Industry *</option>
                    <option>Software &amp; IT</option>
                    <option>Construction &amp; Real Estate</option>
                    <option>Manufacturing</option>
                    <option>CA / CS / CFA / Accounting</option>
                    <option>Marketing &amp; Agency</option>
                    <option>Healthcare &amp; Hospitals</option>
                    <option>Education</option>
                    <option>Law Firms</option>
                    <option>BPO &amp; KPO</option>
                    <option>Event Management</option>
                    <option>Retail &amp; E-Commerce</option>
                    <option>Others</option>
                </select>
            </div>

            <div class="fg">
                <textarea rows="3" placeholder="Your Message *" required></textarea>
            </div>

            <div class="captcha-row">
                <div class="captcha-left">
                    <input type="checkbox" required>
                    <span>I'm not a robot</span>
                </div>
                <div class="captcha-logo">
                    <img src="https://www.gstatic.com/recaptcha/api2/logo_48.png" alt="reCAPTCHA">
                    <span>reCAPTCHA</span>
                </div>
            </div>

            <button type="submit" class="btn-submit">Submit</button>
        </form>
    </div>
</div>


<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     STATS STRIP
     Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<div class="stats-strip">
    <div class="stat-item"><div class="num">10,000+</div><div class="lbl">Active Users</div></div>
    <div class="stat-item"><div class="num">500+</div><div class="lbl">Companies Served</div></div>
    <div class="stat-item"><div class="num">4.6Ã¢Ëœâ€¦</div><div class="lbl">App Store Rating</div></div>
    <div class="stat-item"><div class="num">15 Days</div><div class="lbl">Free Trial</div></div>
    <div class="stat-item"><div class="num">99.9%</div><div class="lbl">Uptime SLA</div></div>
</div>


<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     FEATURES SECTION
     Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<section id="features">
    <div class="sec-header">
        <div class="sec-eyebrow">Features</div>
        <h2>Everything Your Team Needs to <span>Stay Organized</span></h2>
        <p>A comprehensive task management system with real-time tracking, collaboration tools, and analytics Ã¢â‚¬â€ built for high-performing teams.</p>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="check-square"></i></div>
            <h3>Task Management</h3>
            <p>Assign tasks, set priority codes, track due dates, and monitor completion percentages in real time.</p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="folder-open"></i></div>
            <h3>Project Management</h3>
            <p>Organize projects with milestones, assign teams, and track overall project progress from a single view.</p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="message-square"></i></div>
            <h3>Chat &amp; Discussions</h3>
            <p>Collaborate in real time Ã¢â‚¬â€ post comments, share file attachments, toggle task and general channels.</p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="paperclip"></i></div>
            <h3>Doc &amp; Attachments</h3>
            <p>Upload, store, and manage documents and file attachments linked directly to tasks and projects.</p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="git-branch"></i></div>
            <h3>Workflow Management</h3>
            <p>Build and automate custom workflows with approvals, stages, and conditional task routing.</p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="clock"></i></div>
            <h3>Attendance &amp; Timesheets</h3>
            <p>Log work hours, track check-ins, run productivity reports, and ensure clean team cycles.</p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="bar-chart-2"></i></div>
            <h3>Advanced Reports</h3>
            <p>Generate priority summaries, completed vs incomplete line graphs, and project status ratio charts.</p>
        </div>
        <div class="feature-card">
            <div class="f-icon"><i data-lucide="bell"></i></div>
            <h3>Smart Notifications</h3>
            <p>Get real-time alerts for task assignments, deadline reminders, and team updates via app and email.</p>
        </div>
    </div>
</section>


<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     INDUSTRIES SECTION
     Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<section id="industries">
    <div class="sec-header">
        <div class="sec-eyebrow">Industries</div>
        <h2>Industries We <span>Serve</span></h2>
        <p>Vyala Software TaskPad is trusted by professional services, agencies, developers, and firms across India.</p>
    </div>
    <div class="industries-grid">
        <div class="industry-card">
            <div class="i-icon"><i data-lucide="building-2"></i></div>
            <div class="industry-body">
                <h3>Manufacturing</h3>
                <p>Track production schedules, quality control tasks, machine maintenance checklists, and shift-wise assignments.</p>
            </div>
        </div>
        <div class="industry-card">
            <div class="i-icon"><i data-lucide="home"></i></div>
            <div class="industry-body">
                <h3>Construction &amp; Real Estate</h3>
                <p>Manage site drafts, AutoCAD layout confirmations, boundary surveys, and builder documentation checklists.</p>
            </div>
        </div>
        <div class="industry-card">
            <div class="i-icon"><i data-lucide="calculator"></i></div>
            <div class="industry-body">
                <h3>CA / CS / CFA Firms</h3>
                <p>Track client compliance deadlines, GST filing schedules, audit milestones, and documentation handoffs.</p>
            </div>
        </div>
        <div class="industry-card">
            <div class="i-icon"><i data-lucide="code-2"></i></div>
            <div class="industry-body">
                <h3>IT &amp; Software Agencies</h3>
                <p>Orchestrate engineering sprints, coordinate DevOps releases, track developer productivity, and log ticket timelines.</p>
            </div>
        </div>
        <div class="industry-card">
            <div class="i-icon"><i data-lucide="graduation-cap"></i></div>
            <div class="industry-body">
                <h3>Education Sector</h3>
                <p>Manage curriculum planning, faculty assignments, exam schedules, and institutional administrative tasks.</p>
            </div>
        </div>
        <div class="industry-card">
            <div class="i-icon"><i data-lucide="heart-pulse"></i></div>
            <div class="industry-body">
                <h3>Healthcare &amp; Hospitals</h3>
                <p>Coordinate patient care tasks, staff duty rosters, compliance checklists, and procurement workflows.</p>
            </div>
        </div>
    </div>
</section>


<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     PRICING SECTION
     Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<section id="pricing">
    <div class="sec-header">
        <div class="sec-eyebrow">Pricing</div>
        <h2>Simple, <span>Transparent</span> Pricing</h2>
        <p>Choose the plan that fits your team. Start with a risk-free 15-day trial and upgrade as you scale.</p>
    </div>
    <div class="pricing-grid">
        <!-- Basic -->
        <div class="pricing-card">
            <div class="plan-name">Basic Trial</div>
            <div class="plan-desc">For small teams getting started</div>
            <div class="plan-price">
                <span class="amount">Free</span>
                <span class="period"> / 15 Days</span>
            </div>
            <ul class="plan-features">
                <li><i data-lucide="check"></i> Up to 20 team members</li>
                <li><i data-lucide="check"></i> 10 active projects</li>
                <li><i data-lucide="check"></i> Standard task tracking</li>
                <li><i data-lucide="check"></i> Basic discussions feed</li>
                <li class="disabled"><i data-lucide="x"></i> Analytics &amp; reports</li>
                <li class="disabled"><i data-lucide="x"></i> API integrations</li>
            </ul>
            <a href="login.php" class="btn-plan outline">Start Free Trial</a>
        </div>

        <!-- Professional (featured) -->
        <div class="pricing-card featured">
            <div class="pop-badge">Most Popular</div>
            <div class="plan-name">Professional</div>
            <div class="plan-desc">For growth-focused companies</div>
            <div class="plan-price">
                <span class="amount">Ã¢â€šÂ¹200</span>
                <span class="period"> / user / month</span>
            </div>
            <ul class="plan-features">
                <li><i data-lucide="check"></i> Unlimited team members</li>
                <li><i data-lucide="check"></i> Unlimited projects</li>
                <li><i data-lucide="check"></i> Priority support</li>
                <li><i data-lucide="check"></i> Analytics &amp; chart reports</li>
                <li><i data-lucide="check"></i> Custom workflow templates</li>
                <li><i data-lucide="check"></i> 5GB storage per user</li>
            </ul>
            <a href="login.php" class="btn-plan fill">Get Professional</a>
        </div>

        <!-- Enterprise -->
        <div class="pricing-card">
            <div class="plan-name">Enterprise</div>
            <div class="plan-desc">For large organisations &amp; corporates</div>
            <div class="plan-price">
                <span class="amount">Custom</span>
                <span class="period"> / annual</span>
            </div>
            <ul class="plan-features">
                <li><i data-lucide="check"></i> Dedicated database</li>
                <li><i data-lucide="check"></i> Custom integration APIs</li>
                <li><i data-lucide="check"></i> 99.9% uptime SLA</li>
                <li><i data-lucide="check"></i> 24/7 dedicated support</li>
                <li><i data-lucide="check"></i> White-label options</li>
                <li><i data-lucide="check"></i> On-premise deployment</li>
            </ul>
            <a href="login.php" class="btn-plan outline">Contact Sales</a>
        </div>
    </div>
</section>


<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     BLOG SECTION
     Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<section id="blog">
    <div class="sec-header">
        <div class="sec-eyebrow">Blog</div>
        <h2>Productivity &amp; <span>Planning Insights</span></h2>
        <p>Articles, guides, and tips on optimising team collaboration and task management workflows.</p>
    </div>
    <div class="blog-grid">
        <div class="blog-card">
            <div class="blog-img" style="background:linear-gradient(135deg,#eff6ff,#bfdbfe);">
                <i data-lucide="book-open" style="color:#2563eb;"></i>
            </div>
            <div class="blog-body">
                <span class="blog-tag">Collaboration Ã¢â‚¬Â¢ 5 min</span>
                <h3>5 Ways Teams Streamline Approvals &amp; NOC Clearances</h3>
                <p>Discover how engineering crews collaborate dynamically on layout drawings and blueprint checks using Vyala Software TaskPad.</p>
                <a href="login.php" class="blog-link">Read Article <i data-lucide="arrow-right"></i></a>
            </div>
        </div>
        <div class="blog-card">
            <div class="blog-img" style="background:linear-gradient(135deg,#f0fdf4,#bbf7d0);">
                <i data-lucide="clock" style="color:#16a34a;"></i>
            </div>
            <div class="blog-body">
                <span class="blog-tag">Productivity Ã¢â‚¬Â¢ 4 min</span>
                <h3>The Power of Daily Timesheets &amp; Logging Work Hours</h3>
                <p>How recording project cycles keeps budgets in check and helps managers identify engineering bottlenecks instantly.</p>
                <a href="login.php" class="blog-link">Read Article <i data-lucide="arrow-right"></i></a>
            </div>
        </div>
        <div class="blog-card">
            <div class="blog-img" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
                <i data-lucide="check-square" style="color:#d97706;"></i>
            </div>
            <div class="blog-body">
                <span class="blog-tag">Guides Ã¢â‚¬Â¢ 6 min</span>
                <h3>RERA Registration Blueprint &amp; Document Checklists</h3>
                <p>A complete checklist of files, layout certificates, and clearance approvals required for real estate project launch.</p>
                <a href="login.php" class="blog-link">Read Article <i data-lucide="arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>


<!-- Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â
     FOOTER
     Ã¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢ÂÃ¢â€¢Â -->
<footer>
    <div class="footer-top">
        <div class="footer-brand">
            <div class="brand-name">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" fill="rgba(96,165,250,0.15)"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                Vyala Software <span>TaskPad</span>
            </div>
            <p>India's complete task management system built for teams and professionals. Manage tasks, projects, discussions, and workflows in one place.</p>
            <div class="footer-social">
                <a href="#" title="Facebook"><i data-lucide="facebook"></i></a>
                <a href="#" title="LinkedIn"><i data-lucide="linkedin"></i></a>
                <a href="#" title="Twitter"><i data-lucide="twitter"></i></a>
                <a href="#" title="Instagram"><i data-lucide="instagram"></i></a>
                <a href="#" title="YouTube"><i data-lucide="youtube"></i></a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Features</h4>
            <ul>
                <li><a href="#features">Task Management</a></li>
                <li><a href="#features">Project Management</a></li>
                <li><a href="#features">Discussions</a></li>
                <li><a href="#features">Timesheets</a></li>
                <li><a href="#features">Reports</a></li>
                <li><a href="#features">Integrations</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Company</h4>
            <ul>
                <li><a href="index.php#features">About Us</a></li>
                <li><a href="index.php#blog">Blog</a></li>
                <li><a href="contact.php">Careers</a></li>
                <li><a href="contact.php">Contact Us</a></li>
                <li><a href="guide.php">Guide Manual</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Legal</h4>
            <ul>
                <li><a href="login.php">Privacy Policy</a></li>
                <li><a href="login.php">Terms of Service</a></li>
                <li><a href="login.php">Cookie Policy</a></li>
                <li><a href="login.php">Refund Policy</a></li>
            </ul>
        </div>
    </div>
    <hr class="footer-divider">
    <div class="footer-bottom">
        <span>&copy; <?= date('Y') ?> Vyala Software TaskPad. All rights reserved.</span>
        <span>Designed &amp; developed by Vyala Software</span>
    </div>
</footer>


<!-- 💬 WHATSAPP FLOATING BUTTON — NUMBER இங்கே மாற்றவும் (wa.me/91XXXXXXXXXX) -->
<a href="https://wa.me/919344376416" class="wa-float" title="Chat on WhatsApp">
    <svg viewBox="0 0 24 24" width="28" height="28" fill="#fff">
        <path d="M12.031 2C6.49 2 2 6.48 2 12.018a10.01 10.01 0 0 0 1.54 5.347L2 22l4.8-.934a9.92 9.92 0 0 0 5.232 1.48C17.572 22.546 22 18.067 22 12.529c0-2.678-1.04-5.197-2.937-7.094A9.957 9.957 0 0 0 12.031 2zm5.943 14.624c-.258.726-1.49 1.39-2.037 1.464-.536.074-1.04.174-3.547-.79-2.994-1.159-4.888-4.226-5.034-4.42-.146-.194-1.186-1.575-1.186-3.005s.747-2.135.1-2.43c-.647-.294-1.434-.341-2.056.094a2.77 2.77 0 0 0-.969 2.04c0 1.254.548 2.464.703 2.668.155.204 2.282 3.49 5.548 4.9.776.335 1.381.536 1.853.685.778.248 1.487.21 2.046.127.631-.093 1.888-.773 2.152-1.52.266-.748.266-1.388.183-1.522-.084-.134-.302-.215-.64-.38z"/>
    </svg>
</a>


<script>
    // Initialize Lucide icons
    lucide.createIcons();

    // Account dropdown toggle
    const accBtn = document.getElementById('acc-btn');
    const accDrop = document.getElementById('acc-drop');
    if (accBtn && accDrop) {
        accBtn.addEventListener('click', e => {
            e.stopPropagation();
            accDrop.classList.toggle('open');
        });
        document.addEventListener('click', () => accDrop.classList.remove('open'));
    }
</script>
</body>
</html>


