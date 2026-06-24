<?php
/**
 * admin/add-lesson.php — Add New Lesson
 * ───────────────────────────────────────
 * Placement: lms-project/admin/add-lesson.php
 *
 * Supports:
 *  - Course dropdown (loaded from DB)
 *  - Pre-selecting a course via ?course_id=X (from course-list context button)
 *  - YouTube video URL (stored as-is; sanitized on output)
 *  - PDF notes upload → uploads/lessons/
 *  - Sort order, preview flag, status
 */

define('BASE_URL', '../');
$currentPage = 'add_lesson';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Upload configuration ───────────────────────────────────────
define('PDF_UPLOAD_DIR',  '../uploads/lessons/');
define('PDF_UPLOAD_URL',  'uploads/lessons/');
define('PDF_MAX_SIZE',    10 * 1024 * 1024);        // 10 MB
define('PDF_MIME',        'application/pdf');
define('PDF_EXT',         'pdf');

$errors  = [];
$success = '';
$old     = [];

// ── Load all active/draft courses for dropdown ─────────────────
$courseRes = $conn->query(
    "SELECT id, title FROM courses ORDER BY title ASC"
);
$allCourses = [];
while ($row = $courseRes->fetch_assoc()) $allCourses[] = $row;

// Pre-select from query string (when navigating from course view)
$preselectedCourse = (int)($_GET['course_id'] ?? 0);

// ── Handle POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        // Collect & sanitize
        $course_id   = (int)($_POST['course_id']   ?? 0);
        $title       = trim(htmlspecialchars($_POST['title']       ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $video_url   = trim($_POST['video_url'] ?? '');
        $sort_order  = max(0, (int)($_POST['sort_order'] ?? 0));
        $is_preview  = isset($_POST['is_preview']) ? 1 : 0;
        $status      = $_POST['status'] ?? 'published';

        $old = compact('course_id','title','description','video_url','sort_order','is_preview','status');

        // Validate
        if ($course_id <= 0)       $errors[] = 'Please select a course.';
        if (empty($title))         $errors[] = 'Lesson title is required.';
        if (strlen($title) > 200)  $errors[] = 'Title must be 200 characters or fewer.';
        if (!in_array($status, ['published','draft'])) $errors[] = 'Invalid status.';

        // Validate course exists
        if ($course_id > 0) {
            $chk = $conn->prepare("SELECT id FROM courses WHERE id = ? LIMIT 1");
            $chk->bind_param('i', $course_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) $errors[] = 'Selected course does not exist.';
            $chk->close();
        }

        // Validate YouTube URL (optional but if provided must look like a YT URL)
        if (!empty($video_url)) {
            $ytPattern = '/^https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)[\w\-]{11}/i';
            if (!preg_match($ytPattern, $video_url)) {
                $errors[] = 'Please enter a valid YouTube video URL (e.g. https://www.youtube.com/watch?v=...).';
            }
        }

        // Handle PDF upload
        $pdfPath = null;
        if (!empty($_FILES['pdf_notes']['name'])) {
            $file     = $_FILES['pdf_notes'];
            $tmpPath  = $file['tmp_name'];
            $fileSize = $file['size'];
            $origName = basename($file['name']);
            $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $mimeType = mime_content_type($tmpPath);

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'File upload error (code ' . $file['error'] . '). Please try again.';
            } elseif ($fileSize > PDF_MAX_SIZE) {
                $errors[] = 'PDF must be smaller than 10 MB.';
            } elseif ($mimeType !== PDF_MIME || $ext !== PDF_EXT) {
                $errors[] = 'Only PDF files are accepted for lesson notes.';
            } else {
                if (!is_dir(PDF_UPLOAD_DIR)) mkdir(PDF_UPLOAD_DIR, 0755, true);

                $newFilename = 'lesson_' . time() . '_' . bin2hex(random_bytes(5)) . '.pdf';
                $destination = PDF_UPLOAD_DIR . $newFilename;

                if (move_uploaded_file($tmpPath, $destination)) {
                    $pdfPath = PDF_UPLOAD_URL . $newFilename;
                } else {
                    $errors[] = 'Failed to save the uploaded PDF.';
                }
            }
        }

        // Insert
        if (empty($errors)) {
            $adminId = $_SESSION['user_id'];
            $stmt    = $conn->prepare("
                INSERT INTO lessons
                    (course_id, title, description, video_url, pdf_notes,
                     sort_order, is_preview, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                'issssiis',
                $course_id, $title, $description, $video_url,
                $pdfPath, $sort_order, $is_preview, $status
            );

            if ($stmt->execute()) {
                $success = 'Lesson <strong>' . htmlspecialchars($title) . '</strong> added successfully!';
                $old     = [];
            } else {
                $errors[] = 'Database error: ' . $conn->error;
                if ($pdfPath && file_exists('../' . $pdfPath)) unlink('../' . $pdfPath);
            }
            $stmt->close();
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Auto-suggest next sort order for a course (for UX)
function nextSortOrder(mysqli $conn, int $courseId): int {
    if ($courseId <= 0) return 1;
    $r = $conn->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS nxt FROM lessons WHERE course_id = ?");
    $r->bind_param('i', $courseId);
    $r->execute();
    return (int)$r->get_result()->fetch_assoc()['nxt'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Lesson — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        /* ── YouTube preview ── */
        .yt-preview-wrap {
            margin-top: .75rem;
            border-radius: var(--radius-sm);
            overflow: hidden;
            border: 1px solid var(--border);
            display: none;
            aspect-ratio: 16/9;
            background: #000;
        }
        .yt-preview-wrap iframe { width: 100%; height: 100%; border: none; display: block; }

        /* ── PDF upload zone ── */
        .pdf-upload-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius);
            padding: 1.75rem 1.5rem;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }
        .pdf-upload-zone:hover, .pdf-upload-zone.dragging {
            border-color: #f97316;
            background: #fff7ed;
        }
        .pdf-upload-zone input[type="file"] {
            position: absolute; inset: 0; opacity: 0; cursor: pointer;
        }
        .pdf-icon { font-size: 2rem; color: #f97316; margin-bottom: .5rem; }
        .pdf-selected {
            display: none;
            align-items: center;
            gap: .6rem;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: var(--radius-sm);
            padding: .65rem .9rem;
            margin-top: .75rem;
            font-size: .85rem;
        }
        .pdf-selected i { color: #f97316; font-size: 1.1rem; }
        .pdf-selected .pdf-name { font-weight: 600; color: var(--text); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pdf-selected .pdf-size { color: var(--text-muted); white-space: nowrap; }
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
                <div class="page-title">Add New Lesson</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="lesson-list.php">Lessons</a> &rsaquo; Add
                </div>
            </div>
        </div>
        <div class="topbar-right">
            <a href="lesson-list.php" class="btn-lms-outline">
                <i class="bi bi-arrow-left"></i> <span>Back to List</span>
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
                    <?= $errors[0] ?>
                <?php else: ?>
                    <strong>Please fix the following:</strong>
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
                — <a href="lesson-list.php" style="color:inherit;font-weight:600;">View All Lessons</a>
                &nbsp;|&nbsp; <a href="add-lesson.php" style="color:inherit;font-weight:600;">Add Another</a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" action="add-lesson.php"
              enctype="multipart/form-data"
              data-loading id="lessonForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-3">

                <!-- ── LEFT: Lesson Details ── -->
                <div class="col-lg-8">
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title">
                                <i class="bi bi-play-btn-fill"></i> Lesson Details
                            </h5>
                        </div>
                        <div class="lms-card-body">

                            <p class="form-section-title">Basic Information</p>

                            <!-- Course dropdown -->
                            <div class="mb-3">
                                <label class="lms-label">
                                    Course <span class="req">*</span>
                                </label>
                                <?php if (empty($allCourses)): ?>
                                    <div class="lms-alert lms-alert-warning" style="margin-bottom:0;">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        No courses found. <a href="add-course.php" style="color:inherit;font-weight:700;">Create a course first</a>.
                                    </div>
                                <?php else: ?>
                                    <select name="course_id" id="courseSelect" class="lms-select" required>
                                        <option value="">— Select a course —</option>
                                        <?php foreach ($allCourses as $c): ?>
                                        <option value="<?= $c['id'] ?>"
                                            <?= ((int)($old['course_id'] ?? $preselectedCourse) === (int)$c['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['title']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-hint">
                                        Changing the course also updates the suggested lesson order number.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Title -->
                            <div class="mb-3">
                                <label class="lms-label">Lesson Title <span class="req">*</span></label>
                                <input type="text" name="title" class="lms-input"
                                       placeholder="e.g. Introduction to Variables"
                                       value="<?= htmlspecialchars($old['title'] ?? '') ?>"
                                       maxlength="200" required>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="lms-label">Description</label>
                                <textarea name="description" class="lms-textarea"
                                          placeholder="What will students learn in this lesson?"
                                ><?= htmlspecialchars($old['description'] ?? '') ?></textarea>
                            </div>

                            <p class="form-section-title">Video Content</p>

                            <!-- YouTube URL -->
                            <div class="mb-3">
                                <label class="lms-label">
                                    <i class="bi bi-youtube" style="color:#ff0000;"></i>
                                    YouTube Video URL
                                </label>
                                <input type="url" name="video_url" id="videoUrlInput"
                                       class="lms-input"
                                       placeholder="https://www.youtube.com/watch?v=xxxxxxxxxxx"
                                       value="<?= htmlspecialchars($old['video_url'] ?? '') ?>">
                                <div class="field-hint">
                                    Paste the full YouTube watch URL. Leave blank if no video for this lesson.
                                </div>
                                <!-- Live preview -->
                                <div class="yt-preview-wrap" id="ytPreviewWrap">
                                    <iframe id="ytFrame" src="" allowfullscreen
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                                    </iframe>
                                </div>
                            </div>

                            <p class="form-section-title">PDF Notes</p>

                            <!-- PDF Upload -->
                            <div>
                                <label class="lms-label">
                                    <i class="bi bi-file-earmark-pdf-fill" style="color:#f97316;"></i>
                                    Upload PDF Notes
                                </label>
                                <div class="pdf-upload-zone" id="pdfUploadZone">
                                    <input type="file" name="pdf_notes" id="pdfInput"
                                           accept=".pdf,application/pdf">
                                    <div class="pdf-icon"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                                    <div class="upload-title">Drag & drop or click to upload PDF</div>
                                    <div class="upload-hint">PDF only &mdash; max 10 MB</div>
                                </div>
                                <!-- Selected file feedback -->
                                <div class="pdf-selected" id="pdfSelected">
                                    <i class="bi bi-file-earmark-pdf-fill"></i>
                                    <span class="pdf-name" id="pdfName">—</span>
                                    <span class="pdf-size" id="pdfSize"></span>
                                    <button type="button" id="pdfClear"
                                            style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;font-size:.95rem;"
                                            title="Remove">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ── RIGHT: Settings ── -->
                <div class="col-lg-4">

                    <!-- Order & Preview -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-sort-numeric-down"></i> Display Options</h5>
                        </div>
                        <div class="lms-card-body">
                            <div class="mb-3">
                                <label class="lms-label">Sort Order</label>
                                <input type="number" name="sort_order" id="sortOrderInput"
                                       class="lms-input" min="0" placeholder="1"
                                       value="<?= (int)($old['sort_order'] ?? 1) ?>">
                                <div class="field-hint">
                                    Lower numbers appear first. Auto-suggested based on course.
                                </div>
                            </div>
                            <div class="mb-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           name="is_preview" id="isPreview"
                                           <?= !empty($old['is_preview']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="isPreview">
                                        <strong>Free Preview</strong>
                                    </label>
                                </div>
                                <div class="field-hint mt-1">
                                    Allow non-enrolled students to view this lesson for free.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-toggle-on"></i> Publish Status</h5>
                        </div>
                        <div class="lms-card-body">
                            <?php foreach (['draft' => 'Draft', 'published' => 'Published'] as $val => $label): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio"
                                       name="status" id="status_<?= $val ?>" value="<?= $val ?>"
                                       <?= (($old['status'] ?? 'published') === $val) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="status_<?= $val ?>">
                                    <?= $label ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            <div class="field-hint mt-1">Only <strong>Published</strong> lessons are visible to students.</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <button type="submit" class="btn-lms-primary w-100"
                            style="justify-content:center;padding:.7rem;"
                            <?= empty($allCourses) ? 'disabled' : '' ?>>
                        <i class="bi bi-check-circle-fill"></i> Save Lesson
                    </button>
                    <a href="lesson-list.php" class="btn-lms-outline w-100 mt-2"
                       style="justify-content:center;">Cancel</a>

                </div>
            </div>
        </form>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
<script>
// ── YouTube live preview ────────────────────────────────────────
const videoInput   = document.getElementById('videoUrlInput');
const ytWrap       = document.getElementById('ytPreviewWrap');
const ytFrame      = document.getElementById('ytFrame');

function extractYtId(url) {
    const m = url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/);
    return m ? m[1] : null;
}

function updateYtPreview() {
    const id = extractYtId(videoInput.value.trim());
    if (id) {
        ytFrame.src = 'https://www.youtube.com/embed/' + id;
        ytWrap.style.display = 'block';
    } else {
        ytFrame.src = '';
        ytWrap.style.display = 'none';
    }
}

videoInput?.addEventListener('input', updateYtPreview);

// Show preview on page load if value pre-filled (validation failure)
if (videoInput?.value) updateYtPreview();

// ── PDF input feedback ──────────────────────────────────────────
const pdfInput    = document.getElementById('pdfInput');
const pdfZone     = document.getElementById('pdfUploadZone');
const pdfSelected = document.getElementById('pdfSelected');
const pdfName     = document.getElementById('pdfName');
const pdfSize     = document.getElementById('pdfSize');
const pdfClear    = document.getElementById('pdfClear');

function formatBytes(b) {
    return b > 1048576 ? (b/1048576).toFixed(1)+' MB' : (b/1024).toFixed(0)+' KB';
}

function showPdfInfo(file) {
    pdfName.textContent     = file.name;
    pdfSize.textContent     = formatBytes(file.size);
    pdfSelected.style.display = 'flex';
    pdfZone.style.display   = 'none';
}

pdfInput?.addEventListener('change', () => {
    if (pdfInput.files[0]) showPdfInfo(pdfInput.files[0]);
});

pdfZone?.addEventListener('dragover',  e => { e.preventDefault(); pdfZone.classList.add('dragging'); });
pdfZone?.addEventListener('dragleave', ()  => pdfZone.classList.remove('dragging'));
pdfZone?.addEventListener('drop', e => {
    e.preventDefault(); pdfZone.classList.remove('dragging');
    if (e.dataTransfer.files.length) {
        pdfInput.files = e.dataTransfer.files;
        if (pdfInput.files[0]) showPdfInfo(pdfInput.files[0]);
    }
});

pdfClear?.addEventListener('click', () => {
    pdfInput.value          = '';
    pdfSelected.style.display = 'none';
    pdfZone.style.display   = 'block';
});

// ── Auto-suggest sort order when course changes ─────────────────
const courseSelect   = document.getElementById('courseSelect');
const sortOrderInput = document.getElementById('sortOrderInput');

const sortHints = <?php
    // Build a JS map: courseId → next sort order
    $hints = [];
    foreach ($allCourses as $c) {
        $hints[$c['id']] = nextSortOrder($conn, (int)$c['id']);
    }
    echo json_encode($hints);
?>;

courseSelect?.addEventListener('change', () => {
    const id  = parseInt(courseSelect.value);
    const nxt = sortHints[id];
    if (nxt !== undefined) sortOrderInput.value = nxt;
});

// Fire on load for pre-selected
if (courseSelect?.value) courseSelect.dispatchEvent(new Event('change'));
</script>
</body>
</html>