<?php
/**
 * admin/edit-lesson.php — Edit Existing Lesson
 * ──────────────────────────────────────────────
 * Placement: lms-project/admin/edit-lesson.php
 */

define('BASE_URL', '../');
$currentPage = 'lessons';

require_once '../config/db.php';
require_once 'includes/auth.php';

// ── Validate ID ────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: lesson-list.php');
    exit();
}

// ── Fetch lesson ───────────────────────────────────────────────
$fetchStmt = $conn->prepare("SELECT * FROM lessons WHERE id = ? LIMIT 1");
$fetchStmt->bind_param('i', $id);
$fetchStmt->execute();
$lesson = $fetchStmt->get_result()->fetch_assoc();
$fetchStmt->close();

if (!$lesson) {
    $_SESSION['flash_error'] = 'Lesson not found.';
    header('Location: lesson-list.php');
    exit();
}

// ── Upload config ──────────────────────────────────────────────
define('PDF_UPLOAD_DIR', '../uploads/lessons/');
define('PDF_UPLOAD_URL', 'uploads/lessons/');
define('PDF_MAX_SIZE',   10 * 1024 * 1024);

// ── Load all courses for dropdown ──────────────────────────────
$courseRes  = $conn->query("SELECT id, title FROM courses ORDER BY title ASC");
$allCourses = [];
while ($row = $courseRes->fetch_assoc()) $allCourses[] = $row;

$errors  = [];
$success = '';

// Helper: extract YouTube video ID
function extractYtId(string $url): ?string {
    if (preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/', $url, $m)) return $m[1];
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {

        $course_id   = (int)($_POST['course_id']   ?? 0);
        $title       = trim(htmlspecialchars($_POST['title']       ?? ''));
        $description = trim(htmlspecialchars($_POST['description'] ?? ''));
        $video_url   = trim($_POST['video_url'] ?? '');
        $sort_order  = max(0, (int)($_POST['sort_order'] ?? 0));
        $is_preview  = isset($_POST['is_preview']) ? 1 : 0;
        $status      = $_POST['status'] ?? 'draft';

        // Keep current PDF unless replaced or removed
        $pdfPath = $lesson['pdf_notes'];

        // Validate
        if ($course_id <= 0)       $errors[] = 'Please select a course.';
        if (empty($title))         $errors[] = 'Lesson title is required.';
        if (strlen($title) > 200)  $errors[] = 'Title must be 200 characters or fewer.';
        if (!in_array($status, ['published','draft'])) $errors[] = 'Invalid status.';

        if ($course_id > 0) {
            $chk = $conn->prepare("SELECT id FROM courses WHERE id = ? LIMIT 1");
            $chk->bind_param('i', $course_id);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) $errors[] = 'Selected course does not exist.';
            $chk->close();
        }

        if (!empty($video_url)) {
            if (!preg_match('/^https?:\/\/(www\.)?(youtube\.com\/watch\?v=|youtu\.be\/)[\w\-]{11}/i', $video_url)) {
                $errors[] = 'Please enter a valid YouTube video URL.';
            }
        }

        // Handle "remove PDF" checkbox
        if (isset($_POST['remove_pdf']) && $pdfPath) {
            $absPath = dirname(__DIR__) . '/' . $pdfPath;
            if (file_exists($absPath)) @unlink($absPath);
            $pdfPath = null;
        }

        // Handle new PDF upload (replaces existing)
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
            } elseif ($mimeType !== 'application/pdf' || $ext !== 'pdf') {
                $errors[] = 'Only PDF files are accepted.';
            } else {
                if (!is_dir(PDF_UPLOAD_DIR)) mkdir(PDF_UPLOAD_DIR, 0755, true);
                $newFilename = 'lesson_' . time() . '_' . bin2hex(random_bytes(5)) . '.pdf';
                $destination = PDF_UPLOAD_DIR . $newFilename;
                if (move_uploaded_file($tmpPath, $destination)) {
                    // Delete old PDF
                    if ($lesson['pdf_notes']) {
                        $oldAbs = dirname(__DIR__) . '/' . $lesson['pdf_notes'];
                        if (file_exists($oldAbs)) @unlink($oldAbs);
                    }
                    $pdfPath = PDF_UPLOAD_URL . $newFilename;
                } else {
                    $errors[] = 'Failed to save the uploaded PDF.';
                }
            }
        }

        // Update DB
        if (empty($errors)) {
            $stmt = $conn->prepare("
                UPDATE lessons SET
                    course_id = ?, title = ?, description = ?, video_url = ?,
                    pdf_notes = ?, sort_order = ?, is_preview = ?, status = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                'isssssiis',
                $course_id, $title, $description, $video_url,
                $pdfPath, $sort_order, $is_preview, $status, $id
            );

            if ($stmt->execute()) {
                // Refresh data
                $fetchStmt = $conn->prepare("SELECT * FROM lessons WHERE id = ? LIMIT 1");
                $fetchStmt->bind_param('i', $id);
                $fetchStmt->execute();
                $lesson  = $fetchStmt->get_result()->fetch_assoc();
                $fetchStmt->close();
                $success = 'Lesson updated successfully!';
            } else {
                $errors[] = 'Database error: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$existingYtId  = extractYtId($lesson['video_url'] ?? '');
$existingPdfOk = !empty($lesson['pdf_notes']) &&
                 file_exists(dirname(__DIR__) . '/' . $lesson['pdf_notes']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lesson — LMS Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .yt-preview-wrap {
            margin-top:.75rem; border-radius:var(--radius-sm); overflow:hidden;
            border:1px solid var(--border); aspect-ratio:16/9; background:#000;
        }
        .yt-preview-wrap iframe { width:100%; height:100%; border:none; display:block; }
        .pdf-upload-zone {
            border:2px dashed var(--border); border-radius:var(--radius);
            padding:1.5rem; text-align:center; background:#f8fafc; cursor:pointer;
            transition:border-color .2s, background .2s; position:relative;
        }
        .pdf-upload-zone:hover, .pdf-upload-zone.dragging { border-color:#f97316; background:#fff7ed; }
        .pdf-upload-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; }
        .pdf-selected {
            display:none; align-items:center; gap:.6rem;
            background:#fff7ed; border:1px solid #fed7aa;
            border-radius:var(--radius-sm); padding:.65rem .9rem;
            margin-top:.75rem; font-size:.85rem;
        }
        .pdf-selected i { color:#f97316; font-size:1.1rem; }
        .pdf-selected .pdf-name { font-weight:600; color:var(--text); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .existing-pdf {
            display:flex; align-items:center; gap:.65rem;
            background:#fff7ed; border:1px solid #fed7aa;
            border-radius:var(--radius-sm); padding:.7rem 1rem;
            margin-bottom:.75rem; font-size:.85rem;
        }
        .existing-pdf i { color:#f97316; font-size:1.15rem; flex-shrink:0; }
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
                <div class="page-title">Edit Lesson</div>
                <div class="page-breadcrumb">
                    <a href="index.php">Dashboard</a> &rsaquo;
                    <a href="lesson-list.php">Lessons</a> &rsaquo; Edit
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
                <?php if (count($errors)===1): ?><?= $errors[0] ?><?php else: ?>
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-1 ps-3"><?php foreach($errors as $e): ?><li><?=$e?></li><?php endforeach; ?></ul>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="lms-alert lms-alert-success" data-autohide>
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($success) ?>
            — <a href="lesson-list.php" style="color:inherit;font-weight:600;">View All Lessons</a>
        </div>
        <?php endif; ?>

        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:1rem;">
            Editing lesson ID: <strong>#<?= $lesson['id'] ?></strong>
        </div>

        <!-- Form -->
        <form method="POST" action="edit-lesson.php?id=<?= $id ?>"
              enctype="multipart/form-data" data-loading novalidate>
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row g-3">

                <!-- LEFT: Details -->
                <div class="col-lg-8">
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-play-btn-fill"></i> Lesson Details</h5>
                        </div>
                        <div class="lms-card-body">

                            <p class="form-section-title">Basic Information</p>

                            <!-- Course -->
                            <div class="mb-3">
                                <label class="lms-label">Course <span class="req">*</span></label>
                                <select name="course_id" class="lms-select" required>
                                    <option value="">— Select a course —</option>
                                    <?php foreach ($allCourses as $c): ?>
                                    <option value="<?= $c['id'] ?>"
                                        <?= (int)$lesson['course_id'] === (int)$c['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['title']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Title -->
                            <div class="mb-3">
                                <label class="lms-label">Lesson Title <span class="req">*</span></label>
                                <input type="text" name="title" class="lms-input"
                                       value="<?= htmlspecialchars($lesson['title']) ?>"
                                       maxlength="200" required>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="lms-label">Description</label>
                                <textarea name="description" class="lms-textarea"><?= htmlspecialchars($lesson['description'] ?? '') ?></textarea>
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
                                       value="<?= htmlspecialchars($lesson['video_url'] ?? '') ?>">
                                <div class="field-hint">Leave blank to remove the video from this lesson.</div>
                                <?php if ($existingYtId): ?>
                                <div class="yt-preview-wrap" id="ytPreviewWrap">
                                    <iframe id="ytFrame"
                                            src="https://www.youtube.com/embed/<?= htmlspecialchars($existingYtId) ?>"
                                            allowfullscreen>
                                    </iframe>
                                </div>
                                <?php else: ?>
                                <div class="yt-preview-wrap" id="ytPreviewWrap" style="display:none;">
                                    <iframe id="ytFrame" src="" allowfullscreen></iframe>
                                </div>
                                <?php endif; ?>
                            </div>

                            <p class="form-section-title">PDF Notes</p>

                            <!-- Existing PDF -->
                            <?php if ($existingPdfOk): ?>
                            <div class="existing-pdf">
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                <div style="flex:1;overflow:hidden;">
                                    <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        <?= htmlspecialchars(basename($lesson['pdf_notes'])) ?>
                                    </div>
                                    <div style="font-size:.72rem;color:var(--text-muted);">Current PDF notes file</div>
                                </div>
                                <a href="../<?= htmlspecialchars($lesson['pdf_notes']) ?>" target="_blank"
                                   class="btn-icon view" title="Open PDF">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox"
                                       name="remove_pdf" id="removePdf">
                                <label class="form-check-label" for="removePdf"
                                       style="font-size:.82rem;color:var(--danger);">
                                    Remove current PDF
                                </label>
                            </div>
                            <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:.5rem;">Upload a new PDF to replace:</p>
                            <?php endif; ?>

                            <!-- PDF Upload -->
                            <div class="pdf-upload-zone" id="pdfUploadZone">
                                <input type="file" name="pdf_notes" id="pdfInput" accept=".pdf,application/pdf">
                                <div style="font-size:1.8rem;color:#f97316;margin-bottom:.4rem;"><i class="bi bi-file-earmark-pdf-fill"></i></div>
                                <div class="upload-title">Drag & drop or click to upload PDF</div>
                                <div class="upload-hint">PDF only — max 10 MB</div>
                            </div>
                            <div class="pdf-selected" id="pdfSelected">
                                <i class="bi bi-file-earmark-pdf-fill"></i>
                                <span class="pdf-name" id="pdfName">—</span>
                                <span id="pdfSize" style="font-size:.78rem;color:var(--text-muted);white-space:nowrap;"></span>
                                <button type="button" id="pdfClear"
                                        style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;">
                                    <i class="bi bi-x-circle-fill"></i>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- RIGHT: Settings -->
                <div class="col-lg-4">

                    <!-- Display options -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-sort-numeric-down"></i> Display Options</h5>
                        </div>
                        <div class="lms-card-body">
                            <div class="mb-3">
                                <label class="lms-label">Sort Order</label>
                                <input type="number" name="sort_order" class="lms-input"
                                       min="0" value="<?= (int)$lesson['sort_order'] ?>">
                                <div class="field-hint">Lower numbers appear first within the course.</div>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox"
                                       name="is_preview" id="isPreview"
                                       <?= $lesson['is_preview'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isPreview"><strong>Free Preview</strong></label>
                            </div>
                            <div class="field-hint mt-1">Allow non-enrolled students to access this lesson.</div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-header">
                            <h5 class="lms-card-title"><i class="bi bi-toggle-on"></i> Publish Status</h5>
                        </div>
                        <div class="lms-card-body">
                            <?php foreach (['draft'=>'Draft','published'=>'Published'] as $val=>$label): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio"
                                       name="status" id="status_<?= $val ?>" value="<?= $val ?>"
                                       <?= $lesson['status']===$val ? 'checked' : '' ?>>
                                <label class="form-check-label" for="status_<?= $val ?>"><?= $label ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <div class="lms-card mb-3">
                        <div class="lms-card-body" style="padding:1rem 1.25rem;">
                            <div style="font-size:.75rem;color:var(--text-muted);display:flex;flex-direction:column;gap:.35rem;">
                                <div><strong>Created:</strong> <?= date('d M Y, h:i A', strtotime($lesson['created_at'])) ?></div>
                                <div><strong>Updated:</strong> <?= date('d M Y, h:i A', strtotime($lesson['updated_at'])) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <button type="submit" class="btn-lms-primary w-100" style="justify-content:center;padding:.7rem;">
                        <i class="bi bi-check-circle-fill"></i> Update Lesson
                    </button>
                    <a href="delete-lesson.php?id=<?= $id ?>&csrf=<?= $_SESSION['csrf_token'] ?>"
                       class="btn-lms-outline w-100 mt-2"
                       style="justify-content:center;border-color:#fca5a5;color:var(--danger);"
                       data-confirm="Permanently delete this lesson? This cannot be undone.">
                        <i class="bi bi-trash-fill"></i> Delete Lesson
                    </a>

                </div>
            </div>
        </form>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/admin.js"></script>
<script>
// YouTube live preview
const videoInput = document.getElementById('videoUrlInput');
const ytWrap     = document.getElementById('ytPreviewWrap');
const ytFrame    = document.getElementById('ytFrame');

function extractYtId(url) {
    const m = url.match(/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/);
    return m ? m[1] : null;
}
videoInput?.addEventListener('input', () => {
    const id = extractYtId(videoInput.value.trim());
    if (id) { ytFrame.src = 'https://www.youtube.com/embed/'+id; ytWrap.style.display='block'; }
    else    { ytFrame.src = ''; ytWrap.style.display='none'; }
});

// PDF feedback
const pdfInput    = document.getElementById('pdfInput');
const pdfZone     = document.getElementById('pdfUploadZone');
const pdfSelected = document.getElementById('pdfSelected');
const pdfName     = document.getElementById('pdfName');
const pdfSizeEl   = document.getElementById('pdfSize');
const pdfClear    = document.getElementById('pdfClear');

function formatBytes(b){ return b>1048576?(b/1048576).toFixed(1)+' MB':(b/1024).toFixed(0)+' KB'; }
function showPdfInfo(file){ pdfName.textContent=file.name; pdfSizeEl.textContent=formatBytes(file.size); pdfSelected.style.display='flex'; pdfZone.style.display='none'; }

pdfInput?.addEventListener('change', ()=>{ if(pdfInput.files[0]) showPdfInfo(pdfInput.files[0]); });
pdfZone?.addEventListener('dragover', e=>{e.preventDefault();pdfZone.classList.add('dragging');});
pdfZone?.addEventListener('dragleave', ()=>pdfZone.classList.remove('dragging'));
pdfZone?.addEventListener('drop', e=>{
    e.preventDefault(); pdfZone.classList.remove('dragging');
    if(e.dataTransfer.files.length){ pdfInput.files=e.dataTransfer.files; if(pdfInput.files[0]) showPdfInfo(pdfInput.files[0]); }
});
pdfClear?.addEventListener('click', ()=>{ pdfInput.value=''; pdfSelected.style.display='none'; pdfZone.style.display='block'; });
</script>
</body>
</html>