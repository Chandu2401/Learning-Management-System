<?php
/**
 * login.php — LMS Login Page
 * Handles user authentication with session management.
 */

session_start();

require_once 'config/db.php';
require_once 'includes/site-info.php';

// If user is already logged in, redirect to the correct dashboard
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['user_role'] ?? '') === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Sanitize inputs
    $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
    $password = trim($_POST['password'] ?? '');

    // 2. Basic validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {

        // 3. Fetch user by email using prepared statement
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            // 4. Verify password with password_verify()
            if (password_verify($password, $user['password'])) {

                // 5. Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // 6. Store user info in session
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role']  = $user['role'];
                $_SESSION['logged_in']  = true;

                // 7. Redirect based on role
                if ($user['role'] === 'admin') {
                    header("Location: admin/index.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();

            } else {
                // Wrong password — use a generic message to prevent user enumeration
                $error = 'Invalid email or password.';
            }
        } else {
            // No user found — same generic message
            $error = 'Invalid email or password.';
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= htmlspecialchars(SITE_NAME) ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=Inter:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-primary:   #2563eb;
            --brand-dark:      #1e40af;
            --brand-light:     #eff6ff;
            --surface:         #ffffff;
            --text-main:       #1e293b;
            --text-muted:      #64748b;
            --border:          #e2e8f0;
            --danger:          #dc2626;
            --success:         #16a34a;
            --radius:          14px;
            --shadow-card:     0 8px 40px rgba(37,99,235,.10), 0 1.5px 6px rgba(0,0,0,.06);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 50%, #e0f2fe 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        /* ── Decorative blobs ── */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .35;
            pointer-events: none;
            z-index: 0;
        }
        body::before {
            width: 420px; height: 420px;
            background: radial-gradient(circle, #93c5fd, #3b82f6);
            top: -120px; left: -120px;
        }
        body::after {
            width: 380px; height: 380px;
            background: radial-gradient(circle, #bfdbfe, #60a5fa);
            bottom: -100px; right: -100px;
        }

        /* ── Card ── */
        .auth-card {
            position: relative;
            z-index: 1;
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: var(--shadow-card);
            width: 100%;
            max-width: 460px;
            padding: 2.75rem 2.5rem 2.5rem;
            animation: fadeUp .45s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0);    }
        }

        /* ── Brand header ── */
        .auth-logo {
            width: 64px; height: 64px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.1rem;
            box-shadow: 0 4px 16px rgba(37,99,235,.30);
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--border);
        }
        .auth-logo img { width: 100%; height: 100%; object-fit: cover; }

        .auth-institute-name {
            font-family: 'Sora', sans-serif;
            font-size: .92rem;
            font-weight: 700;
            color: var(--brand-primary);
            text-align: center;
            letter-spacing: .02em;
            margin-bottom: .35rem;
            text-transform: uppercase;
        }

        .auth-title {
            font-family: 'Sora', sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            text-align: center;
            margin-bottom: .3rem;
        }
        .auth-subtitle {
            font-size: .875rem;
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 2rem;
        }

        /* ── Form controls ── */
        .form-label {
            font-size: .825rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: .4rem;
            letter-spacing: .01em;
        }
        .input-group-text {
            background: var(--brand-light);
            border-color: var(--border);
            color: var(--brand-primary);
        }
        .form-control {
            border-color: var(--border);
            border-radius: 0 8px 8px 0 !important;
            font-size: .9rem;
            padding: .6rem .9rem;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-group .input-group-text {
            border-radius: 8px 0 0 8px !important;
        }
        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,.12);
        }

        /* toggle password icon */
        .toggle-pw {
            cursor: pointer;
            border-radius: 0 8px 8px 0 !important;
            border-left: 0 !important;
            background: var(--brand-light);
            border-color: var(--border);
            color: var(--text-muted);
            transition: color .2s;
        }
        .toggle-pw:hover { color: var(--brand-primary); }

        /* ── Alert ── */
        .alert { font-size: .875rem; border-radius: 10px; }

        /* ── Submit button ── */
        .btn-login {
            background: linear-gradient(135deg, var(--brand-primary), #1d4ed8);
            border: none;
            border-radius: 10px;
            font-family: 'Sora', sans-serif;
            font-size: .95rem;
            font-weight: 600;
            padding: .72rem;
            letter-spacing: .02em;
            transition: transform .15s, box-shadow .15s;
            box-shadow: 0 4px 14px rgba(37,99,235,.30);
        }
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(37,99,235,.38);
        }
        .btn-login:active { transform: translateY(0); }

        /* ── Footer links ── */
        .auth-footer {
            font-size: .825rem;
            color: var(--text-muted);
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }
        .auth-footer a {
            color: var(--brand-primary);
            text-decoration: none;
            font-weight: 600;
        }
        .auth-footer a:hover { text-decoration: underline; }

        /* ── Remember / Forgot row ── */
        .form-check-label { font-size: .85rem; color: var(--text-muted); cursor: pointer; }
        .form-check-input:checked { background-color: var(--brand-primary); border-color: var(--brand-primary); }
        .forgot-link {
            font-size: .85rem;
            color: var(--brand-primary);
            text-decoration: none;
            font-weight: 500;
        }
        .forgot-link:hover { text-decoration: underline; }

        .page-wrap { width: 100%; max-width: 460px; }
    </style>
</head>
<body>

<div class="page-wrap">

<div class="auth-card">

    <!-- Logo + Title -->
    <div class="auth-logo">
        <img src="<?= htmlspecialchars(site_logo_url()) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?> logo">
    </div>
    <div class="auth-institute-name"><?= htmlspecialchars(SITE_NAME) ?></div>
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-subtitle">Sign in to your <?= htmlspecialchars(SITE_NAME) ?> account</p>

    <!-- Alert Messages -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-check-circle-fill flex-shrink-0"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="login.php" novalidate>

        <!-- CSRF Token -->
        <?php
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        ?>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <!-- Email -->
        <div class="mb-3">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="you@example.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                >
            </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
                <button class="btn toggle-pw" type="button" id="togglePassword" tabindex="-1" aria-label="Toggle password visibility">
                    <i class="bi bi-eye-fill" id="toggleIcon"></i>
                </button>
            </div>
        </div>

        <!-- Remember Me + Forgot Password -->
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="rememberMe" name="remember">
                <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
            <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
        </div>

        <!-- Submit -->
        <button type="submit" class="btn btn-login btn-primary w-100 text-white">
            <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
        </button>

    </form>

    <!-- Footer -->
    <div class="auth-footer">
        Don't have an account? <a href="register.php">Create one</a>
    </div>

</div>

<?php render_site_footer(''); ?>

</div>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle password visibility
    const toggleBtn  = document.getElementById('togglePassword');
    const pwInput    = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');

    toggleBtn.addEventListener('click', () => {
        const isHidden = pwInput.type === 'password';
        pwInput.type   = isHidden ? 'text' : 'password';
        toggleIcon.classList.toggle('bi-eye-fill',      !isHidden);
        toggleIcon.classList.toggle('bi-eye-slash-fill', isHidden);
    });

    // Prevent double-submit
    document.querySelector('form').addEventListener('submit', function () {
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Signing in…';
    });
</script>
</body>
</html>