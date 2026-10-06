
<?php

$currentUserRole = $_SESSION['user_role'] ?? '';

?>

<div
    class="sidebar-overlay"
    id="sidebarOverlay"
></div>


<aside
    class="dashboard-sidebar"
    id="dashboardSidebar"
>

    <div class="sidebar-header">

        <div class="sidebar-brand">

            <span class="brand-icon">
                SA
            </span>

            <span>
                <?= $currentUserRole === 'ADMIN'
                    ? 'Administration'
                    : 'Teacher Portal'
                ?>
            </span>

        </div>


        <button
            type="button"
            class="sidebar-close"
            id="sidebarClose"
            aria-label="Close navigation"
        >
            &times;
        </button>

    </div>


    <nav class="sidebar-navigation">

        <?php if ($currentUserRole === 'ADMIN'): ?>

            <a href="/student-attendance-system/admin/dashboard.php">
                Dashboard
            </a>

            <a href="/student-attendance-system/admin/students.php">
                Students
            </a>

            <a href="/student-attendance-system/admin/teachers.php">
                Teachers
            </a>

            <a href="/student-attendance-system/admin/classes.php">
                Classes
            </a>

            <a href="/student-attendance-system/admin/subjects.php">
                Subjects
            </a>

            <a href="/student-attendance-system/admin/attendance.php">
                Attendance
            </a>

            <a href="/student-attendance-system/admin/attendance-history.php">
                Attendance History
            </a>

            <a href="/student-attendance-system/admin/reports.php">
                Reports
            </a>

            <a href="/student-attendance-system/admin/users.php">
                Users
            </a>

            <a href="/student-attendance-system/admin/settings.php">
                Settings
            </a>

        <?php elseif ($currentUserRole === 'TEACHER'): ?>

            <a href="/student-attendance-system/teacher/dashboard.php">
                Dashboard
            </a>

            <a href="/student-attendance-system/teacher/my-classes.php">
                My Classes
            </a>

            <a href="/student-attendance-system/teacher/my-students.php">
                My Students
            </a>

            <a href="/student-attendance-system/teacher/attendance.php">
                Take Attendance
            </a>

            <a href="/student-attendance-system/teacher/attendance-history.php">
                Attendance History
            </a>

            <a href="/student-attendance-system/teacher/reports.php">
                Reports
            </a>

        <?php endif; ?>

    </nav>

</aside>
