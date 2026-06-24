<?php
/**
 * admin/edit-user.php — Edit Existing User
 * ───────────────────────────────────────────
 * Placement: lms-project/admin/edit-user.php
 *
 * Fields: Name, Email, Role.
 *
 * Validation:
 *  - Name required
 *  - Email required, valid format, unique (excluding this user's own row)
 *  - Role must be 'admin' or 'student'
 *  - An admin editing their OWN account cannot change their role away
 *    from 'admin' (prevents accidental self-demotion / lockout)
 *
 * No schema changes. Prepared statements + CSRF throughout.
 */

define('BASE_URL', '../');
$currentPage = 'users';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Validate ID parameter ──────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Invalid user ID.';
    header('Location: users.php');
    exit();
}

// ── Fetch existing user ────────────────────────────────────────
$fetchStmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1");
$fetchStmt->bind_param('i', $id);
$fetchStmt->execute();
$user = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$user) {
    $_SESSION['flash_error'] = 'User not found.';
    header('Location: users.php');
    exit();
}

$loggedInId = (int)$_SESSION['user_id'];
$isSelf     = ($id === $loggedInId);

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── CSRF check ──────────────────────────────────────────────
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        // ── Collect & sanitize ──────────────────────────────────
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? '';

        // ── Validate: Name ──────────────────────────────────────
        if ($name === '') {
            $errors[] = 'Name is required.';
        } elseif (mb_strlen($name) > 150) {
            $errors[] = 'Name must be 150 characters or fewer.';
        }

        // ── Validate: Email ─────────────────────────────────────
        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Unique check — excluding this user's own current row
            $dupStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $dupStmt->bind_param('si', $email, $id);
            $dupStmt->execute();
            if ($dupStmt->get_result()->num_rows > 0) {
                $errors[] = 'This email is already used by another account.';
            }
            $dupStmt->close();
        }

        // ── Validate: Role ──────────────────────────────────────
        if (!in_array($role, ['admin', 'student'], true)) {
            $errors[] = 'Role must be either Admin or Student.';
        }

        // ── Self-protection rules ────────────────────────────────
        // An admin editing their OWN account can never end up as
        // 'student' — this would either remove their own admin
        // access (if changed deliberately) or lock them out of the
        // admin panel entirely. Both are blocked the same way: the
        // role is forced back to 'admin' for self-edits, with a
        // clear message instead of a silent override.
        if (empty($errors) && $isSelf && $role !== 'admin') {
            $errors[] = 'You cannot change your own role away from Admin.';
        }

        // ── Save if no errors ────────────────────────────────────
        if (empty($errors)) {
            $updateStmt = $conn->prepare(
                "UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?"
            );
            $updateStmt->bind_param('sssi', $name, $email, $role, $id);

            if ($updateStmt->execute()) {
                // Refresh user data after update
                $fetchStmt = $conn->prepare("SELECT id, name, email, role, created_at FROM users WHERE id = ? LIMIT 1");
                $fetchStmt->bind_param('i', $id);
                $fetchStmt->execute();
                $user = $fetchStmt->get_result()->fetch_assoc();
                $fetchStmt->close();

                // Keep the logged-in session's own name/email/role in
                // sync if the admin just edited their own account.
                if ($isSelf) {
                    $_SESSION['user_name']  = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role']  = $user['role'];
                }

                $success = 'User updated successfully!';
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
            $updateStmt->close();
        }
    }
}

// ── CSRF token ──────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isAdmin = ($user['role'] === 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>
<?php include 'includes/sidebar.php'; ?>

<div class="lms-main">

    <header class="lms-topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle"><i class="bi bi-list"></i></button>
            <div>
                <div class="page-title">Edit User</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="users.php">Users</a> &rsaquo; Edit
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="view-user.php?id=<?= $id ?>" class="btn-lms-outline">
                <i class="bi bi-arrow-left"></i> <span>Back to Profile</span>
            </a>
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
                — <a href="view-user.php?id=<?= $id ?>" style="color:inherit;font-weight:600;">View Profile</a>
            </div>
        <?php endif; ?>

        <!-- User ID badge -->
        <div style="margin-bottom:1rem;font-size:.8rem;color:var(--text-muted);">
            Editing user ID: <strong>#<?= $user['id'] ?></strong>
            <?php if ($isSelf): ?>
                <span style="margin-left:.4rem;font-size:.68rem;font-weight:700;color:var(--brand);background:var(--brand-light);border-radius:20px;padding:.1em .55em;">YOUR ACCOUNT</span>
            <?php endif; ?>
        </div>

        <div class="row g-3">

            <!-- LEFT: Edit Form -->
            <div class="col-lg-8">
                <form method="POST" action="edit-user.php?id=<?= $id ?>" data-loading novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="lms-card">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-pencil-square"></i> Account Details</h5>
                        </div>
                        <div class="lms-card-body">

                            <div class="mb-3">
                                <label class="lms-label">Full Name <span class="req">*</span></label>
                                <input type="text" name="name" class="lms-input"
                                       value="<?= htmlspecialchars($_POST['name'] ?? $user['name']) ?>"
                                       maxlength="150" required>
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Email <span class="req">*</span></label>
                                <input type="email" name="email" class="lms-input"
                                       value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>"
                                       maxlength="190" required>
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Role <span class="req">*</span></label>
                                <select name="role" class="lms-select" <?= $isSelf ? 'disabled' : '' ?> required>
                                    <?php
                                    $selectedRole = $_POST['role'] ?? $user['role'];
                                    foreach (['admin', 'student'] as $r):
                                    ?>
                                    <option value="<?= $r ?>" <?= $selectedRole === $r ? 'selected' : '' ?>>
                                        <?= ucfirst($r) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($isSelf): ?>
                                    <!-- Disabled selects don't submit a value, so a hidden field
                                         carries the locked-in 'admin' role for self-edits. -->
                                    <input type="hidden" name="role" value="admin">
                                    <div style="font-size:.76rem;color:var(--text-muted);margin-top:.4rem;">
                                        <i class="bi bi-info-circle-fill"></i>
                                        You cannot change your own role away from Admin.
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn-lms-primary" style="padding:.65rem 1.5rem;">
                            <i class="bi bi-check-lg"></i> Save Changes
                        </button>
                        <a href="view-user.php?id=<?= $id ?>" class="btn-lms-outline" style="padding:.65rem 1.5rem;">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- RIGHT: Read-only summary -->
            <div class="col-lg-4">
                <div class="lms-card" style="overflow:visible;">
                    <div class="lms-card-body" style="text-align:center;padding:2rem 1.5rem;">
                        <div style="
                            width:72px;height:72px;border-radius:50%;margin:0 auto 1rem;
                            background:<?= $isAdmin ? 'linear-gradient(135deg,#dc2626,#991b1b)' : 'linear-gradient(135deg,#16a34a,#15803d)' ?>;
                            display:flex;align-items:center;justify-content:center;
                            font-family:'Sora',sans-serif;font-weight:700;font-size:1.8rem;color:#fff;
                        ">
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        </div>
                        <h6 style="font-family:'Sora',sans-serif;font-weight:700;margin-bottom:.2rem;">
                            <?= htmlspecialchars($user['name']) ?>
                        </h6>
                        <div style="color:var(--text-muted);font-size:.82rem;margin-bottom:.85rem;">
                            <?= htmlspecialchars($user['email']) ?>
                        </div>
                        <?php if ($isAdmin): ?>
                            <span class="lms-badge" style="background:#fee2e2;color:#991b1b;">
                                <i class="bi bi-shield-fill"></i> Admin
                            </span>
                        <?php else: ?>
                            <span class="lms-badge" style="background:#dcfce7;color:#15803d;">
                                <i class="bi bi-person-fill"></i> Student
                            </span>
                        <?php endif; ?>
                    </div>
                    <div style="border-top:1px solid var(--border);padding:.85rem 1.5rem;font-size:.875rem;">
                        <span style="color:var(--text-muted);font-weight:600;">Member Since</span>
                        <span style="float:right;font-weight:600;"><?= date('d M Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>
            </div>

        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>