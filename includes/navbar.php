<?php

$currentUserName = $_SESSION['user_name'] ?? 'User';
$currentUserRole = $_SESSION['user_role'] ?? '';

?>

<header class="dashboard-header">

    <div class="dashboard-header-left">

        <button
            type="button"
            class="mobile-menu-toggle"
            id="mobileMenuToggle"
            aria-label="Open navigation"
        >
            <span></span>
            <span></span>
            <span></span>
        </button>

        <a
            href="/student-attendance-system/index.php"
            class="dashboard-brand"
        >

            <span class="brand-icon">
                SA
            </span>

            <span class="brand-name">
                Student Attendance System
            </span>

        </a>

    </div>


    <div class="dashboard-header-right">

        <div class="user-information">

            <span class="user-name">
                <?= htmlspecialchars($currentUserName) ?>
            </span>

            <span class="user-role">
                <?= htmlspecialchars($currentUserRole) ?>
            </span>

        </div>

        <a
            href="/student-attendance-system/logout.php"
            class="logout-button"
        >
            Sign Out
        </a>

    </div>

</header>