<?php
/**
 * download-certificate.php — Download Certificate as PDF
 * Placement: lms-project/download-certificate.php
 * Action   : CREATE
 *
 * Approach: Renders a print-optimised HTML page.
 * Browser's built-in "Save as PDF" / print to PDF is triggered
 * automatically via JavaScript window.print() — works on all browsers,
 * zero server dependencies (no mPDF, no FPDF, no Composer needed).
 *
 * The page is rendered with @media print CSS so it outputs
 * exactly the certificate, then auto-triggers print dialog.
 * User selects "Save as PDF" in the dialog.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';
require_once __DIR__ . '/includes/site-info.php';

$certId = (int)($_GET['id'] ?? 0);
if ($certId <= 0) {
    header('Location: my-certificate.php');
    exit();
}

// Fetch certificate — owner only
$stmt = $conn->prepare("
    SELECT cert.id, cert.certificate_no, cert.issued_at,
           u.name  AS student_name,
           c.title AS course_title,
           c.level, c.duration, c.category
    FROM certificates cert
    INNER JOIN users   u ON u.id = cert.student_id
    INNER JOIN courses c ON c.id = cert.course_id
    WHERE cert.id = ? AND cert.student_id = ?
    LIMIT 1
");
if ($stmt === false) {
    die('Query error: ' . $conn->error);
}
$stmt->bind_param('ii', $certId, $authUserId);
$stmt->execute();
$cert = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cert) {
    header('Location: my-certificate.php');
    exit();
}

$issuedDate = date('F j, Y', strtotime($cert['issued_at']));
$safeTitle  = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cert['course_title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate — <?= htmlspecialchars($cert['course_title']) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold:  #b8860b;
            --blue:  #1e40af;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
        }

        /* Save hint bar — hidden on print */
        .save-hint {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: .75rem 1.25rem;
            font-size: .85rem;
            color: #1e40af;
            margin-bottom: 1.25rem;
            width: 100%;
            max-width: 900px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .save-hint i { font-size: 1.1rem; flex-shrink: 0; }
        .btn-print-now {
            background: #1e40af; color: #fff; border: none;
            border-radius: 6px; padding: .4rem 1rem;
            font-size: .82rem; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: .35rem;
        }
        @media print { .save-hint { display: none !important; } }

        /* ── Certificate ── */
        .certificate-wrap {
            width: 100%; max-width: 900px;
        }

        .cert-border {
            border: 3px solid var(--gold);
            padding: 36px 48px 40px;
            position: relative;
        }
        .cert-border::before { content: '✦'; position: absolute; top: -13px; left: -13px; font-size: 1.1rem; color: var(--gold); background: #fff; padding: 0 4px; }
        .cert-border::after  { content: '✦'; position: absolute; bottom: -13px; right: -13px; font-size: 1.1rem; color: var(--gold); background: #fff; padding: 0 4px; }

        .cert-header-strip {
            background: linear-gradient(135deg, #0f172a, #1e40af, #2563eb);
            margin: -36px -48px 28px;
            padding: 22px 48px 18px;
            text-align: center;
        }
        .cert-site-name {
            font-family: 'Sora', sans-serif;
            font-size: .85rem; font-weight: 700;
            color: rgba(255,255,255,.7);
            letter-spacing: .15em; text-transform: uppercase;
            margin-bottom: .2rem;
        }
        .cert-logo {
            width: 44px; height: 44px;
            border-radius: 9px;
            object-fit: cover;
            margin: 0 auto .5rem;
            display: block;
            border: 2px solid rgba(255,255,255,.4);
        }
        .cert-contact-row {
            display: flex;
            justify-content: center;
            gap: 1.25rem;
            font-size: .7rem;
            color: rgba(255,255,255,.7);
            margin-top: .4rem;
        }
        .cert-main-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem; font-weight: 700;
            color: #fff; letter-spacing: .06em; text-transform: uppercase;
        }
        .cert-sub { font-size: .8rem; color: rgba(255,255,255,.6); margin-top: .3rem; }

        .gold-line { height: 2px; background: linear-gradient(90deg,transparent,var(--gold),transparent); margin: 18px 0; }

        .cert-body { text-align: center; }
        .cert-trophy { font-size: 2.5rem; color: var(--gold); margin-bottom: .6rem; display: block; }
        .cert-presented { font-size: .78rem; color: #64748b; letter-spacing: .1em; text-transform: uppercase; margin-bottom: .5rem; }
        .cert-student-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.2rem; font-weight: 700; font-style: italic;
            color: #0f172a;
            border-bottom: 2px solid var(--gold);
            display: inline-block;
            padding-bottom: .2rem;
            margin-bottom: 1rem;
        }
        .cert-text { font-size: .88rem; color: #475569; margin-bottom: 1rem; }
        .cert-course {
            font-family: 'Sora', sans-serif;
            font-size: 1.2rem; font-weight: 700;
            color: #1e40af;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            padding: .5rem 1.25rem;
            display: inline-block;
            margin-bottom: 1.1rem;
        }
        .cert-meta-line { font-size: .78rem; color: #94a3b8; margin-bottom: 1.1rem; }

        .cert-footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 1.25rem; gap: .5rem; }
        .cert-footer-col { text-align: center; flex: 1; }
        .cert-footer-label { font-size: .65rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .08em; margin-bottom: .25rem; }
        .cert-footer-value { font-family: 'Sora', sans-serif; font-size: .82rem; font-weight: 700; color: #1e293b; border-top: 1.5px solid #e2e8f0; padding-top: .4rem; }
        .cert-no-badge {
            font-family: 'Courier New', monospace;
            font-size: .7rem; color: #64748b;
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 3px; padding: .2rem .5rem;
        }
        .cert-signature-img {
            max-height: 36px;
            max-width: 110px;
            object-fit: contain;
            margin: 0 auto;
            display: block;
        }
        .cert-signature-blank {
            height: 36px;
            border-bottom: 1.5px solid #cbd5e1;
            max-width: 110px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<!-- Save hint -->
<div class="save-hint">
    <span>
        <i class="bi bi-info-circle-fill"></i>
        Click <strong>Print</strong> → select <strong>Save as PDF</strong> as the printer destination.
    </span>
    <button class="btn-print-now" onclick="window.print()">
        🖨️ Print / Save as PDF
    </button>
</div>

<!-- Certificate -->
<div class="certificate-wrap">
    <div class="cert-border">

        <div class="cert-header-strip">
            <img src="<?= htmlspecialchars(site_logo_url()) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" class="cert-logo">
            <div class="cert-site-name"><?= htmlspecialchars(SITE_NAME) ?></div>
            <div class="cert-main-title">Certificate of Completion</div>
            <div class="cert-sub">This certifies the successful completion of the following course</div>
            <div class="cert-contact-row">
                <span>📞 <?= htmlspecialchars(SITE_PHONE) ?></span>
                <span>✉️ <?= htmlspecialchars(SITE_EMAIL) ?></span>
            </div>
        </div>

        <div class="gold-line"></div>

        <div class="cert-body">
            <span class="cert-trophy">🏆</span>
            <p class="cert-presented">This certificate is proudly presented to</p>
            <div class="cert-student-name"><?= htmlspecialchars($cert['student_name']) ?></div>
            <p class="cert-text">has successfully completed all lessons and passed the required assessment for the course</p>
            <div class="cert-course"><?= htmlspecialchars($cert['course_title']) ?></div>
            <div class="cert-meta-line">
                <?php if ($cert['category']): ?>
                <?= htmlspecialchars($cert['category']) ?>
                <?php endif; ?>
                <?php if ($cert['level']): ?>
                &nbsp;·&nbsp; <?= ucfirst($cert['level']) ?> Level
                <?php endif; ?>
                <?php if ($cert['duration']): ?>
                &nbsp;·&nbsp; <?= htmlspecialchars($cert['duration']) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="gold-line"></div>

        <div class="cert-footer">
            <div class="cert-footer-col">
                <div class="cert-footer-label">Issue Date</div>
                <div class="cert-footer-value"><?= $issuedDate ?></div>
            </div>
            <div class="cert-footer-col" style="flex:1.5;">
                <div class="cert-footer-label">Certificate Number</div>
                <div class="cert-footer-value">
                    <span class="cert-no-badge"><?= htmlspecialchars($cert['certificate_no']) ?></span>
                </div>
            </div>
            <div class="cert-footer-col">
                <?php if (signature_file_exists(__DIR__)): ?>
                    <img src="<?= htmlspecialchars(signature_url()) ?>" alt="Signature" class="cert-signature-img">
                <?php else: ?>
                    <div class="cert-signature-blank"></div>
                <?php endif; ?>
                <div class="cert-footer-label" style="margin-top:.3rem;">Authorized By</div>
                <div class="cert-footer-value" style="border-top:none;padding-top:0;">
                    <?= htmlspecialchars(SIGNATORY_NAME) ?>
                    <div style="font-size:.62rem;font-weight:500;color:#94a3b8;margin-top:.1rem;">
                        <?= htmlspecialchars(SIGNATORY_DESIGNATION) ?>, <?= htmlspecialchars(SITE_NAME) ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Auto-trigger print dialog after fonts load
window.addEventListener('load', () => {
    setTimeout(() => window.print(), 800);
});
</script>
</body>
</html>