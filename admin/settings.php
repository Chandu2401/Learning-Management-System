<?php
/**
 * admin/settings.php — LMS Settings (General / LMS / Security)
 * ───────────────────────────────────────────────────────────────
 * Placement: lms-project/admin/settings.php
 *
 * Simple key/value settings store. Creates the `settings` table
 * automatically if it doesn't exist yet (same self-healing pattern
 * used by other defensive checks across the project), so this page
 * works even if config/settings.sql was never run manually.
 *
 * Sections:
 *  1. General Settings    — Site Name, Site Tagline, Admin Email
 *  2. LMS Settings        — Allow Registration, Default User Role,
 *                            Certificate Auto Generation
 *  3. Security Settings   — Session Timeout, Password Minimum Length
 *
 * Prepared statements + CSRF throughout. No other module touched.
 */

define('BASE_URL', '../');
$currentPage = 'settings';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Self-healing: create settings table if missing ──────────────
$tblCheck = $conn->query("SHOW TABLES LIKE 'settings'");
if (!$tblCheck || $tblCheck->num_rows === 0) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS settings (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key   VARCHAR(100) NOT NULL,
            setting_value VARCHAR(500) NOT NULL DEFAULT '',
            updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_setting_key (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

// ── Defaults used when a key has never been saved yet ────────────
$defaults = [
    'site_name'                 => 'LearnHub',
    'site_tagline'               => 'Learn anything, anytime.',
    'admin_email'                => '',
    'allow_registration'         => '1',
    'default_user_role'          => 'student',
    'certificate_auto_generate'  => '1',
    'session_timeout'            => '30',
    'password_min_length'        => '6',
];

// ── Load current settings into an associative array ─────────────
function loadSettings(mysqli $conn, array $defaults): array {
    $settings = $defaults;
    $res = $conn->query("SELECT setting_key, setting_value FROM settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings;
}

$settings = loadSettings($conn, $defaults);

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF check ────────────────────────────────────────────────
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        // ── Collect & sanitize ───────────────────────────────────
        $siteName    = trim($_POST['site_name']    ?? '');
        $siteTagline = trim($_POST['site_tagline'] ?? '');
        $adminEmail  = trim($_POST['admin_email']  ?? '');

        $allowRegistration = isset($_POST['allow_registration']) ? '1' : '0';
        $defaultUserRole   = 'student'; // fixed by requirement — not user-editable
        $certAutoGenerate  = isset($_POST['certificate_auto_generate']) ? '1' : '0';

        $sessionTimeout    = (int)($_POST['session_timeout']     ?? 0);
        $passwordMinLength = (int)($_POST['password_min_length'] ?? 0);

        // ── Validate ──────────────────────────────────────────────
        if ($siteName === '') {
            $errors[] = 'Site Name is required.';
        } elseif (mb_strlen($siteName) > 100) {
            $errors[] = 'Site Name must be 100 characters or fewer.';
        }

        if (mb_strlen($siteTagline) > 200) {
            $errors[] = 'Site Tagline must be 200 characters or fewer.';
        }

        if ($adminEmail === '') {
            $errors[] = 'Admin Email is required.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid Admin Email address.';
        }

        if ($sessionTimeout < 5 || $sessionTimeout > 1440) {
            $errors[] = 'Session Timeout must be between 5 and 1440 minutes.';
        }

        if ($passwordMinLength < 6 || $passwordMinLength > 32) {
            $errors[] = 'Password Minimum Length must be between 6 and 32 characters.';
        }

        // ── Save if no errors ────────────────────────────────────
        if (empty($errors)) {

            $toSave = [
                'site_name'                => $siteName,
                'site_tagline'              => $siteTagline,
                'admin_email'               => $adminEmail,
                'allow_registration'        => $allowRegistration,
                'default_user_role'         => $defaultUserRole,
                'certificate_auto_generate' => $certAutoGenerate,
                'session_timeout'           => (string)$sessionTimeout,
                'password_min_length'       => (string)$passwordMinLength,
            ];

            $upsertStmt = $conn->prepare("
                INSERT INTO settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = ?
            ");

            $allOk = true;
            foreach ($toSave as $key => $value) {
                $upsertStmt->bind_param('sss', $key, $value, $value);
                if (!$upsertStmt->execute()) {
                    $allOk = false;
                }
            }
            $upsertStmt->close();

            if ($allOk) {
                $settings = loadSettings($conn, $defaults);
                $success  = 'Settings saved successfully!';
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
        }
    }
}

// ── CSRF token ────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .form-section-title {
            font-family:'Sora',sans-serif;font-weight:700;font-size:.95rem;
            margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">

    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">Settings</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo; Settings
                </div>
            </div>
        </div>
    </header>

    <main class="lms-body">

        <!-- Alerts -->
        <?php if (!empty($errors)): ?>
            <div class="lms-alert lms-alert-danger">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>
                    <?php if (count($errors) === 1): ?>
                        <?= htmlspecialchars($errors[0]) ?>
                    <?php else: ?>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="lms-alert lms-alert-success" data-autohide>
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php" data-loading novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-3">

                <!-- LEFT COLUMN -->
                <div class="col-lg-8">

                    <!-- ════════════════ 1. GENERAL SETTINGS ════════════════ -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-gear-fill"></i> General Settings</h5>
                        </div>
                        <div class="lms-card-body">

                            <div class="mb-3">
                                <label class="lms-label">Site Name <span class="req">*</span></label>
                                <input type="text" name="site_name" class="lms-input"
                                       value="<?= htmlspecialchars($settings['site_name']) ?>"
                                       maxlength="100" required>
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Site Tagline</label>
                                <input type="text" name="site_tagline" class="lms-input"
                                       value="<?= htmlspecialchars($settings['site_tagline']) ?>"
                                       maxlength="200">
                            </div>

                            <div class="mb-0">
                                <label class="lms-label">Admin Email <span class="req">*</span></label>
                                <input type="email" name="admin_email" class="lms-input"
                                       value="<?= htmlspecialchars($settings['admin_email']) ?>"
                                       maxlength="190" required>
                            </div>

                        </div>
                    </div>

                    <!-- ════════════════ 2. LMS SETTINGS ════════════════ -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-mortarboard-fill"></i> LMS Settings</h5>
                        </div>
                        <div class="lms-card-body">

                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           name="allow_registration" id="allowRegistration"
                                           <?= $settings['allow_registration'] === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="allowRegistration">
                                        <strong>Allow Registration</strong>
                                    </label>
                                </div>
                                <div style="font-size:.76rem;color:var(--text-muted);margin-top:.3rem;">
                                    When off, new students cannot create an account from the Register page.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Default User Role</label>
                                <input type="text" class="lms-input" value="Student" disabled
                                       style="background:#f1f5f9;color:var(--text-muted);">
                                <div style="font-size:.76rem;color:var(--text-muted);margin-top:.3rem;">
                                    All new registrations are assigned the Student role. Admin accounts are
                                    created or promoted manually from the Users page.
                                </div>
                            </div>

                            <div class="mb-0">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           name="certificate_auto_generate" id="certAutoGen"
                                           <?= $settings['certificate_auto_generate'] === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="certAutoGen">
                                        <strong>Certificate Auto Generation</strong>
                                    </label>
                                </div>
                                <div style="font-size:.76rem;color:var(--text-muted);margin-top:.3rem;">
                                    When on, certificates are issued automatically once a student completes
                                    all lessons and passes the course quiz.
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- ════════════════ 3. SECURITY SETTINGS ════════════════ -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-shield-lock-fill"></i> Security Settings</h5>
                        </div>
                        <div class="lms-card-body">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="lms-label">Session Timeout (minutes) <span class="req">*</span></label>
                                    <input type="number" name="session_timeout" class="lms-input"
                                           value="<?= htmlspecialchars($settings['session_timeout']) ?>"
                                           min="5" max="1440" required>
                                    <div style="font-size:.76rem;color:var(--text-muted);margin-top:.3rem;">
                                        Between 5 and 1440 minutes (24 hours).
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="lms-label">Password Minimum Length <span class="req">*</span></label>
                                    <input type="number" name="password_min_length" class="lms-input"
                                           value="<?= htmlspecialchars($settings['password_min_length']) ?>"
                                           min="6" max="32" required>
                                    <div style="font-size:.76rem;color:var(--text-muted);margin-top:.3rem;">
                                        Between 6 and 32 characters.
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <button type="submit" class="btn-lms-primary" style="padding:.7rem 1.75rem;">
                        <i class="bi bi-check-lg"></i> Save Settings
                    </button>

                </div>

                <!-- RIGHT COLUMN: info sidebar -->
                <div class="col-lg-4">
                    <div class="lms-card">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-info-circle-fill"></i> About These Settings</h5>
                        </div>
                        <div class="lms-card-body" style="font-size:.84rem;color:var(--text-muted);line-height:1.6;">
                            <p>These settings are stored in a simple key/value table and apply platform-wide.</p>
                            <p class="mb-0">Changing the Admin Email here updates the contact address shown in
                            system messages — it does not change your own admin login email
                            (use the Users page to edit your own account).</p>
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>