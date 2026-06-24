<?php
/**
 * includes/site-info.php — Central Branding System
 * ───────────────────────────────────────────────────────────────
 * Placement: lms-project/includes/site-info.php
 *
 * Single source of truth for institute branding across the entire
 * project. Reads from the existing `settings` key/value table via
 * get_lms_setting() (defined in config/db.php), with safe fallback
 * defaults so every page works even before an admin saves anything
 * in admin/settings.php.
 *
 * REQUIRES: config/db.php must already be required (for $conn and
 * the get_lms_setting() helper) before this file is included.
 *
 * Usage (from project root, e.g. dashboard.php):
 *     require_once 'config/db.php';
 *     require_once 'includes/site-info.php';
 *     echo SITE_NAME;            // "SHAURYA EDUCATION HUB"
 *     echo site_logo_url('');    // logo <img> src, relative to root
 *
 * Usage (from admin/ or other one-level-deep folders):
 *     require_once '../config/db.php';
 *     require_once '../includes/site-info.php';
 *     echo site_logo_url('../'); // prefixes the relative path
 */

if (!isset($conn) || !($conn instanceof mysqli)) {
    // Defensive guard — site-info.php must be loaded after db.php.
    // If it isn't, fall back to pure defaults rather than fatal-erroring.
    $conn = null;
}

/**
 * site_setting()
 * Thin wrapper around get_lms_setting() so this file works even in
 * the rare case $conn isn't available (falls back to $default).
 */
function site_setting(string $key, string $default = ''): string {
    global $conn;
    if ($conn instanceof mysqli && function_exists('get_lms_setting')) {
        return get_lms_setting($conn, $key, $default);
    }
    return $default;
}

// ── Institute branding (reads from settings table, falls back to
//    the values supplied for this project) ──────────────────────
define('SITE_NAME',    site_setting('institute_name',  'SHAURYA EDUCATION HUB'));
define('SITE_TAGLINE',  site_setting('site_tagline',     'Empowering Students Through Digital Learning'));
define('SITE_PHONE',    site_setting('institute_phone',  '9322881223'));
define('SITE_EMAIL',    site_setting('institute_email',  'chandrakantmate24@gmail.com'));
define('SITE_ADDRESS',  site_setting('institute_address', ''));

// Logo path is stored relative to the project root (e.g.
// "assets/images/logo.png"). Default matches the logo file already
// placed in the project for this rebrand.
define('SITE_LOGO_PATH', site_setting('institute_logo', 'assets/images/logo.png'));

// ── Certificate signatory (Authorized By section) ─────────────────
// SIGNATORY_NAME is the person's name printed under the signature
// image on certificates. Falls back to the institute name if never
// configured, so certificates still look complete out of the box.
define('SIGNATORY_NAME',        site_setting('signatory_name', 'Chandrakant Mate'));
define('SIGNATORY_DESIGNATION', site_setting('signatory_designation', 'Director'));
define('SIGNATURE_PATH',        site_setting('signature_image', 'assets/images/signature.png'));

/**
 * site_logo_url()
 * Returns the logo path prefixed with the correct relative prefix
 * depending on how deep the calling page is nested.
 *
 *   site_logo_url()        → "assets/images/logo.png"        (from root)
 *   site_logo_url('../')   → "../assets/images/logo.png"     (from admin/)
 */
function site_logo_url(string $prefix = ''): string {
    return $prefix . SITE_LOGO_PATH;
}

/**
 * signature_url()
 * Same prefixing pattern as site_logo_url(), for the authorized
 * signatory's signature image used on certificates.
 */
function signature_url(string $prefix = ''): string {
    return $prefix . SIGNATURE_PATH;
}

/**
 * signature_file_exists()
 * Checks whether the configured signature image actually exists on
 * disk, so certificate pages can gracefully fall back to a plain
 * underline (no broken image icon) if it hasn't been uploaded yet.
 * $rootPath is the filesystem path to the project root (not a URL
 * prefix) — e.g. __DIR__ . '/..' when called from includes/.
 */
function signature_file_exists(string $rootPath): bool {
    return is_file(rtrim($rootPath, '/') . '/' . SIGNATURE_PATH);
}

/**
 * render_site_footer()
 * Renders the standard professional footer used across every page.
 * $prefix works the same way as site_logo_url() — pass '../' when
 * calling from a one-level-deep folder like admin/.
 */
function render_site_footer(string $prefix = ''): void {
    $year = date('Y');
    ?>
    <footer class="site-footer">
        <div class="site-footer-inner">
            <div class="site-footer-brand">
                <img src="<?= htmlspecialchars(site_logo_url($prefix)) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" class="site-footer-logo">
                <div>
                    <div class="site-footer-name"><?= htmlspecialchars(SITE_NAME) ?></div>
                    <div class="site-footer-tagline"><?= htmlspecialchars(SITE_TAGLINE) ?></div>
                </div>
            </div>
            <div class="site-footer-contact">
                <div><i class="bi bi-telephone-fill"></i> <?= htmlspecialchars(SITE_PHONE) ?></div>
                <div><i class="bi bi-envelope-fill"></i> <?= htmlspecialchars(SITE_EMAIL) ?></div>
            </div>
        </div>
        <div class="site-footer-copy">&copy; <?= $year ?> <?= htmlspecialchars(SITE_NAME) ?>. All rights reserved.</div>
    </footer>
    <style>
        .site-footer {
            margin-top: 2.5rem;
            padding: 1.5rem 1.25rem 1.25rem;
            border-top: 1px solid rgba(0,0,0,.08);
            font-family: 'Poppins', 'Sora', 'DM Sans', sans-serif;
        }
        .site-footer-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            max-width: 1100px;
            margin: 0 auto;
        }
        .site-footer-brand {
            display: flex;
            align-items: center;
            gap: .65rem;
        }
        .site-footer-logo {
            width: 38px; height: 38px;
            border-radius: 9px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .site-footer-name {
            font-weight: 700;
            font-size: .88rem;
            color: #1e1b3a;
            letter-spacing: .01em;
        }
        .site-footer-tagline {
            font-size: .74rem;
            color: #6b7280;
            margin-top: .1rem;
        }
        .site-footer-contact {
            display: flex;
            gap: 1.25rem;
            font-size: .78rem;
            color: #6b7280;
        }
        .site-footer-contact i {
            color: #4f46e5;
            margin-right: .35rem;
        }
        .site-footer-copy {
            text-align: center;
            font-size: .72rem;
            color: #9ca3af;
            margin-top: 1rem;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }
        @media (max-width: 600px) {
            .site-footer-inner { flex-direction: column; text-align: center; }
            .site-footer-contact { flex-direction: column; gap: .35rem; align-items: center; }
        }
    </style>
    <?php
}