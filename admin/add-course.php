<?php
/**
 * admin/add-course.php — Add New Course
 * ──────────────────────────────────────
 * Placement: lms-project/admin/add-course.php
 */

define('BASE_URL', '../');
$currentPage = 'add_course';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Upload configuration ───────────────────────────────────────
define('UPLOAD_DIR',     '../uploads/courses/');
define('UPLOAD_URL',     'uploads/courses/');
define('MAX_FILE_SIZE',  3 * 1024 * 1024);  // 3 MB
define('ALLOWED_TYPES',  ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_EXT',    ['jpg', 'jpeg', 'png', 'webp', 'gif']);

$errors  = [];
$success = '';
$old     = [];   // repopulate form on validation failure

// Helper: slugify
function makeSlug(string $text): string {
    $text = mb_strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        // ── Collect & sanitize inputs ──────────────────────────
        $title        = trim(htmlspecialchars($_POST['title']        ?? ''));
        $description  = trim(htmlspecialchars($_POST['description']  ?? ''));
        $short_desc   = trim(htmlspecialchars($_POST['short_desc']   ?? ''));
        $category     = trim(htmlspecialchars($_POST['category']     ?? ''));
        $level        = $_POST['level']    ?? 'beginner';
        $language     = trim(htmlspecialchars($_POST['language']     ?? 'English'));
        $duration     = trim(htmlspecialchars($_POST['duration']     ?? ''));
        $total_lessons= (int)($_POST['total_lessons'] ?? 0);
        $status       = $_POST['status']   ?? 'draft';
        $is_free      = isset($_POST['is_free']) ? 1 : 0;
        $price        = $is_free ? 0.00 : (float)($_POST['price'] ?? 0);

        $old = compact('title','description','short_desc','category','level',
                       'language','duration','total_lessons','status','is_free','price');

        // ── Validate ───────────────────────────────────────────
        if (empty($title))       $errors[] = 'Course title is required.';
        if (strlen($title) > 200) $errors[] = 'Title must be 200 characters or fewer.';
        if (empty($description)) $errors[] = 'Description is required.';
        if (!in_array($level,  ['beginner','intermediate','advanced'])) $errors[] = 'Invalid level.';
        if (!in_array($status, ['active','inactive','draft']))           $errors[] = 'Invalid status.';
        if (!$is_free && $price <= 0) $errors[] = 'Please enter a valid price for paid courses.';

        // ── Generate unique slug ───────────────────────────────
        $baseSlug = makeSlug($title);
        $slug     = $baseSlug;
        $i        = 1;
        while (true) {
            $chk = $conn->prepare("SELECT id FROM courses WHERE slug = ? LIMIT 1");
            $chk->bind_param("s", $slug);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) break;
            $slug = $baseSlug . '-' . $i++;
            $chk->close();
        }

        // ── Handle image upload ────────────────────────────────
        $imagePath = null;

        if (!empty($_FILES['image']['name'])) {
            $file     = $_FILES['image'];
            $origName = basename($file['name']);
            $tmpPath  = $file['tmp_name'];
            $fileSize = $file['size'];
            $mimeType = mime_content_type($tmpPath);   // server-side MIME check
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload error. Please try again.';
            } elseif ($fileSize > MAX_FILE_SIZE) {
                $errors[] = 'Image must be smaller than 3 MB.';
            } elseif (!in_array($mimeType, ALLOWED_TYPES)) {
                $errors[] = 'Only JPG, PNG, WEBP, and GIF images are allowed.';
            } elseif (!in_array($ext, ALLOWED_EXT)) {
                $errors[] = 'Invalid file extension.';
            } else {
                // Create upload directory if it doesn't exist
                if (!is_dir(UPLOAD_DIR)) {
                    mkdir(UPLOAD_DIR, 0755, true);
                }

                // Generate unique filename
                $newFilename = 'course_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
                $destination = UPLOAD_DIR . $newFilename;

                if (move_uploaded_file($tmpPath, $destination)) {
                    $imagePath = UPLOAD_URL . $newFilename;
                } else {
                    $errors[] = 'Failed to save the uploaded image.';
                }
            }
        }

        // ── Insert into DB ─────────────────────────────────────
        if (empty($errors)) {
            $adminId = $_SESSION['user_id'];
            $stmt    = $conn->prepare("
                INSERT INTO courses
                    (title, slug, description, short_desc, image, category, level, language,
                     price, is_free, duration, total_lessons, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "sssssssssdissi",
                $title, $slug, $description, $short_desc, $imagePath,
                $category, $level, $language,
                $price, $is_free, $duration, $total_lessons, $status, $adminId
            );

            if ($stmt->execute()) {
                $newId   = $stmt->insert_id;
                $success = 'Course <strong>' . htmlspecialchars($title) . '</strong> added successfully!';
                $old     = [];  // clear form
            } else {
                $errors[] = 'Database error: ' . $conn->error;
                // If DB insert failed, remove uploaded file
                if ($imagePath && file_exists('../' . $imagePath)) {
                    unlink('../' . $imagePath);
                }
            }
            $stmt->close();
        }
    }
}

// CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Course — LMS Admin</title>
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
                <div class="page-title">Add New Course</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="course-list.php">Courses</a> &rsaquo; Add
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="course-list.php" class="btn-lms-outline">
                <i class="bi bi-arrow-left"></i> <span>Back to List</span>
            </a>
        </div>
    </header>

    <main class="lms-body">

        <!-- Alerts -->
        <?php if (!empty($errors)): ?>
            <div class="lms-alert lms-alert-danger" data-autohide>
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>
                    <?php if (count($errors) === 1): ?>
                        <?= $errors[0] ?>
                    <?php else: ?>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            <?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="lms-alert lms-alert-success" data-autohide>
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    <?= $success ?>
                    — <a href="course-list.php" style="color:inherit;font-weight:600;">View All Courses</a>
                    &nbsp;|&nbsp; <a href="add-course.php" style="color:inherit;font-weight:600;">Add Another</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="add-course.php"
              enctype="multipart/form-data"
              data-loading
              novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-3">

                <!-- LEFT COL: Main Details -->
                <div class="col-lg-8">

                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-pencil-square"></i> Course Details</h5>
                        </div>
                        <div class="lms-card-body">

                            <p class="form-section-title">Basic Information</p>

                            <div class="mb-3">
                                <label class="lms-label">Course Title <span class="req">*</span></label>
                                <input type="text" name="title" class="lms-input"
                                       placeholder="e.g. Complete PHP & MySQL Bootcamp"
                                       value="<?= htmlspecialchars($old['title'] ?? '') ?>"
                                       maxlength="200" required>
                                <div class="field-hint">Keep it clear and descriptive (max 200 chars)</div>
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Short Description</label>
                                <input type="text" name="short_desc" class="lms-input"
                                       placeholder="A one-line summary shown on course cards"
                                       value="<?= htmlspecialchars($old['short_desc'] ?? '') ?>"
                                       maxlength="500">
                            </div>

                            <div class="mb-3">
                                <label class="lms-label">Full Description <span class="req">*</span></label>
                                <textarea name="description" class="lms-textarea"
                                          placeholder="Describe what students will learn, prerequisites, who this course is for…"
                                          required><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                            </div>

                            <p class="form-section-title">Classification</p>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="lms-label">Category</label>
                                    <input type="text" name="category" class="lms-input"
                                           placeholder="e.g. Web Development"
                                           value="<?= htmlspecialchars($old['category'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="lms-label">Level <span class="req">*</span></label>
                                    <select name="level" class="lms-select" required>
                                        <?php foreach (['beginner','intermediate','advanced'] as $lvl): ?>
                                        <option value="<?= $lvl ?>"
                                            <?= (($old['level'] ?? 'beginner') === $lvl) ? 'selected' : '' ?>>
                                            <?= ucfirst($lvl) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="lms-label">Language</label>
                                    <input type="text" name="language" class="lms-input"
                                           placeholder="English"
                                           value="<?= htmlspecialchars($old['language'] ?? 'English') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="lms-label">Duration</label>
                                    <input type="text" name="duration" class="lms-input"
                                           placeholder="e.g. 12 hours"
                                           value="<?= htmlspecialchars($old['duration'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="lms-label">Total Lessons</label>
                                    <input type="number" name="total_lessons" class="lms-input"
                                           min="0" placeholder="0"
                                           value="<?= (int)($old['total_lessons'] ?? 0) ?>">
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- RIGHT COL: Image, Pricing, Status -->
                <div class="col-lg-4">

                    <!-- Course Image -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-image-fill"></i> Course Image</h5>
                        </div>
                        <div class="lms-card-body">
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="image" id="courseImage"
                                       accept=".jpg,.jpeg,.png,.webp,.gif">
                                <div class="upload-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                                <div class="upload-title">Drag & drop or click to upload</div>
                                <div class="upload-hint">JPG, PNG, WEBP, GIF &mdash; max 3 MB<br>Recommended: 800 × 450 px</div>
                            </div>
                            <div class="image-preview-wrap" id="imagePreviewWrap">
                                <img id="imagePreview" src="" alt="Preview" style="width:100%;">
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-cash-coin"></i> Pricing</h5>
                        </div>
                        <div class="lms-card-body">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           name="is_free" id="isFree"
                                           <?= (!empty($old['is_free']) || empty($old)) ? 'checked' : '' ?>
                                           onchange="document.getElementById('priceRow').style.display=this.checked?'none':'block'">
                                    <label class="form-check-label" for="isFree">
                                        <strong>Free Course</strong>
                                    </label>
                                </div>
                                <div class="field-hint">Toggle off to set a price.</div>
                            </div>
                            <div id="priceRow" style="<?= (!empty($old) && empty($old['is_free'])) ? '' : 'display:none' ?>">
                                <label class="lms-label">Price (₹) <span class="req">*</span></label>
                                <input type="number" name="price" class="lms-input"
                                       min="1" step="0.01" placeholder="499.00"
                                       value="<?= htmlspecialchars($old['price'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-toggle-on"></i> Publish Status</h5>
                        </div>
                        <div class="lms-card-body">
                            <?php foreach (['draft'=>'Draft','active'=>'Active (Published)','inactive'=>'Inactive'] as $val => $label): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio"
                                       name="status" id="status_<?= $val ?>" value="<?= $val ?>"
                                       <?= (($old['status'] ?? 'draft') === $val) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="status_<?= $val ?>">
                                    <?= $label ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <div class="field-hint mt-1">Only <strong>Active</strong> courses are visible to students.</div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-lms-primary w-100" style="justify-content:center;padding:.7rem;">
                        <i class="bi bi-check-circle-fill"></i> Save Course
                    </button>
                    <a href="course-list.php" class="btn-lms-outline w-100 mt-2" style="justify-content:center;">
                        Cancel
                    </a>

                </div>
            </div>

        </form>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>