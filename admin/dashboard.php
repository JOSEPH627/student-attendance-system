<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Admin Dashboard';
$pageType = 'admin';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css'
];



/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$activeStudents = 0;
$teachers = 0;
$classes = 0;
$subjects = 0;

$todayPresent = 0;
$todayAbsent = 0;
$todayPermission = 0;
$totalAttendance = 0;
$attendanceRate = 0;



/*
|--------------------------------------------------------------------------
| Main Statistics
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM students
        WHERE status = 'ACTIVE'
    ");

    $activeStudents = (int) $stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM teachers
    ");

    $teachers = (int) $stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM classes
    ");

    $classes = (int) $stmt->fetchColumn();


    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM subjects
    ");

    $subjects = (int) $stmt->fetchColumn();



    /*
    |--------------------------------------------------------------------------
    | Today's Attendance
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT
            SUM(status = 'PRESENT') AS present_count,
            SUM(status = 'ABSENT') AS absent_count,
            SUM(status = 'PERMISSION') AS permission_count
        FROM attendance
        WHERE attendance_date = CURDATE()
    ");

    $todayAttendance = $stmt->fetch();

    if ($todayAttendance) {

        $todayPresent =
            (int) ($todayAttendance['present_count'] ?? 0);

        $todayAbsent =
            (int) ($todayAttendance['absent_count'] ?? 0);

        $todayPermission =
            (int) ($todayAttendance['permission_count'] ?? 0);
    }



    /*
    |--------------------------------------------------------------------------
    | Total Attendance
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM attendance
    ");

    $totalAttendance = (int) $stmt->fetchColumn();



    /*
    |--------------------------------------------------------------------------
    | Attendance Rate
    |--------------------------------------------------------------------------
    */

    if ($totalAttendance > 0) {

        $attendanceRate =
            round(
                ($todayPresent / $totalAttendance) * 100,
                1
            );
    }

} catch (PDOException $e) {

    $activeStudents = 0;
    $teachers = 0;
    $classes = 0;
    $subjects = 0;

    $todayPresent = 0;
    $todayAbsent = 0;
    $todayPermission = 0;

    $totalAttendance = 0;
    $attendanceRate = 0;
}



require_once __DIR__ . '/../includes/header.php';

?>



<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>



    <div class="dashboard-main">

        <?php require_once __DIR__ . '/../includes/navbar.php'; ?>



        <main class="dashboard-content">



            <!-- =====================================================
                 PAGE HEADER
                 ===================================================== -->

            <section class="dashboard-page-header">

                <div>

                    <span class="dashboard-eyebrow">
                        Administration
                    </span>

                    <h1>
                        Dashboard
                    </h1>

                    <p>
                        Overview of your student attendance system.
                    </p>

                </div>


                <div class="dashboard-date">

                    <svg
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <rect
                            x="3"
                            y="4"
                            width="18"
                            height="18"
                            rx="2"
                            ry="2"
                        ></rect>

                        <line
                            x1="16"
                            y1="2"
                            x2="16"
                            y2="6"
                        ></line>

                        <line
                            x1="8"
                            y1="2"
                            x2="8"
                            y2="6"
                        ></line>

                        <line
                            x1="3"
                            y1="10"
                            x2="21"
                            y2="10"
                        ></line>
                    </svg>

                    <span>
                        <?= date('F j, Y') ?>
                    </span>

                </div>

            </section>



            <!-- =====================================================
                 MAIN STATISTICS
                 ===================================================== -->

            <section class="dashboard-stats-grid">



                <!-- STUDENTS -->

                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon stat-icon-green">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                ></path>

                                <circle
                                    cx="9"
                                    cy="7"
                                    r="4"
                                ></circle>

                                <path
                                    d="M22 21v-2a4 4 0 0 0-3-3.87"
                                ></path>

                                <path
                                    d="M16 3.13a4 4 0 0 1 0 7.75"
                                ></path>

                            </svg>

                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($activeStudents) ?>
                    </div>

                    <div class="stat-label">
                        Active Students
                    </div>

                </div>



                <!-- TEACHERS -->

                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon stat-icon-blue">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
                                ></path>

                                <circle
                                    cx="12"
                                    cy="7"
                                    r="4"
                                ></circle>

                            </svg>

                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($teachers) ?>
                    </div>

                    <div class="stat-label">
                        Teachers
                    </div>

                </div>



                <!-- CLASSES -->

                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon stat-icon-orange">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M3 21h18"
                                ></path>

                                <path
                                    d="M5 21V5l7-3 7 3v16"
                                ></path>

                                <path
                                    d="M9 9h1"
                                ></path>

                                <path
                                    d="M14 9h1"
                                ></path>

                                <path
                                    d="M9 13h1"
                                ></path>

                                <path
                                    d="M14 13h1"
                                ></path>

                            </svg>

                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($classes) ?>
                    </div>

                    <div class="stat-label">
                        Classes
                    </div>

                </div>



                <!-- SUBJECTS -->

                <div class="stat-card">

                    <div class="stat-card-top">

                        <div class="stat-icon stat-icon-purple">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                                ></path>

                                <path
                                    d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                                ></path>

                            </svg>

                        </div>

                    </div>


                    <div class="stat-value">
                        <?= number_format($subjects) ?>
                    </div>

                    <div class="stat-label">
                        Subjects
                    </div>

                </div>



            </section>



                           <div class="quick-actions">



                    <!-- ADD STUDENT -->

                    <a
                        href="/student-attendance-system/admin/add-student.php"
                        class="quick-action-card"
                    >

                        <span class="quick-action-icon quick-icon-green">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                ></path>

                                <circle
                                    cx="9"
                                    cy="7"
                                    r="4"
                                ></circle>

                                <line
                                    x1="19"
                                    y1="8"
                                    x2="19"
                                    y2="14"
                                ></line>

                                <line
                                    x1="16"
                                    y1="11"
                                    x2="22"
                                    y2="11"
                                ></line>

                            </svg>

                        </span>


                        <span class="quick-action-content">

                            <span class="quick-action-title">
                                Add Student
                            </span>

                            <span class="quick-action-description">
                                Register a student
                            </span>

                        </span>


                        <svg
                            class="quick-action-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <line
                                x1="5"
                                y1="12"
                                x2="19"
                                y2="12"
                            ></line>

                            <polyline
                                points="12 5 19 12 12 19"
                            ></polyline>

                        </svg>

                    </a>



                    <!-- ADD TEACHER -->

                    <a
                        href="/student-attendance-system/admin/add-teacher.php"
                        class="quick-action-card"
                    >

                        <span class="quick-action-icon quick-icon-blue">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                ></path>

                                <circle
                                    cx="9"
                                    cy="7"
                                    r="4"
                                ></circle>

                                <line
                                    x1="19"
                                    y1="8"
                                    x2="19"
                                    y2="14"
                                ></line>

                                <line
                                    x1="16"
                                    y1="11"
                                    x2="22"
                                    y2="11"
                                ></line>

                            </svg>

                        </span>


                        <span class="quick-action-content">

                            <span class="quick-action-title">
                                Add Teacher
                            </span>

                            <span class="quick-action-description">
                                Create teacher account
                            </span>

                        </span>


                        <svg
                            class="quick-action-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <line
                                x1="5"
                                y1="12"
                                x2="19"
                                y2="12"
                            ></line>

                            <polyline
                                points="12 5 19 12 12 19"
                            ></polyline>

                        </svg>

                    </a>



                    <!-- ADD CLASS -->

                    <a
                        href="/student-attendance-system/admin/classes.php#class-form"
                        class="quick-action-card"
                    >

                        <span class="quick-action-icon quick-icon-orange">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M3 21h18"
                                ></path>

                                <path
                                    d="M5 21V5l7-3 7 3v16"
                                ></path>

                                <line
                                    x1="12"
                                    y1="9"
                                    x2="12"
                                    y2="15"
                                ></line>

                                <line
                                    x1="9"
                                    y1="12"
                                    x2="15"
                                    y2="12"
                                ></line>

                            </svg>

                        </span>


                        <span class="quick-action-content">

                            <span class="quick-action-title">
                                Add Class
                            </span>

                            <span class="quick-action-description">
                                Create a class
                            </span>

                        </span>


                        <svg
                            class="quick-action-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <line
                                x1="5"
                                y1="12"
                                x2="19"
                                y2="12"
                            ></line>

                            <polyline
                                points="12 5 19 12 12 19"
                            ></polyline>

                        </svg>

                    </a>



                    <!-- ADD SUBJECT -->

                    <a
                        href="/student-attendance-system/admin/subjects.php"
                        class="quick-action-card"
                    >

                        <span class="quick-action-icon quick-icon-purple">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                                ></path>

                                <path
                                    d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                                ></path>

                                <line
                                    x1="12"
                                    y1="7"
                                    x2="12"
                                    y2="13"
                                ></line>

                                <line
                                    x1="9"
                                    y1="10"
                                    x2="15"
                                    y2="10"
                                ></line>

                            </svg>

                        </span>


                        <span class="quick-action-content">

                            <span class="quick-action-title">
                                Add Subject
                            </span>

                            <span class="quick-action-description">
                                Create a subject
                            </span>

                        </span>


                        <svg
                            class="quick-action-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <line
                                x1="5"
                                y1="12"
                                x2="19"
                                y2="12"
                            ></line>

                            <polyline
                                points="12 5 19 12 12 19"
                            ></polyline>

                        </svg>

                    </a>



                </div>

            </section>



            <!-- =====================================================
                 TODAY'S ATTENDANCE
                 ===================================================== -->

            <section class="dashboard-section">

                <div class="dashboard-section-header">

                    <div>

                        <span class="dashboard-eyebrow">
                            Attendance
                        </span>

                        <h2>
                            Today's Attendance
                        </h2>

                    </div>


                    <a
                        href="/student-attendance-system/admin/attendance.php"
                        class="dashboard-section-link"
                    >
                        Manage Attendance

                        <svg
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <line
                                x1="5"
                                y1="12"
                                x2="19"
                                y2="12"
                            ></line>

                            <polyline
                                points="12 5 19 12 12 19"
                            ></polyline>

                        </svg>

                    </a>

                </div>



                <div class="attendance-overview-card">



                    <!-- PRESENT -->

                    <div class="attendance-overview-item">

                        <div class="attendance-overview-icon present">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <polyline
                                    points="20 6 9 17 4 12"
                                ></polyline>

                            </svg>

                        </div>


                        <div>

                            <span class="attendance-overview-value">
                                <?= number_format($todayPresent) ?>
                            </span>

                            <span class="attendance-overview-label">
                                Present
                            </span>

                        </div>

                    </div>



                    <!-- ABSENT -->

                    <div class="attendance-overview-item">

                        <div class="attendance-overview-icon absent">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <line
                                    x1="18"
                                    y1="6"
                                    x2="6"
                                    y2="18"
                                ></line>

                                <line
                                    x1="6"
                                    y1="6"
                                    x2="18"
                                    y2="18"
                                ></line>

                            </svg>

                        </div>


                        <div>

                            <span class="attendance-overview-value">
                                <?= number_format($todayAbsent) ?>
                            </span>

                            <span class="attendance-overview-label">
                                Absent
                            </span>

                        </div>

                    </div>



                    <!-- PERMISSION -->

                    <div class="attendance-overview-item">

                        <div class="attendance-overview-icon permission">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                ></circle>

                                <line
                                    x1="12"
                                    y1="8"
                                    x2="12"
                                    y2="12"
                                ></line>

                                <line
                                    x1="12"
                                    y1="16"
                                    x2="12.01"
                                    y2="16"
                                ></line>

                            </svg>

                        </div>


                        <div>

                            <span class="attendance-overview-value">
                                <?= number_format($todayPermission) ?>
                            </span>

                            <span class="attendance-overview-label">
                                Permission
                            </span>

                        </div>

                    </div>



                    <!-- TOTAL -->

                    <div class="attendance-overview-item">

                        <div class="attendance-overview-icon total">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M8 6h13"
                                ></path>

                                <path
                                    d="M8 12h13"
                                ></path>

                                <path
                                    d="M8 18h13"
                                ></path>

                                <path
                                    d="M3 6h.01"
                                ></path>

                                <path
                                    d="M3 12h.01"
                                ></path>

                                <path
                                    d="M3 18h.01"
                                ></path>

                            </svg>

                        </div>


                        <div>

                            <span class="attendance-overview-value">
                                <?= number_format($totalAttendance) ?>
                            </span>

                            <span class="attendance-overview-label">
                                Total Records
                            </span>

                        </div>

                    </div>



                    <!-- RATE -->

                    <div class="attendance-rate-block">

                        <div class="attendance-rate-top">

                            <span>
                                Attendance Rate
                            </span>

                            <strong>
                                <?= number_format($attendanceRate, 1) ?>%
                            </strong>

                        </div>


                        <div class="attendance-progress">

                            <div
                                class="attendance-progress-bar"
                                style="width: <?= min(100, max(0, $attendanceRate)) ?>%;"
                            ></div>

                        </div>

                    </div>



                </div>

            </section>



            <!-- =====================================================
                 MANAGEMENT SHORTCUTS
                 ===================================================== -->

            <section class="dashboard-section management-shortcuts-section">

                <div class="dashboard-section-header">

                    <div>

                        <span class="dashboard-eyebrow">
                            Navigation
                        </span>

                        <h2>
                            Management Shortcuts
                        </h2>

                    </div>

                </div>



                <div class="management-shortcuts-grid">



                    <!-- STUDENTS -->

                    <a
                        href="/student-attendance-system/admin/students.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon green">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                ></path>

                                <circle
                                    cx="9"
                                    cy="7"
                                    r="4"
                                ></circle>

                                <path
                                    d="M22 21v-2a4 4 0 0 0-3-3.87"
                                ></path>

                                <path
                                    d="M16 3.13a4 4 0 0 1 0 7.75"
                                ></path>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Students
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                    <!-- TEACHERS -->

                    <a
                        href="/student-attendance-system/admin/teachers.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon blue">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="7"
                                    r="4"
                                ></circle>

                                <path
                                    d="M5.5 21a6.5 6.5 0 0 1 13 0"
                                ></path>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Teachers
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                    <!-- CLASSES -->

                    <a
                        href="/student-attendance-system/admin/classes.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon orange">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M3 21h18"
                                ></path>

                                <path
                                    d="M5 21V5l7-3 7 3v16"
                                ></path>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Classes
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                    <!-- SUBJECTS -->

                    <a
                        href="/student-attendance-system/admin/subjects.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon purple">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"
                                ></path>

                                <path
                                    d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
                                ></path>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Subjects
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                    <!-- ATTENDANCE -->

                    <a
                        href="/student-attendance-system/admin/attendance.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon green">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <polyline
                                    points="20 6 9 17 4 12"
                                ></polyline>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Attendance
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                    <!-- REPORTS -->

                    <a
                        href="/student-attendance-system/admin/reports.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon blue">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <line
                                    x1="18"
                                    y1="20"
                                    x2="18"
                                    y2="10"
                                ></line>

                                <line
                                    x1="12"
                                    y1="20"
                                    x2="12"
                                    y2="4"
                                ></line>

                                <line
                                    x1="6"
                                    y1="20"
                                    x2="6"
                                    y2="14"
                                ></line>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Reports
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                    <!-- USERS -->

                    <a
                        href="/student-attendance-system/admin/users.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon orange">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path
                                    d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"
                                ></path>

                                <circle
                                    cx="9"
                                    cy="7"
                                    r="4"
                                ></circle>

                                <path
                                    d="M23 21v-2a4 4 0 0 0-3-3.87"
                                ></path>

                                <path
                                    d="M16 3.13a4 4 0 0 1 0 7.75"
                                ></path>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Users
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                    <!-- SETTINGS -->

                    <a
                        href="/student-attendance-system/admin/settings.php"
                        class="management-shortcut"
                    >

                        <span class="management-shortcut-icon purple">

                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="3"
                                ></circle>

                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06-1.42 1.42-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21h-2v-.08a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06-1.42-1.42.06-.06A1.65 1.65 0 0 0 8.6 15a1.65 1.65 0 0 0-1.51-1H7v-2h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06 1.42-1.42.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 .99-1.51V5h2v.08a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06 1.42 1.42-.06.06A1.65 1.65 0 0 0 19.4 9c.17.6.72 1 1.34 1H21v2h-.08a1.65 1.65 0 0 0-1.52 1 1.65 1.65 0 0 0 0 2z"
                                ></path>

                            </svg>

                        </span>

                        <span class="management-shortcut-text">
                            Settings
                        </span>

                        <svg
                            class="management-shortcut-arrow"
                            width="14"
                            height="14"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <polyline
                                points="9 18 15 12 9 6"
                            ></polyline>
                        </svg>

                    </a>



                </div>

            </section>



        </main>



        <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    </div>

</div>



<script
    src="/student-attendance-system/assets/js/main.js"
    defer
></script>