<?php
/**
 * view-certificate.php — View / Print Certificate
 * Placement: lms-project/view-certificate.php
 * Action   : CREATE
 *
 * Renders a styled printable certificate.
 * Only the certificate owner can view it.
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/student-auth.php';
require_once __DIR__ . '/includes/site-info.php';

$certId = (int)($_GET['id'] ?? 0);
if ($certId <= 0) {
    header('Location: my-certificate.php');
    exit();
}

// Fetch certificate — must belong to this student
$stmt = $conn->prepare("
    SELECT cert.id, cert.certificate_no, cert.issued_at,
           cert.student_id, cert.course_id,
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate — <?= htmlspecialchars($cert['course_title']) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Inter:wght@400;500&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --gold:    #b8860b;
            --gold-lt: #fef9e7;
            --blue:    #1e40af;
            --blue-lt: #eff6ff;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem 1rem;
        }

        /* ── Top action bar (hidden on print) ── */
        .action-bar {
            display: flex; gap: .75rem; align-items: center;
            width: 100%; max-width: 900px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .btn-back {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem 1rem; border-radius: 8px;
            font-size: .85rem; font-weight: 600; text-decoration: none;
            border: 1.5px solid #e2e8f0; color: #64748b; background: #fff;
            transition: background .15s;
        }
        .btn-back:hover { background: #f8fafc; color: #1e293b; }
        .btn-print {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem 1.1rem; border-radius: 8px;
            font-size: .85rem; font-weight: 600;
            background: linear-gradient(135deg, #1e40af, #2563eb);
            color: #fff; border: none; cursor: pointer;
            transition: opacity .2s;
            box-shadow: 0 2px 8px rgba(37,99,235,.25);
        }
        .btn-print:hover { opacity: .9; }
        .btn-download {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .5rem 1.1rem; border-radius: 8px;
            font-size: .85rem; font-weight: 600;
            background: #fff; color: #1e40af;
            border: 1.5px solid #2563eb; text-decoration: none;
            transition: background .15s;
        }
        .btn-download:hover { background: #eff6ff; color: #1e40af; }

        /* ── Certificate card ── */
        .certificate-wrap {
            width: 100%; max-width: 900px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            box-shadow: 0 8px 40px rgba(0,0,0,.12);
            overflow: hidden;
        }

        .certificate {
            padding: 0;
            position: relative;
        }

        /* Outer decorative border */
        .cert-border {
            margin: 28px;
            border: 3px solid var(--gold);
            padding: 36px 48px 40px;
            position: relative;
        }
        /* Corner ornaments */
        .cert-border::before, .cert-border::after {
            content: '✦';
            position: absolute;
            font-size: 1.2rem;
            color: var(--gold);
        }
        .cert-border::before { top: -14px; left: -14px; }
        .cert-border::after  { bottom: -14px; right: -14px; }

        /* Header strip */
        .cert-header-strip {
            background: linear-gradient(135deg, #0f172a, #1e40af, #2563eb);
            margin: -36px -48px 32px;
            padding: 24px 48px 20px;
            text-align: center;
            position: relative;
        }
        .cert-site-name {
            font-family: 'Sora', sans-serif;
            font-size: 1rem; font-weight: 700;
            color: rgba(255,255,255,.7);
            letter-spacing: .15em;
            text-transform: uppercase;
            margin-bottom: .25rem;
        }
        .cert-logo {
            width: 52px; height: 52px;
            border-radius: 10px;
            object-fit: cover;
            margin: 0 auto .6rem;
            display: block;
            border: 2px solid rgba(255,255,255,.4);
        }
        .cert-contact-row {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            font-size: .76rem;
            color: rgba(255,255,255,.7);
            margin-top: .5rem;
            flex-wrap: wrap;
        }
        .cert-contact-row i { margin-right: .3rem; }
        .cert-header-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem; font-weight: 700;
            color: #fff;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .cert-header-sub {
            font-size: .85rem; color: rgba(255,255,255,.65);
            margin-top: .35rem;
        }
        /* Gold line under header */
        .gold-line {
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 20px 0;
        }

        /* Body */
        .cert-body { text-align: center; }
        .cert-presented {
            font-size: .85rem; color: #64748b;
            letter-spacing: .1em; text-transform: uppercase;
            margin-bottom: .6rem;
        }
        .cert-student-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.6rem; font-weight: 700; font-style: italic;
            color: #0f172a;
            border-bottom: 2px solid var(--gold);
            display: inline-block;
            padding-bottom: .25rem;
            margin-bottom: 1.2rem;
        }
        .cert-body-text {
            font-size: .95rem; color: #475569; line-height: 1.7;
            max-width: 540px; margin: 0 auto 1.4rem;
        }
        .cert-course-name {
            font-family: 'Sora', sans-serif;
            font-size: 1.35rem; font-weight: 700;
            color: #1e40af;
            background: var(--blue-lt);
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: .6rem 1.5rem;
            display: inline-block;
            margin-bottom: 1.5rem;
        }

        /* Footer row */
        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 1.5rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .cert-footer-col { text-align: center; flex: 1; min-width: 140px; }
        .cert-footer-label {
            font-size: .72rem; color: #94a3b8;
            text-transform: uppercase; letter-spacing: .08em;
            margin-bottom: .25rem;
        }
        .cert-footer-value {
            font-family: 'Sora', sans-serif;
            font-size: .9rem; font-weight: 700;
            color: #1e293b;
            border-top: 1.5px solid #e2e8f0;
            padding-top: .5rem;
        }
        .cert-footer-no {
            font-family: 'Courier New', monospace;
            font-size: .78rem; color: #64748b;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: .3rem .65rem;
        }
        .cert-signature-img {
            max-height: 44px;
            max-width: 140px;
            object-fit: contain;
            margin: 0 auto;
            display: block;
        }
        .cert-signature-blank {
            height: 44px;
            border-bottom: 1.5px solid #cbd5e1;
            max-width: 140px;
            margin: 0 auto;
        }

        /* Trophy icon */
        .cert-trophy {
            font-size: 2.8rem;
            color: var(--gold);
            margin-bottom: .75rem;
            display: block;
        }

        /* ── Print styles ── */
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .action-bar { display: none !important; }
            .certificate-wrap {
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
            .cert-border {
                margin: 18px;
                padding: 28px 36px 32px;
            }
        }

        @media (max-width: 600px) {
            .cert-border { margin: 12px; padding: 20px; }
            .cert-header-strip { padding: 16px 20px 14px; margin: -20px -20px 20px; }
            .cert-student-name { font-size: 1.8rem; }
            .cert-course-name { font-size: 1rem; padding: .5rem 1rem; }
            .cert-header-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Action bar -->
<div class="action-bar no-print">
    <a href="my-certificate.php" class="btn-back">
        <i class="bi bi-arrow-left"></i> Back
    </a>
    <button class="btn-print" onclick="window.print()">
        <i class="bi bi-printer-fill"></i> Print
    </button>
    <a href="download-certificate.php?id=<?= $certId ?>" class="btn-download">
        <i class="bi bi-download"></i> Download PDF
    </a>
</div>

<!-- Certificate -->
<div class="certificate-wrap">
    <div class="certificate">
        <div class="cert-border">

            <!-- Header -->
            <div class="cert-header-strip">
                <img src="<?= htmlspecialchars(site_logo_url()) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" class="cert-logo">
                <div class="cert-site-name"><?= htmlspecialchars(SITE_NAME) ?></div>
                <div class="cert-header-title">Certificate of Completion</div>
                <div class="cert-header-sub">This is to certify the successful completion of the course</div>
                <div class="cert-contact-row">
                    <span><i class="bi bi-telephone-fill"></i><?= htmlspecialchars(SITE_PHONE) ?></span>
                    <span><i class="bi bi-envelope-fill"></i><?= htmlspecialchars(SITE_EMAIL) ?></span>
                </div>
            </div>

            <div class="gold-line"></div>

            <!-- Body -->
            <div class="cert-body">
                <i class="bi bi-award-fill cert-trophy"></i>
                <p class="cert-presented">This certificate is proudly presented to</p>
                <div class="cert-student-name">
                    <?= htmlspecialchars($cert['student_name']) ?>
                </div>
                <p class="cert-body-text">
                    has successfully completed all lessons and passed the required assessment for the course
                </p>
                <div class="cert-course-name">
                    <?= htmlspecialchars($cert['course_title']) ?>
                </div>

                <?php if ($cert['category'] || $cert['level']): ?>
                <p style="font-size:.82rem;color:#64748b;margin-bottom:1.25rem;">
                    <?php if ($cert['category']): ?>
                    <strong><?= htmlspecialchars($cert['category']) ?></strong>
                    <?php endif; ?>
                    <?php if ($cert['category'] && $cert['level']): ?>&nbsp;·&nbsp;<?php endif; ?>
                    <?php if ($cert['level']): ?>
                    <?= ucfirst($cert['level']) ?> Level
                    <?php endif; ?>
                    <?php if ($cert['duration']): ?>
                    &nbsp;·&nbsp; <?= htmlspecialchars($cert['duration']) ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>

            <div class="gold-line"></div>

            <!-- Footer -->
            <div class="cert-footer">
                <div class="cert-footer-col">
                    <div class="cert-footer-label">Issue Date</div>
                    <div class="cert-footer-value"><?= $issuedDate ?></div>
                </div>
                <div class="cert-footer-col" style="flex:1.5;">
                    <div class="cert-footer-label">Certificate Number</div>
                    <div class="cert-footer-value">
                        <span class="cert-footer-no"><?= htmlspecialchars($cert['certificate_no']) ?></span>
                    </div>
                </div>
                <div class="cert-footer-col">
                    <?php if (signature_file_exists(__DIR__)): ?>
                        <img src="<?= htmlspecialchars(signature_url()) ?>" alt="Signature" class="cert-signature-img">
                    <?php else: ?>
                        <div class="cert-signature-blank"></div>
                    <?php endif; ?>
                    <div class="cert-footer-label" style="margin-top:.35rem;">Authorized By</div>
                    <div class="cert-footer-value" style="border-top:none;padding-top:0;">
                        <?= htmlspecialchars(SIGNATORY_NAME) ?>
                        <div style="font-size:.7rem;font-weight:500;color:#94a3b8;margin-top:.1rem;">
                            <?= htmlspecialchars(SIGNATORY_DESIGNATION) ?>, <?= htmlspecialchars(SITE_NAME) ?>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.cert-border -->
    </div><!-- /.certificate -->
</div><!-- /.certificate-wrap -->

</body>
</html>