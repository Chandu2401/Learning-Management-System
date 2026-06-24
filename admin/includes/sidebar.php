<?php
/**
 * admin/includes/sidebar.php
 * ─────────────────────────────────────────────────────────────
 * Reusable sidebar for all admin pages.
 * Requires $currentPage variable to be set before including.
 *
 * v2 — added Lessons nav group, fixed broken logout <a> tag.
 */

$adminName  = htmlspecialchars($_SESSION['user_name']  ?? 'Admin');
$adminEmail = htmlspecialchars($_SESSION['user_email'] ?? '');

// Ensure branding constants are available even if the including
// page forgot to require this itself — site-info.php is safe to
// require multiple times (require_once) and needs $conn from db.php,
// which every admin page already loads before this sidebar.
require_once __DIR__ . '/../../includes/site-info.php';

$nav = [
    'dashboard'  => ['icon' => 'bi-grid-1x2-fill',        'label' => 'Dashboard',  'href' => 'index.php',       'group' => 'Main Menu'],
    'courses'    => ['icon' => 'bi-book-fill',              'label' => 'Courses',    'href' => 'course-list.php', 'group' => 'Main Menu'],
    'add_course' => ['icon' => 'bi-journal-plus',           'label' => 'Add Course', 'href' => 'add-course.php',  'group' => 'Main Menu'],
    'lessons'    => ['icon' => 'bi-play-btn-fill',          'label' => 'Lessons',    'href' => 'lesson-list.php', 'group' => 'Lessons'],
    'add_lesson' => ['icon' => 'bi-file-earmark-plus-fill', 'label' => 'Add Lesson', 'href' => 'add-lesson.php',  'group' => 'Lessons'],
    'quizzes'       => ['icon' => 'bi-patch-question-fill', 'label' => 'Quizzes',      'href' => 'quiz-list.php',    'group' => 'Quizzes'],
    'add_quiz'      => ['icon' => 'bi-plus-square-fill',     'label' => 'Add Quiz',     'href' => 'add-quiz.php',     'group' => 'Quizzes'],
    'quiz_attempts' => ['icon' => 'bi-clipboard-data-fill',  'label' => 'Attempts',     'href' => 'quiz-attempts.php','group' => 'Quizzes'],
    'certificates'  => ['icon' => 'bi-award-fill',           'label' => 'Certificates', 'href' => 'certificates.php', 'group' => 'Quizzes'],
    'users'      => ['icon' => 'bi-people-fill',            'label' => 'Users',      'href' => 'users.php',       'group' => 'More'],
    'reports'    => ['icon' => 'bi-bar-chart-line-fill',    'label' => 'Reports',    'href' => 'reports.php',     'group' => 'More'],
    'settings'   => ['icon' => 'bi-gear-fill',              'label' => 'Settings',   'href' => 'settings.php',    'group' => 'More'],
];

$navGroups = [];
foreach ($nav as $key => $item) {
    $navGroups[$item['group']][$key] = $item;
}
?>
<aside class="lms-sidebar" id="lmsSidebar">

    <div class="sidebar-brand">
        <div class="brand-icon">
            <img src="<?= htmlspecialchars(site_logo_url('../')) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:inherit;">
        </div>
        <div class="brand-text">
            <span class="brand-name" style="font-size:.82rem;line-height:1.25;display:block;"><?= htmlspecialchars(SITE_NAME) ?></span>
            <span class="brand-sub">Admin Panel</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
        <p class="nav-label <?= $groupLabel !== 'Main Menu' ? 'mt-3' : '' ?>"><?= $groupLabel ?></p>
        <ul>
            <?php foreach ($items as $key => $item): ?>
            <li>
                <a href="<?= $item['href'] ?>"
                   class="nav-link <?= ($currentPage === $key) ? 'active' : '' ?>">
                    <i class="bi <?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                    <?php if ($currentPage === $key): ?>
                        <span class="active-dot"></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endforeach; ?>

        <p class="nav-label mt-3">Account</p>
        <ul>
            <li>
                <a href="../logout.php"
                   class="nav-link text-danger-soft"
                   onclick="return confirm('Log out of admin panel?')">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-user-card">
        <div class="su-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'A', 0, 1)) ?></div>
        <div class="su-info">
            <div class="su-name"><?= $adminName ?></div>
            <div class="su-role">Administrator</div>
        </div>
        <a href="../logout.php" class="su-logout" title="Logout"
           onclick="return confirm('Log out?')">
            <i class="bi bi-power"></i>
        </a>
    </div>

</aside>