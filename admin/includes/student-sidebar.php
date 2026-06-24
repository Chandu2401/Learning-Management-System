<?php
/**
 * includes/student-sidebar.php
 * Placement: lms-project/includes/student-sidebar.php
 *
 * Reusable sidebar for all student pages.
 * Requires $currentPage to be set before including.
 * e.g. $currentPage = 'browse';
 */

$_sNav = [
    'dashboard'  => ['icon' => 'bi-grid-1x2-fill',    'label' => 'Dashboard',       'href' => 'dashboard.php'],
    'browse'     => ['icon' => 'bi-compass-fill',       'label' => 'Browse Courses',  'href' => 'browse-courses.php'],
    'my_courses' => ['icon' => 'bi-book-fill',          'label' => 'My Courses',      'href' => 'my-courses.php'],
];
$_sAccount = [
    'logout'     => ['icon' => 'bi-box-arrow-left',    'label' => 'Logout',          'href' => 'logout.php'],
];
?>
<div class="st-overlay" id="stOverlay"></div>

<aside class="st-sidebar" id="stSidebar">
    <div class="st-brand">
        <div class="st-brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
        <div>
            <div class="st-brand-name">Learn<span>Hub</span></div>
            <div class="st-brand-sub">Student Portal</div>
        </div>
    </div>

    <nav class="st-nav">
        <p class="st-nav-label">Menu</p>
        <ul>
            <?php foreach ($_sNav as $key => $item): ?>
            <li>
                <a href="<?= $item['href'] ?>"
                   class="st-nav-link <?= ($currentPage === $key) ? 'active' : '' ?>">
                    <i class="bi <?= $item['icon'] ?>"></i>
                    <span><?= $item['label'] ?></span>
                    <?php if ($currentPage === $key): ?>
                    <span class="st-nav-dot"></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <p class="st-nav-label mt-3">Account</p>
        <ul>
            <li>
                <a href="logout.php" class="st-nav-link logout"
                   onclick="return confirm('Log out of your account?')">
                    <i class="bi bi-box-arrow-left"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="st-user-card">
        <div class="st-avatar">
            <?= strtoupper(substr($authUserName ?: 'S', 0, 1)) ?>
        </div>
        <div style="flex:1;overflow:hidden;">
            <div class="st-user-name"><?= htmlspecialchars($authUserName) ?></div>
            <div class="st-user-role">Student</div>
        </div>
        <a href="logout.php" class="st-logout-btn" title="Logout"
           onclick="return confirm('Log out?')">
            <i class="bi bi-power"></i>
        </a>
    </div>
</aside>