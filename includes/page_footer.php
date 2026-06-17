<?php // includes/page_footer.php - Shared landing page footer ?>
<footer style="
    background: #0f172a;
    color: #94a3b8;
    font-family: 'Outfit', sans-serif;
    padding: 56px 72px 0;
    margin-top: auto;
">
    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 48px; margin-bottom: 40px;">

        <!-- Brand Column -->
        <div>
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px;">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" fill="#1e3a8a"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                <div>
                    <div style="font-size:16px; font-weight:800; color:#fff; letter-spacing:-0.3px;">Vyala Software <span style="color:#2563eb;">TaskPad</span></div>
                    <div style="font-size:8px; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; color:#2563eb; margin-top:1px;">Be Organized</div>
                </div>
            </div>
            <p style="font-size:13.5px; line-height:1.75; margin-bottom:20px; max-width:300px;">
                India's complete task management system built for growing businesses. Manage tasks, projects, teams and workflows - all in one place.
            </p>
            <!-- Address -->
            <div style="font-size:13px; line-height:2;">
                <div style="display:flex; gap:8px; align-items:flex-start; margin-bottom:6px;">
                    <i data-lucide="map-pin" style="width:14px; height:14px; color:#2563eb; margin-top:3px; flex-shrink:0;"></i>
                    <span>Vyala Software Solutions,<br>Chennai, Tamil Nadu, India - 600001</span>
                </div>
                <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
                    <i data-lucide="phone" style="width:14px; height:14px; color:#2563eb; flex-shrink:0;"></i>
                    <!-- 📱 உங்கள் PHONE NUMBER இங்கே மாற்றவும் -->
                <a href="tel:+919344376416" style="color:#94a3b8; text-decoration:none;">+91 93443 76416</a>
                </div>
                <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
                    <i data-lucide="mail" style="width:14px; height:14px; color:#2563eb; flex-shrink:0;"></i>
                    <a href="mailto:info@vyalasoftware.com" style="color:#94a3b8; text-decoration:none;">info@vyalasoftware.com</a>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <i data-lucide="globe" style="width:14px; height:14px; color:#2563eb; flex-shrink:0;"></i>
                    <a href="https://www.vyalasoftware.com" target="_blank" style="color:#94a3b8; text-decoration:none;">www.vyalasoftware.com</a>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div>
            <h4 style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#e2e8f0; margin-bottom:20px;">Quick Links</h4>
            <ul style="list-style:none; display:flex; flex-direction:column; gap:10px;">
                <?php
                $quickLinks = [
                    ['href'=>'index.php#features','label'=>'Features'],
                    ['href'=>'index.php#industries','label'=>'Industries'],
                    ['href'=>'index.php#pricing','label'=>'Pricing'],
                    ['href'=>'index.php#blog','label'=>'Blog'],
                    ['href'=>'demo.php','label'=>'Request Demo'],
                    ['href'=>'guide.php','label'=>'User Guide'],
                    ['href'=>'contact.php','label'=>'Contact Us'],
                ];
                foreach ($quickLinks as $l): ?>
                <li><a href="<?= $l['href'] ?>" style="color:#94a3b8; text-decoration:none; font-size:13.5px; transition:color .2s;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#94a3b8'"><?= $l['label'] ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Product -->
        <div>
            <h4 style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#e2e8f0; margin-bottom:20px;">Product</h4>
            <ul style="list-style:none; display:flex; flex-direction:column; gap:10px;">
                <?php
                $productLinks = [
                    ['href'=>'login.php','label'=>'Login to App'],
                    ['href'=>'login.php?tab=signup','label'=>'Sign Up Free'],
                    ['href'=>'demo.php','label'=>'Book a Demo'],
                    ['href'=>'index.php#pricing','label'=>'Pricing Plans'],
                    ['href'=>'guide.php','label'=>'Documentation'],
                ];
                foreach ($productLinks as $l): ?>
                <li><a href="<?= $l['href'] ?>" style="color:#94a3b8; text-decoration:none; font-size:13.5px; transition:color .2s;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#94a3b8'"><?= $l['label'] ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Social -->
        <div>
            <h4 style="font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:1.5px; color:#e2e8f0; margin-bottom:20px;">Follow Us</h4>
            <div style="display:flex; flex-direction:column; gap:12px;">
                <?php
                $socials = [
                    ['icon'=>'linkedin','label'=>'LinkedIn','href'=>'#'],
                    ['icon'=>'twitter','label'=>'Twitter / X','href'=>'#'],
                    ['icon'=>'facebook','label'=>'Facebook','href'=>'#'],
                    ['icon'=>'instagram','label'=>'Instagram','href'=>'#'],
                    ['icon'=>'youtube','label'=>'YouTube','href'=>'#'],
                ];
                foreach ($socials as $s): ?>
                <a href="<?= $s['href'] ?>" style="display:flex; align-items:center; gap:10px; color:#94a3b8; text-decoration:none; font-size:13.5px; transition:color .2s;" onmouseover="this.style.color='#2563eb'" onmouseout="this.style.color='#94a3b8'">
                    <i data-lucide="<?= $s['icon'] ?>" style="width:16px; height:16px;"></i> <?= $s['label'] ?>
                </a>
                <?php endforeach; ?>
            </div>
            <!-- 💬 WHATSAPP NUMBER இங்கே மாற்றவும் — wa.me/91XXXXXXXXXX format -->
            <a href="https://wa.me/919344376416" target="_blank" style="display:inline-flex; align-items:center; gap:8px; margin-top:20px; background:#25d366; color:#fff; padding:9px 18px; border-radius:22px; font-size:13px; font-weight:600; text-decoration:none;">
                <i data-lucide="message-circle" style="width:15px; height:15px;"></i> Chat on WhatsApp
            </a>
        </div>
    </div>

    <!-- Footer Bottom Bar -->
    <div style="border-top:1px solid #1e293b; padding:20px 0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <span style="font-size:13px;">&copy; <?= date('Y') ?> Vyala Software Solutions. All rights reserved.</span>
        <div style="display:flex; gap:20px;">
            <a href="#" style="font-size:13px; color:#94a3b8; text-decoration:none;">Privacy Policy</a>
            <a href="#" style="font-size:13px; color:#94a3b8; text-decoration:none;">Terms of Service</a>
            <a href="contact.php" style="font-size:13px; color:#94a3b8; text-decoration:none;">Contact</a>
        </div>
    </div>
</footer>

<style>
@media (max-width: 1024px) {
    footer > div:first-of-type { grid-template-columns: 1fr 1fr !important; }
}
@media (max-width: 640px) {
    footer { padding: 40px 20px 0 !important; }
    footer > div:first-of-type { grid-template-columns: 1fr !important; }
    footer > div:last-of-type { flex-direction: column !important; gap: 8px !important; }
}
</style>

