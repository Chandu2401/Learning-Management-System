<?php
/**
 * register.php — Student Registration
 * ────────────────────────────────────
 * Fixes applied (Critical Issue #1):
 *  - Prepared statements (no raw SQL / SQL injection)
 *  - CSRF token generation + validation
 *  - Duplicate email check before insert
 *  - Email format validation
 *  - Minimum password length (6 chars)
 *  - Proper redirect after successful registration
 */

session_start();

require_once 'config/db.php';
require_once 'includes/site-info.php';

$error = '';

// ── Generate CSRF token ─────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['register'])) {

    // 1. CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {

        $name     = trim($_POST['name'] ?? '');
        $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
        $password = $_POST['password'] ?? '';

        // 2. Basic validation
        if ($name === '' || $email === '' || $password === '') {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } else {

            // 3. Duplicate email check (prepared statement)
            $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if ($exists) {
                $error = 'An account with this email already exists. Please log in instead.';
            } else {

                // 4. Insert via prepared statement
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $insertStmt = $conn->prepare(
                    "INSERT INTO users (name, email, password) VALUES (?, ?, ?)"
                );
                $insertStmt->bind_param('sss', $name, $email, $hashedPassword);

                if ($insertStmt->execute()) {
                    $insertStmt->close();

                    // 5. Regenerate CSRF token and redirect to login
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    header('Location: login.php?registered=1');
                    exit();

                } else {
                    $insertStmt->close();
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — <?= htmlspecialchars(SITE_NAME) ?></title>

<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --brand-1:      #1d4ed8;
        --brand-2:      #2563eb;
        --brand-3:      #38bdf8;
        --text-main:    #0f172a;
        --text-muted:   #64748b;
        --border:       #e2e8f0;
        --danger:       #dc2626;
        --success:      #16a34a;
        --radius-lg:    18px;
        --radius-md:    12px;
    }

    *, *::before, *::after { box-sizing: border-box; }

    body {
        margin: 0;
        font-family: 'Poppins', sans-serif;
        min-height: 100vh;
        background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 45%, #38bdf8 100%);
        background-attachment: fixed;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
        position: relative;
        overflow-x: hidden;
    }

    /* ── Decorative floating blobs ── */
    body::before, body::after {
        content: '';
        position: fixed;
        border-radius: 50%;
        filter: blur(95px);
        opacity: .32;
        pointer-events: none;
        z-index: 0;
    }
    body::before {
        width: 480px; height: 480px;
        background: radial-gradient(circle, #93c5fd, #1d4ed8);
        top: -150px; left: -150px;
        animation: floatBlob 9s ease-in-out infinite;
    }
    body::after {
        width: 420px; height: 420px;
        background: radial-gradient(circle, #7dd3fc, #0ea5e9);
        bottom: -130px; right: -130px;
        animation: floatBlob 11s ease-in-out infinite reverse;
    }
    @keyframes floatBlob {
        0%, 100% { transform: translate(0, 0) scale(1); }
        50%      { transform: translate(28px, -18px) scale(1.07); }
    }

    /* ── Outer split card (glassmorphism) ── */
    .auth-shell {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 980px;
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
        border: 1px solid rgba(255,255,255,.4);
        border-radius: var(--radius-lg);
        box-shadow: 0 25px 70px rgba(15, 35, 95, .35), 0 4px 14px rgba(0,0,0,.06);
        display: flex;
        overflow: hidden;
        min-height: 620px;
        animation: shellIn .55s cubic-bezier(.16,.84,.44,1) both;
    }
    @keyframes shellIn {
        from { opacity: 0; transform: translateY(30px) scale(.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* ── LEFT: Branding / illustration panel ── */
    .auth-illustration {
        flex: 0 0 44%;
        background: linear-gradient(160deg, #1e3a8a 0%, #1d4ed8 55%, #38bdf8 130%);
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 3rem 2.25rem;
        color: #fff;
        overflow: hidden;
    }
    .auth-illustration::before,
    .auth-illustration::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255,255,255,.10);
    }
    .auth-illustration::before { width: 220px; height: 220px; top: -60px; right: -60px; }
    .auth-illustration::after  { width: 160px; height: 160px; bottom: -40px; left: -40px; }

    .auth-logo-row {
        display: flex;
        align-items: center;
        gap: .6rem;
        position: relative;
        z-index: 2;
        margin-bottom: 2.25rem;
    }
    .auth-logo-row .logo-badge {
        width: 38px; height: 38px;
        border-radius: 10px;
        background: rgba(255,255,255,.16);
        border: 1px solid rgba(255,255,255,.3);
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .auth-logo-row .logo-badge i { color: #fff; font-size: 1.1rem; }
    .auth-logo-row span {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 1.05rem;
        letter-spacing: -.01em;
    }

    .illu-icon-ring {
        width: 110px; height: 110px;
        border-radius: 28px;
        background: rgba(255,255,255,.14);
        backdrop-filter: blur(6px);
        border: 1px solid rgba(255,255,255,.25);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 1.75rem;
        position: relative;
        z-index: 2;
        animation: gentleFloat 4s ease-in-out infinite;
    }
    @keyframes gentleFloat {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-10px); }
    }
    .illu-icon-ring i { font-size: 3rem; color: #fff; }

    .auth-illustration h2 {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 1.65rem;
        text-align: center;
        margin-bottom: .65rem;
        position: relative;
        z-index: 2;
    }
    .auth-illustration p.welcome-text {
        font-size: .9rem;
        text-align: center;
        opacity: .88;
        max-width: 320px;
        line-height: 1.6;
        position: relative;
        z-index: 2;
        margin-bottom: 2rem;
    }

    .illu-feature-list {
        list-style: none;
        padding: 0;
        margin: 0;
        position: relative;
        z-index: 2;
        width: 100%;
        max-width: 290px;
    }
    .illu-feature-list li {
        display: flex;
        align-items: center;
        gap: .65rem;
        font-size: .84rem;
        padding: .55rem 0;
        opacity: .95;
    }
    .illu-feature-list li i {
        width: 30px; height: 30px;
        background: rgba(255,255,255,.16);
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: .85rem;
        flex-shrink: 0;
    }

    /* ── RIGHT: Form panel ── */
    .auth-form-panel {
        flex: 1;
        padding: 3rem 3rem 2.5rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
        background: rgba(255,255,255,.5);
    }

    .auth-title {
        font-family: 'Poppins', sans-serif;
        font-weight: 700;
        font-size: 1.65rem;
        color: var(--text-main);
        margin-bottom: .35rem;
        letter-spacing: -.01em;
    }
    .auth-subtitle {
        font-size: .875rem;
        color: var(--text-muted);
        margin-bottom: 1.75rem;
    }

    /* ── Alerts ── */
    .alert-modern {
        border: none;
        border-radius: var(--radius-md);
        font-size: .85rem;
        padding: .8rem 1rem;
        display: flex;
        align-items: flex-start;
        gap: .6rem;
        margin-bottom: 1.25rem;
        animation: alertSlide .4s ease both;
    }
    @keyframes alertSlide {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .alert-modern.danger {
        background: #fef2f2;
        color: #991b1b;
        border-left: 3px solid var(--danger);
    }
    .alert-modern.success {
        background: #f0fdf4;
        color: #166534;
        border-left: 3px solid var(--success);
    }
    .alert-modern i { font-size: 1.05rem; margin-top: .05rem; flex-shrink: 0; }

    /* ── Floating-label inputs ── */
    .field-group {
        position: relative;
        margin-bottom: 1.35rem;
    }
    .field-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 1rem;
        z-index: 3;
        transition: color .2s;
        pointer-events: none;
    }
    .field-group .form-control {
        height: 54px;
        border-radius: var(--radius-md);
        border: 1.5px solid var(--border);
        padding: 1.1rem 2.6rem 0 2.6rem;
        font-size: .92rem;
        font-family: 'Poppins', sans-serif;
        background: #fff;
        transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .field-group .form-control:focus {
        border-color: var(--brand-2);
        box-shadow: 0 0 0 4px rgba(37,99,235,.12);
        background: #fff;
    }
    .field-group .form-control:focus ~ .field-icon {
        color: var(--brand-2);
    }
    .field-group label.floating-label {
        position: absolute;
        left: 2.6rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: .92rem;
        color: var(--text-muted);
        background: transparent;
        pointer-events: none;
        transition: all .18s ease;
        z-index: 2;
    }
    .field-group .form-control:focus ~ label.floating-label,
    .field-group .form-control:not(:placeholder-shown) ~ label.floating-label {
        top: 11px;
        transform: translateY(0);
        font-size: .68rem;
        font-weight: 600;
        color: var(--brand-2);
        letter-spacing: .02em;
    }
    .field-group .form-control.is-invalid {
        border-color: var(--danger);
    }
    .field-group .form-control.is-invalid ~ .field-icon {
        color: var(--danger);
    }

    .toggle-pw-btn {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 1.02rem;
        z-index: 3;
        padding: 4px;
        transition: color .2s;
    }
    .toggle-pw-btn:hover { color: var(--brand-2); }

    .match-hint {
        font-size: .72rem;
        margin-top: -.55rem;
        margin-bottom: 1.1rem;
        font-weight: 500;
        display: none;
        align-items: center;
        gap: .3rem;
    }
    .match-hint.show { display: flex; }
    .match-hint.ok { color: var(--success); }
    .match-hint.bad { color: var(--danger); }

    /* ── Submit button ── */
    .btn-register {
        height: 54px;
        border: none;
        border-radius: var(--radius-md);
        background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
        color: #fff;
        font-family: 'Poppins', sans-serif;
        font-weight: 600;
        font-size: .95rem;
        letter-spacing: .02em;
        box-shadow: 0 8px 22px rgba(29,78,216,.32);
        transition: transform .18s ease, box-shadow .18s ease, opacity .18s ease;
        position: relative;
        overflow: hidden;
    }
    .btn-register::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, var(--brand-2), var(--brand-3));
        opacity: 0;
        transition: opacity .25s ease;
    }
    .btn-register:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(29,78,216,.42);
    }
    .btn-register:hover::before { opacity: 1; }
    .btn-register span, .btn-register i { position: relative; z-index: 2; }
    .btn-register:active { transform: translateY(0); }
    .btn-register:disabled { opacity: .75; cursor: not-allowed; transform: none; }

    .auth-footer-link {
        text-align: center;
        font-size: .85rem;
        color: var(--text-muted);
        margin-top: 1.6rem;
    }
    .auth-footer-link a {
        color: var(--brand-2);
        font-weight: 600;
        text-decoration: none;
    }
    .auth-footer-link a:hover { text-decoration: underline; }

    /* ── Entrance animation for the form fields ── */
    .stagger-in {
        animation: fieldIn .45s ease both;
    }
    @keyframes fieldIn {
        from { opacity: 0; transform: translateY(14px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Mobile responsiveness ── */
    @media (max-width: 860px) {
        .auth-shell {
            flex-direction: column;
            max-width: 460px;
            min-height: auto;
        }
        .auth-illustration {
            flex: none;
            padding: 2.25rem 1.75rem;
        }
        .illu-feature-list { display: none; }
        .auth-illustration p.welcome-text { margin-bottom: 0; }
        .auth-form-panel {
            padding: 2.25rem 1.75rem 2rem;
            background: rgba(255,255,255,.92);
        }
    }
    @media (max-width: 420px) {
        body { padding: .75rem; }
        .auth-form-panel { padding: 2rem 1.25rem 1.75rem; }
        .auth-illustration { padding: 1.85rem 1.25rem; }
    }
</style>
</head>
<body>

<div class="auth-shell">

    <!-- ════════ LEFT: Branding / Illustration Panel ════════ -->
    <div class="auth-illustration">

        <div class="auth-logo-row">
            <div class="logo-badge"><img src="<?= htmlspecialchars(site_logo_url()) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:10px;"></div>
            <span><?= htmlspecialchars(SITE_NAME) ?></span>
        </div>

        <div class="illu-icon-ring">
            <i class="bi bi-easel2-fill"></i>
        </div>
        <h2>Welcome to <?= htmlspecialchars(SITE_NAME) ?></h2>
        <p class="welcome-text">Join thousands of learners building real skills with hands-on courses, quizzes, and certificates.</p>

        <ul class="illu-feature-list">
            <li><i class="bi bi-play-circle-fill"></i> Learn at your own pace</li>
            <li><i class="bi bi-patch-question-fill"></i> Test yourself with quizzes</li>
            <li><i class="bi bi-award-fill"></i> Earn verified certificates</li>
        </ul>
    </div>

    <!-- ════════ RIGHT: Registration Form ════════ -->
    <div class="auth-form-panel">

        <h1 class="auth-title">Create your account</h1>
        <p class="auth-subtitle">Sign up to start your learning journey</p>

        <?php if ($error): ?>
            <div class="alert-modern danger" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm" novalidate>

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <!-- Full Name -->
            <div class="field-group stagger-in" style="animation-delay:.05s;">
                <i class="bi bi-person-fill field-icon"></i>
                <input
                    type="text"
                    id="name"
                    name="name"
                    class="form-control"
                    placeholder=" "
                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    autocomplete="name"
                    required>
                <label for="name" class="floating-label">Full Name</label>
            </div>

            <!-- Email -->
            <div class="field-group stagger-in" style="animation-delay:.10s;">
                <i class="bi bi-envelope-fill field-icon"></i>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder=" "
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    autocomplete="email"
                    required>
                <label for="email" class="floating-label">Email Address</label>
            </div>

            <!-- Password -->
            <div class="field-group stagger-in" style="animation-delay:.15s;">
                <i class="bi bi-lock-fill field-icon"></i>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder=" "
                    autocomplete="new-password"
                    minlength="6"
                    required>
                <label for="password" class="floating-label">Password</label>
                <button type="button" class="toggle-pw-btn" id="togglePw1" tabindex="-1" aria-label="Toggle password visibility">
                    <i class="bi bi-eye-fill"></i>
                </button>
            </div>

            <!-- Confirm Password -->
            <div class="field-group stagger-in" style="animation-delay:.20s;">
                <i class="bi bi-shield-lock-fill field-icon"></i>
                <input
                    type="password"
                    id="confirmPassword"
                    name="confirm_password"
                    class="form-control"
                    placeholder=" "
                    autocomplete="new-password"
                    required>
                <label for="confirmPassword" class="floating-label">Confirm Password</label>
                <button type="button" class="toggle-pw-btn" id="togglePw2" tabindex="-1" aria-label="Toggle confirm password visibility">
                    <i class="bi bi-eye-fill"></i>
                </button>
            </div>
            <div class="match-hint" id="matchHint">
                <i class="bi bi-check-circle-fill"></i><span>Passwords match</span>
            </div>

            <div style="height:.6rem;"></div>

            <button type="submit" name="register" class="btn btn-register w-100 stagger-in" id="submitBtn" style="animation-delay:.25s;">
                <span id="submitBtnText"><i class="bi bi-person-plus-fill me-2"></i>Create Account</span>
            </button>

        </form>

        <div class="auth-footer-link">
            Already have an account? <a href="login.php">Login</a>
        </div>

    </div>
</div>

<?php render_site_footer(''); ?>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    'use strict';

    // ── Password show/hide toggles ──────────────────────────────
    function wireToggle(btnId, inputId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        if (!btn || !input) return;
        btn.addEventListener('click', function () {
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            const icon = btn.querySelector('i');
            icon.classList.toggle('bi-eye-fill', !isHidden);
            icon.classList.toggle('bi-eye-slash-fill', isHidden);
        });
    }
    wireToggle('togglePw1', 'password');
    wireToggle('togglePw2', 'confirmPassword');

    // ── Confirm password match indicator ────────────────────────
    const pwInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmPassword');
    const matchHint = document.getElementById('matchHint');

    function checkMatch() {
        if (confirmInput.value.length === 0) {
            matchHint.classList.remove('show', 'ok', 'bad');
            confirmInput.classList.remove('is-invalid');
            return;
        }
        matchHint.classList.add('show');
        const icon = matchHint.querySelector('i');
        const text = matchHint.querySelector('span');

        if (pwInput.value === confirmInput.value) {
            matchHint.classList.add('ok');
            matchHint.classList.remove('bad');
            confirmInput.classList.remove('is-invalid');
            icon.className = 'bi bi-check-circle-fill';
            text.textContent = 'Passwords match';
        } else {
            matchHint.classList.add('bad');
            matchHint.classList.remove('ok');
            confirmInput.classList.add('is-invalid');
            icon.className = 'bi bi-x-circle-fill';
            text.textContent = 'Passwords do not match';
        }
    }
    pwInput.addEventListener('input', checkMatch);
    confirmInput.addEventListener('input', checkMatch);

    // ── Submit: client-side confirm-password guard + loading state ──
    // Note: this is a UI-only safeguard. All real validation
    // (required fields, email format, password length, duplicate
    // email, CSRF) is enforced server-side in the unchanged PHP
    // block above — this script never bypasses or replaces it.
    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitBtnText = document.getElementById('submitBtnText');

    form.addEventListener('submit', function (e) {
        if (pwInput.value !== confirmInput.value) {
            e.preventDefault();
            checkMatch();
            confirmInput.focus();
            return;
        }

        submitBtn.disabled = true;
        submitBtnText.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Creating account…';
    });
})();
</script>
</body>
</html>