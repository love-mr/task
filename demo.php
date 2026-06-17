<?php
$pageTitle  = 'Request a Free Demo â€” Vyala Software TaskPad';
$metaDesc   = 'Schedule a free personalised demo of Vyala Software TaskPad. See how our task management software works for your business.';
$currentPage = 'demo';

// Handle form submission
$success = false;
$errors  = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']    ?? '');
    $company = trim($_POST['company'] ?? '');
    $mobile  = trim($_POST['mobile']  ?? '');
    $email   = trim($_POST['email']   ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name))    $errors[] = 'Full Name is required.';
    if (empty($company)) $errors[] = 'Company Name is required.';
    if (empty($mobile) || !preg_match('/^[0-9+\-\s]{7,15}$/', $mobile))
        $errors[] = 'Please enter a valid mobile number.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (empty($message)) $errors[] = 'Message / Requirement is required.';

    if (empty($errors)) {
        // In production, send email here. For now just show success.
        $success = true;
    }
}

require_once 'includes/page_header.php';
?>
<style>
    main { flex: 1; background: linear-gradient(160deg,#fff 60%,#eff6ff 100%); padding: 64px 72px; }

    .demo-grid {
        display: grid;
        grid-template-columns: 1fr 1.1fr;
        gap: 64px;
        max-width: 1100px;
        margin: 0 auto;
        align-items: start;
    }

    /* Left info panel */
    .demo-info h1 { font-size: 36px; font-weight: 800; color: #111827; line-height: 1.2; margin-bottom: 16px; }
    .demo-info h1 span { color: #2563eb; }
    .demo-info > p { font-size: 16px; color: #6b7280; line-height: 1.7; margin-bottom: 32px; }
    .demo-perks { display: flex; flex-direction: column; gap: 16px; }
    .demo-perk {
        display: flex; align-items: flex-start; gap: 14px;
        background: #fff; border: 1px solid #e5e7eb;
        border-radius: 12px; padding: 18px 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .perk-icon {
        width: 42px; height: 42px; border-radius: 10px;
        background: #eff6ff; color: #2563eb;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .perk-icon i { width: 20px; height: 20px; }
    .perk-body h4 { font-size: 14.5px; font-weight: 700; color: #111827; margin-bottom: 4px; }
    .perk-body p { font-size: 13px; color: #6b7280; line-height: 1.5; }

    /* Right form card */
    .demo-form-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.07);
    }
    .demo-form-card h2 { font-size: 22px; font-weight: 800; color: #111827; margin-bottom: 6px; }
    .demo-form-card > p { font-size: 13.5px; color: #6b7280; margin-bottom: 28px; }

    .form-group { margin-bottom: 18px; }
    .form-group label {
        display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%; padding: 12px 16px;
        font-family: 'Outfit', sans-serif; font-size: 14px;
        border: 1.5px solid #e5e7eb; border-radius: 10px;
        background: #f9fafb; color: #111827;
        outline: none; transition: all .2s;
    }
    .form-group input:focus,
    .form-group textarea:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    .form-group textarea { resize: vertical; min-height: 100px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .phone-row { display: grid; grid-template-columns: 140px 1fr; gap: 10px; }
    .phone-row select { padding: 12px 10px; }

    .btn-submit {
        width: 100%; padding: 14px; background: #2563eb; color: #fff;
        border: none; border-radius: 10px; font-family: 'Outfit', sans-serif;
        font-size: 15px; font-weight: 700; cursor: pointer; transition: all .2s;
        box-shadow: 0 4px 14px rgba(37,99,235,.3); margin-top: 6px;
        display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .btn-submit:hover { background: #1d4ed8; transform: translateY(-1px); }
    .btn-submit i { width: 18px; height: 18px; }

    .alert-error { background: #fee2e2; border:1px solid #fecaca; color: #b91c1c; padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; }
    .alert-success {
        background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px;
        padding: 28px 24px; text-align: center; margin-bottom: 20px;
    }
    .alert-success i { width: 48px; height: 48px; color: #16a34a; margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; }
    .alert-success h3 { font-size: 20px; font-weight: 800; color: #14532d; margin-bottom: 8px; }
    .alert-success p { font-size: 14px; color: #16a34a; }

    @media (max-width: 900px) {
        main { padding: 40px 24px; }
        .demo-grid { grid-template-columns: 1fr; gap: 32px; }
        .form-row { grid-template-columns: 1fr; }
    }
</style>

<main>
    <div class="demo-grid">
        <!-- Left info -->
        <div class="demo-info">
            <h1>See <span>Vyala Software TaskPad</span> in Action</h1>
            <p>Book a free personalised demo with our product experts. We'll walk you through every feature and answer all your questions.</p>

            <div class="demo-perks">
                <?php
                $perks = [
                    ['icon'=>'monitor','title'=>'Live Product Walkthrough','desc'=>'Get a personalised demo tailored to your industry and business size.'],
                    ['icon'=>'clock','title'=>'30-Minute Session','desc'=>'Quick and focused. No fluff â€” just the features that matter to you.'],
                    ['icon'=>'headphones','title'=>'Expert Support','desc'=>'Our specialists are ready to answer your specific workflow questions.'],
                    ['icon'=>'gift','title'=>'Free 15-Day Trial','desc'=>'Start using TaskPad immediately â€” no credit card required.'],
                    ['icon'=>'shield-check','title'=>'Data Security Guaranteed','desc'=>'Enterprise-grade encryption and 99.9% uptime SLA.'],
                ];
                foreach ($perks as $p): ?>
                <div class="demo-perk">
                    <div class="perk-icon"><i data-lucide="<?= $p['icon'] ?>"></i></div>
                    <div class="perk-body">
                        <h4><?= $p['title'] ?></h4>
                        <p><?= $p['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Right form -->
        <div class="demo-form-card">
            <?php if ($success): ?>
                <div class="alert-success">
                    <i data-lucide="check-circle"></i>
                    <h3>Demo Request Submitted!</h3>
                    <p>Thank you! Our team will contact you within 24 hours to schedule your personalised demo session.</p>
                </div>
                <a href="index.php" style="display:block; text-align:center; color:#2563eb; font-weight:600; font-size:14px; text-decoration:none; margin-top:12px;">â† Back to Home</a>
            <?php else: ?>
                <h2>Request a Free Demo</h2>
                <p>Fill in your details and we'll get back to you within 24 hours.</p>

                <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <ul style="padding-left:16px;">
                        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" action="demo.php" novalidate>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="d-name">Full Name *</label>
                            <input type="text" id="d-name" name="name" placeholder="e.g. Selvakumar J" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="d-company">Company Name *</label>
                            <input type="text" id="d-company" name="company" placeholder="e.g. Thamarai Services" value="<?= htmlspecialchars($_POST['company'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="d-email">Business Email *</label>
                        <input type="email" id="d-email" name="email" placeholder="you@yourcompany.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Mobile Number *</label>
                        <div class="phone-row">
                            <select name="country_code">
                                <option value="+91">ðŸ‡®ðŸ‡³ India (+91)</option>
                                <option value="+1">ðŸ‡ºðŸ‡¸ USA (+1)</option>
                                <option value="+44">ðŸ‡¬ðŸ‡§ UK (+44)</option>
                                <option value="+61">ðŸ‡¦ðŸ‡º Australia (+61)</option>
                                <option value="+971">ðŸ‡¦ðŸ‡ª UAE (+971)</option>
                                <option value="+65">ðŸ‡¸ðŸ‡¬ Singapore (+65)</option>
                                <option value="+60">ðŸ‡²ðŸ‡¾ Malaysia (+60)</option>
                                <option value="+49">ðŸ‡©ðŸ‡ª Germany (+49)</option>
                                <option value="+33">ðŸ‡«ðŸ‡· France (+33)</option>
                                <option value="+81">ðŸ‡¯ðŸ‡µ Japan (+81)</option>
                            </select>
                            <input type="tel" id="d-mobile" name="mobile" placeholder="98480 12345" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="d-industry">Industry</label>
                        <select id="d-industry" name="industry">
                            <option value="">Select your industry</option>
                            <option>IT & Software</option>
                            <option>Manufacturing</option>
                            <option>Real Estate</option>
                            <option>CA / CS / Finance</option>
                            <option>Healthcare</option>
                            <option>Education</option>
                            <option>Law Firm</option>
                            <option>Consulting</option>
                            <option>BPO / KPO</option>
                            <option>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="d-message">Your Requirement / Message *</label>
                        <textarea id="d-message" name="message" placeholder="Tell us about your team size, current challenges, and what you're looking for..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i data-lucide="calendar-check"></i>
                        Request My Free Demo
                    </button>
                    <p style="font-size:12px; color:#9ca3af; text-align:center; margin-top:12px;">
                        By submitting, you agree to our <a href="#" style="color:#2563eb;">Privacy Policy</a>. No spam, ever.
                    </p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once 'includes/page_footer.php'; ?>
</body>
</html>

