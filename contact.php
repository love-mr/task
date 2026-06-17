<?php
$pageTitle = 'Contact Us - Vyala Software TaskPad';
$metaDesc = 'Get in touch with the Vyala Software TaskPad team. Contact us for support, sales enquiries, or to request a demo.';
$currentPage = 'contact';

$success = false;
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name))
        $errors[] = 'Full Name is required.';
    if (empty($mobile) || !preg_match('/^[0-9+\-\s]{7,15}$/', $mobile))
        $errors[] = 'Please enter a valid mobile number.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Please enter a valid email address.';
    if (empty($subject))
        $errors[] = 'Subject is required.';
    if (empty($message))
        $errors[] = 'Message is required.';

    if (empty($errors)) {
        $success = true;
    }
}

require_once 'includes/page_header.php';
?>
<style>
    main {
        flex: 1;
        background: #f9fafb;
    }

    .contact-wrapper {
        max-width: 1200px;
        margin: 0 auto;
        padding: 64px 72px;
    }

    /* Page Heading */
    .contact-heading {
        text-align: center;
        margin-bottom: 52px;
    }

    .contact-heading .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #eff6ff;
        color: #2563eb;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 14px;
        border-radius: 20px;
        margin-bottom: 16px;
        border: 1px solid #bfdbfe;
    }

    .contact-heading .badge i {
        width: 13px;
        height: 13px;
    }

    .contact-heading h1 {
        font-size: 36px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 12px;
    }

    .contact-heading h1 span {
        color: #2563eb;
    }

    .contact-heading p {
        font-size: 16px;
        color: #6b7280;
        max-width: 560px;
        margin: 0 auto;
        line-height: 1.7;
    }

    /* Info + Form Grid */
    .contact-grid {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 32px;
        margin-bottom: 40px;
        align-items: start;
    }

    /* Left info card */
    .contact-info-card {
        background: linear-gradient(145deg, #2563eb 0%, #1d4ed8 100%);
        border-radius: 20px;
        padding: 36px 32px;
        color: #fff;
    }

    .contact-info-card h2 {
        font-size: 20px;
        font-weight: 800;
        margin-bottom: 8px;
    }

    .contact-info-card>p {
        font-size: 13.5px;
        color: rgba(255, 255, 255, 0.8);
        line-height: 1.6;
        margin-bottom: 28px;
    }

    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 22px;
    }

    .info-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .info-item-icon i {
        width: 18px;
        height: 18px;
    }

    .info-item-body h4 {
        font-size: 13px;
        font-weight: 700;
        color: rgba(255, 255, 255, 0.7);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .info-item-body p,
    .info-item-body a {
        font-size: 14.5px;
        color: #fff;
        text-decoration: none;
        line-height: 1.5;
    }

    .social-row {
        display: flex;
        gap: 10px;
        margin-top: 28px;
    }

    .social-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.15);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        text-decoration: none;
        transition: all .2s;
    }

    .social-btn:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .social-btn i {
        width: 17px;
        height: 17px;
    }

    /* Right form card */
    .contact-form-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
    }

    .contact-form-card h2 {
        font-size: 22px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 6px;
    }

    .contact-form-card>p {
        font-size: 13.5px;
        color: #6b7280;
        margin-bottom: 28px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 6px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 12px 16px;
        font-family: 'Outfit', sans-serif;
        font-size: 14px;
        border: 1.5px solid #e5e7eb;
        border-radius: 10px;
        background: #f9fafb;
        color: #111827;
        outline: none;
        transition: all .2s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #2563eb;
        background: #fff;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 120px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .btn-submit {
        width: 100%;
        padding: 14px;
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-family: 'Outfit', sans-serif;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all .2s;
        box-shadow: 0 4px 14px rgba(37, 99, 235, .3);
        margin-top: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-submit:hover {
        background: #1d4ed8;
        transform: translateY(-1px);
    }

    .btn-submit i {
        width: 18px;
        height: 18px;
    }

    .alert-error {
        background: #fee2e2;
        border: 1px solid #fecaca;
        color: #b91c1c;
        padding: 12px 16px;
        border-radius: 8px;
        font-size: 13px;
        margin-bottom: 18px;
    }

    .alert-success {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 12px;
        padding: 32px 24px;
        text-align: center;
    }

    .alert-success i {
        width: 52px;
        height: 52px;
        color: #16a34a;
        margin: 0 auto 14px;
        display: block;
    }

    .alert-success h3 {
        font-size: 22px;
        font-weight: 800;
        color: #14532d;
        margin-bottom: 8px;
    }

    .alert-success p {
        font-size: 14px;
        color: #16a34a;
    }

    /* Google Map */
    .map-section {
        margin-top: 8px;
    }

    .map-section h2 {
        font-size: 22px;
        font-weight: 800;
        color: #111827;
        margin-bottom: 6px;
    }

    .map-section p {
        font-size: 14px;
        color: #6b7280;
        margin-bottom: 20px;
    }

    .map-container {
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
        height: 420px;
    }

    .map-container iframe {
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }

    @media (max-width: 900px) {
        .contact-wrapper {
            padding: 40px 20px;
        }

        .contact-grid {
            grid-template-columns: 1fr;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<main>
    <div class="contact-wrapper">
        <!-- Heading -->
        <div class="contact-heading">
            <div class="badge"><i data-lucide="headphones"></i> We're Here to Help</div>
            <h1>Get in Touch with <span>Our Team</span></h1>
            <p>Have a question, need support, or want to discuss your requirements? We'd love to hear from you. Our team
                typically responds within 2â€“4 business hours.</p>
        </div>

        <!-- Info + Form -->
        <div class="contact-grid">
            <!-- Left: Company Info -->
            <div class="contact-info-card">
                <h2>Contact Information</h2>
                <p>Reach out to us through any of these channels and we'll get back to you as soon as possible.</p>

                <div class="info-item">
                    <div class="info-item-icon"><i data-lucide="map-pin"></i></div>
                    <div class="info-item-body">
                        <h4>Office Address</h4>
                        <p>Vyala Software Solutions<br>Vandavasi, Tamil Nadu<br>India — 604408</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-item-icon"><i data-lucide="phone"></i></div>
                    <div class="info-item-body">
                        <h4>Phone / WhatsApp</h4>
                        <!-- 📱 உங்கள் PHONE NUMBER இங்கே மாற்றவும் -->
                        <a href="tel:+91 9344376416">+91 93443 76416</a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-item-icon"><i data-lucide="mail"></i></div>
                    <div class="info-item-body">
                        <h4>Email Address</h4>
                        <a href="mailto:aruenlumalai2600@gmail.com">info@vyalasoftware.com</a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-item-icon"><i data-lucide="globe"></i></div>
                    <div class="info-item-body">
                        <h4>Website</h4>
                        <a href="https://www.vyalasoftware.com" target="_blank">www.vyalasoftware.com</a>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-item-icon"><i data-lucide="clock"></i></div>
                    <div class="info-item-body">
                        <h4>Business Hours</h4>
                        <p>Mon–Sat: 9:00 AM – 6:00 PM IST</p>
                    </div>
                </div>

                <div class="social-row">
                    <a href="#" class="social-btn" title="LinkedIn"><i data-lucide="linkedin"></i></a>
                    <a href="#" class="social-btn" title="Twitter"><i data-lucide="twitter"></i></a>
                    <a href="#" class="social-btn" title="Facebook"><i data-lucide="facebook"></i></a>
                    <a href="#" class="social-btn" title="Instagram"><i data-lucide="instagram"></i></a>
                    <!-- 💬 WHATSAPP NUMBER இங்கே மாற்றவும் -->
                    <a href="https://wa.me/919344376416" target="_blank" class="social-btn" title="WhatsApp"
                        style="background:rgba(37,211,102,0.25);">
                        <i data-lucide="message-circle"></i>
                    </a>
                </div>
            </div>

            <!-- Right: Contact Form -->
            <div class="contact-form-card">
                <?php if ($success): ?>
                    <div class="alert-success">
                        <i data-lucide="check-circle"></i>
                        <h3>Message Sent Successfully!</h3>
                        <p>Thank you for contacting us. Our team will get back to you within 2â€“4 business hours.</p>
                        <a href="contact.php"
                            style="display:inline-block; margin-top:16px; color:#2563eb; font-weight:600; font-size:14px; text-decoration:none;">â†
                            Send another message</a>
                    </div>
                <?php else: ?>
                    <h2>Send Us a Message</h2>
                    <p>We'll respond to your enquiry within 24 business hours during working days.</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert-error">
                            <ul style="padding-left:16px;">
                                <?php foreach ($errors as $e): ?>
                                    <li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="contact.php" novalidate>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="c-name">Full Name *</label>
                                <input type="text" id="c-name" name="name" placeholder="Your Name"
                                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="c-mobile">Mobile Number *</label>
                                <input type="tel" id="c-mobile" name="mobile" placeholder="+91 98480 12345"
                                    value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="c-email">Email Address *</label>
                            <input type="email" id="c-email" name="email" placeholder="you@company.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="c-subject">Subject *</label>
                            <select id="c-subject" name="subject">
                                <option value="">Select a subject</option>
                                <option <?= ($_POST['subject'] ?? '') === 'General Enquiry' ? 'selected' : '' ?>>General
                                    Enquiry
                                </option>
                                <option <?= ($_POST['subject'] ?? '') === 'Sales / Pricing' ? 'selected' : '' ?>>Sales /
                                    Pricing
                                </option>
                                <option <?= ($_POST['subject'] ?? '') === 'Technical Support' ? 'selected' : '' ?>>Technical
                                    Support
                                </option>
                                <option <?= ($_POST['subject'] ?? '') === 'Request a Demo' ? 'selected' : '' ?>>Request a Demo
                                </option>
                                <option <?= ($_POST['subject'] ?? '') === 'Partnership' ? 'selected' : '' ?>>Partnership
                                </option>
                                <option <?= ($_POST['subject'] ?? '') === 'Billing' ? 'selected' : '' ?>>Billing</option>
                                <option <?= ($_POST['subject'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="c-message">Message *</label>
                            <textarea id="c-message" name="message"
                                placeholder="Describe your question or requirement in detail..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i data-lucide="send"></i>
                            Send Message
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Google Map Section -->
        <div class="map-section">
            <h2>📍 Our Location</h2>
            <p>Find us in Vandavasi, Tamil Nadu, India. We welcome walk-in visits during business hours.</p>
            <div class="map-container">
                <!-- 🗺️ GOOGLE MAPS இங்கே மாற்றவும் — உங்கள் location embed URL பெறுவது எப்படி:
                     1. maps.google.com போங்கள்
                     2. உங்கள் address search பண்ணுங்கள்
                     3. Share -> Embed a map -> Copy HTML கிளிக் பண்ணுங்கள்
                     4. அந்த copied iframe code-ல் இருக்கும் src="https://www.google.com/maps/embed?..." URL-ஐ மட்டும் கீழே உள்ள src-ல் replace செய்யவும் -->
                <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3895.192466855259!2d79.59946637412138!3d12.50339848777059!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3a53250e002c21c1%3A0xfdc5c8189e3b9ae5!2sUzhaippali%20Marachekku%20Ennai!5e0!3m2!1sen!2sin!4v1781541992109!5m2!1sen!2sin"
                    allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    title="Vyala Software Solutions Location - Vandavasi">
                </iframe>
            </div>
        </div>
    </div>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>

<?php require_once 'includes/page_footer.php'; ?>
</body>

</html>