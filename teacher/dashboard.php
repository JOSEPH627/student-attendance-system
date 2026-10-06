<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('TEACHER');

$pageTitle = 'Teacher Dashboard';
$pageType = 'teacher';

$additionalStyles = [
    '/student-attendance-system/assets/css/teacher.css'
];

$teacherUserId = currentUserId();

$teacher = null;
$assignedClasses = [];
$assignedSubjects = [];
$assignedStudents = [];
$totalAttendance = 0;

$presentCount = 0;
$absentCount = 0;
$permissionCount = 0;
$attendanceRate = 0;

$todayTotal = 0;
$todayPresent = 0;
$todayAbsent = 0;
$todayPermission = 0;

$recentAttendance = [];

try {

    /* =====================================================
       TEACHER PROFILE
       ===================================================== */

    $teacherStmt = $pdo->prepare("
        SELECT
            t.id,
            t.employee_number,
            t.phone,
            t.department,
            u.name,
            u.email
        FROM teachers t
        INNER JOIN users u
            ON u.id = t.user_id
        WHERE t.user_id = ?
        LIMIT 1
    ");

    $teacherStmt->execute([$teacherUserId]);

    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);


    if (!$teacher) {
        throw new RuntimeException('Teacher profile could not be found.');
    }

    $teacherId = (int) $teacher['id'];


    /* =====================================================
       ASSIGNED CLASSES
       ===================================================== */

    $classesStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ta.class_id)
        FROM teaching_assignments ta
        WHERE ta.teacher_id = ?
    ");

    $classesStmt->execute([$teacherId]);

    $assignedClasses = (int) $classesStmt->fetchColumn();


    /* =====================================================
       ASSIGNED SUBJECTS
       ===================================================== */

    $subjectsStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ta.subject_id)
        FROM teaching_assignments ta
        WHERE ta.teacher_id = ?
    ");

    $subjectsStmt->execute([$teacherId]);

    $assignedSubjects = (int) $subjectsStmt->fetchColumn();


    /* =====================================================
       ASSIGNED STUDENTS
       ===================================================== */

    $studentsStmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id)
        FROM students s
        INNER JOIN teaching_assignments ta
            ON ta.class_id = s.class_id
        WHERE ta.teacher_id = ?
        AND s.status = 'ACTIVE'
    ");

    $studentsStmt->execute([$teacherId]);

    $assignedStudents = (int) $studentsStmt->fetchColumn();


    /* =====================================================
       TOTAL ATTENDANCE RECORDS
       ===================================================== */

    $attendanceStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM attendance
        WHERE teacher_id = ?
    ");

    $attendanceStmt->execute([$teacherId]);

    $totalAttendance = (int) $attendanceStmt->fetchColumn();


    /* =====================================================
       ATTENDANCE SUMMARY
       ===================================================== */

    $summaryStmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS total
        FROM attendance
        WHERE teacher_id = ?
        GROUP BY status
    ");

    $summaryStmt->execute([$teacherId]);

    $attendanceSummary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attendanceSummary as $summary) {

        $status = strtoupper((string) $summary['status']);
        $count = (int) $summary['total'];

        if ($status === 'PRESENT') {
            $presentCount = $count;
        } elseif ($status === 'ABSENT') {
            $absentCount = $count;
        } elseif ($status === 'PERMISSION') {
            $permissionCount = $count;
        }
    }


    /* =====================================================
       ATTENDANCE RATE
       ===================================================== */

    if ($totalAttendance > 0) {
        $attendanceRate = round(
            ($presentCount / $totalAttendance) * 100,
            1
        );
    }


    /* =====================================================
       TODAY'S ATTENDANCE
       ===================================================== */

    $todayStmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) AS total
        FROM attendance
        WHERE teacher_id = ?
        AND attendance_date = CURDATE()
        GROUP BY status
    ");

    $todayStmt->execute([$teacherId]);

    $todaySummary = $todayStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($todaySummary as $summary) {

        $status = strtoupper((string) $summary['status']);
        $count = (int) $summary['total'];

        if ($status === 'PRESENT') {
            $todayPresent = $count;
        } elseif ($status === 'ABSENT') {
            $todayAbsent = $count;
        } elseif ($status === 'PERMISSION') {
            $todayPermission = $count;
        }
    }

    $todayTotal =
        $todayPresent +
        $todayAbsent +
        $todayPermission;


    /* =====================================================
       MY CLASSES
       IMPORTANT:
       Database column is c.code, NOT c.class_code
       ===================================================== */

    $assignedClassesStmt = $pdo->prepare("
        SELECT
            c.id,
            c.name,
            c.code,
            c.academic_year,
            COUNT(DISTINCT s.id) AS student_count,
            COUNT(DISTINCT ta.subject_id) AS subject_count
        FROM teaching_assignments ta

        INNER JOIN classes c
            ON c.id = ta.class_id

        LEFT JOIN students s
            ON s.class_id = c.id
            AND s.status = 'ACTIVE'

        WHERE ta.teacher_id = ?

        GROUP BY
            c.id,
            c.name,
            c.code,
            c.academic_year

        ORDER BY c.name ASC
    ");

    $assignedClassesStmt->execute([$teacherId]);

    $classes = $assignedClassesStmt->fetchAll(PDO::FETCH_ASSOC);


    /* =====================================================
       SUBJECTS FOR EACH CLASS
       ===================================================== */

    $subjectsByClass = [];

    $subjectsByClassStmt = $pdo->prepare("
        SELECT
            ta.class_id,
            s.id,
            s.name,
            s.code
        FROM teaching_assignments ta

        INNER JOIN subjects s
            ON s.id = ta.subject_id

        WHERE ta.teacher_id = ?

        ORDER BY
            ta.class_id ASC,
            s.name ASC
    ");

    $subjectsByClassStmt->execute([$teacherId]);

    $classSubjects = $subjectsByClassStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($classSubjects as $subject) {

        $classId = (int) $subject['class_id'];

        if (!isset($subjectsByClass[$classId])) {
            $subjectsByClass[$classId] = [];
        }

        $subjectsByClass[$classId][] = $subject;
    }


    /* =====================================================
       RECENT ATTENDANCE
       ===================================================== */

    $recentStmt = $pdo->prepare("
        SELECT
            a.id,
            a.attendance_date,
            a.status,
            a.permission_reason,

            s.name AS student_name,
            s.student_reference,
            s.photo,

            c.name AS class_name,
            c.code AS class_code,

            sub.name AS subject_name,
            sub.code AS subject_code

        FROM attendance a

        INNER JOIN students s
            ON s.id = a.student_id

        INNER JOIN classes c
            ON c.id = a.class_id

        INNER JOIN subjects sub
            ON sub.id = a.subject_id

        WHERE a.teacher_id = ?

        ORDER BY
            a.attendance_date DESC,
            a.id DESC

        LIMIT 10
    ");

    $recentStmt->execute([$teacherId]);

    $recentAttendance = $recentStmt->fetchAll(PDO::FETCH_ASSOC);


} catch (Throwable $e) {

    $dashboardError = $e->getMessage();

    $classes = [];
    $subjectsByClass = [];
    $recentAttendance = [];

}


/* =========================================================
   TEACHER INITIALS
   ========================================================= */

$teacherName = $teacher['name'] ?? 'Teacher';

$nameParts = preg_split(
    '/\s+/',
    trim($teacherName)
);

$teacherInitials = '';

if (!empty($nameParts[0])) {
    $teacherInitials .= strtoupper(
        substr($nameParts[0], 0, 1)
    );
}

if (
    count($nameParts) > 1 &&
    !empty($nameParts[count($nameParts) - 1])
) {
    $teacherInitials .= strtoupper(
        substr(
            $nameParts[count($nameParts) - 1],
            0,
            1
        )
    );
}

if ($teacherInitials === '') {
    $teacherInitials = 'T';
}


/* =========================================================
   STATUS HELPERS
   ========================================================= */

function teacherStatusClass(string $status): string
{
    return match (strtoupper($status)) {

        'PRESENT' => 'status-present',

        'ABSENT' => 'status-absent',

        'PERMISSION' => 'status-permission',

        default => 'status-default'
    };
}


function teacherStatusLabel(string $status): string
{
    return match (strtoupper($status)) {

        'PRESENT' => 'Present',

        'ABSENT' => 'Absent',

        'PERMISSION' => 'Permission',

        default => ucfirst(
            strtolower($status)
        )
    };
}


/* =========================================================
   TODAY ATTENDANCE PERCENTAGE
   ========================================================= */

$todayPresentPercentage = 0;

if ($todayTotal > 0) {
    $todayPresentPercentage = round(
        ($todayPresent / $todayTotal) * 100,
        1
    );
}

?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<main class="dashboard-main teacher-page teacher-dashboard">

```
<!-- =================================================
     PAGE HEADER
     ================================================= -->

<div class="dashboard-page-header">

    <div>

        <span class="teacher-page-eyebrow">
            Teacher Portal
        </span>

        <h1>
            Teacher Dashboard
        </h1>

        <p>
            Manage your classes, students and attendance
            from one place.
        </p>

    </div>

</div>


<!-- =================================================
     WELCOME
     ================================================= -->

<section class="teacher-welcome">

    <h2>
        Welcome back,
        <?= e($teacherName) ?>!
    </h2>

    <p>
        Here is an overview of your teaching activities
        and attendance records.
    </p>

    <?php if (!empty($teacher['employee_number'])): ?>

        <small>
            Employee No:
            <?= e($teacher['employee_number']) ?>
        </small>

    <?php endif; ?>

</section>


<!-- =================================================
     DASHBOARD STATS
     ================================================= -->

<section class="dashboard-stats">


    <!-- Classes -->

    <div class="stat-card">

        <div class="stat-card-icon teacher-stat-icon-green">

            <svg
                width="21"
                height="21"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M3 21h18"></path>
                <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                <path d="M9 7h1"></path>
                <path d="M14 7h1"></path>
                <path d="M9 11h1"></path>
                <path d="M14 11h1"></path>
                <path d="M9 15h1"></path>
                <path d="M14 15h1"></path>
            </svg>

        </div>

        <h3>
            <?= number_format($assignedClasses) ?>
        </h3>

        <p>
            Assigned Classes
        </p>

    </div>


    <!-- Subjects -->

    <div class="stat-card">

        <div class="stat-card-icon teacher-stat-icon-blue">

            <svg
                width="21"
                height="21"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>

        </div>

        <h3>
            <?= number_format($assignedSubjects) ?>
        </h3>

        <p>
            Assigned Subjects
        </p>

    </div>


    <!-- Students -->

    <div class="stat-card">

        <div class="stat-card-icon teacher-stat-icon-orange">

            <svg
                width="21"
                height="21"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>

        </div>

        <h3>
            <?= number_format($assignedStudents) ?>
        </h3>

        <p>
            Active Students
        </p>

    </div>


    <!-- Attendance -->

    <div class="stat-card">

        <div class="stat-card-icon teacher-stat-icon-purple">

            <svg
                width="21"
                height="21"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
            </svg>

        </div>

        <h3>
            <?= number_format($totalAttendance) ?>
        </h3>

        <p>
            Attendance Records
        </p>

    </div>

</section>


<!-- =================================================
     ATTENDANCE ANALYTICS
     ================================================= -->

<section class="teacher-analytics-grid">


    <!-- =============================================
         ATTENDANCE SUMMARY
         ============================================= -->

    <div class="teacher-analytics-card teacher-overall-card">

        <div class="teacher-analytics-header">

            <div class="teacher-analytics-heading">

                <div class="teacher-analytics-heading-icon teacher-heading-icon-green">

                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M3 3v18h18"></path>
                        <path d="M7 16l4-5 3 3 5-7"></path>
                    </svg>

                </div>

                <div>

                    <h2>
                        Attendance Summary
                    </h2>

                    <p>
                        Overall attendance performance
                    </p>

                </div>

            </div>

            <a
                href="/student-attendance-system/teacher/reports.php"
                class="teacher-view-link"
            >
                View Reports

                <svg
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M5 12h14"></path>
                    <path d="M13 6l6 6-6 6"></path>
                </svg>

            </a>

        </div>


        <div class="teacher-overall-content">


            <!-- Attendance Rate -->

            <div class="teacher-rate-section">

                <div
                    class="teacher-rate-circle"
                    style="--attendance-rate: <?= $attendanceRate ?>;"
                >

                    <div class="teacher-rate-circle-inner">

                        <strong>
                            <?= number_format($attendanceRate, 1) ?>%
                        </strong>

                        <span>
                            Rate
                        </span>

                    </div>

                </div>

                <div class="teacher-rate-caption">
                    Overall Attendance Rate
                </div>

            </div>


            <!-- Breakdown -->

            <div class="teacher-breakdown">


                <!-- Present -->

                <div class="teacher-breakdown-item teacher-breakdown-present">

                    <div class="teacher-breakdown-left">

                        <div class="teacher-breakdown-icon">

                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M20 6L9 17l-5-5"></path>
                            </svg>

                        </div>

                        <span>
                            Present
                        </span>

                    </div>

                    <span class="teacher-breakdown-number teacher-number-present">
                        <?= number_format($presentCount) ?>
                    </span>

                </div>


                <!-- Absent -->

                <div class="teacher-breakdown-item teacher-breakdown-absent">

                    <div class="teacher-breakdown-left">

                        <div class="teacher-breakdown-icon">

                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M18 6L6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>

                        </div>

                        <span>
                            Absent
                        </span>

                    </div>

                    <span class="teacher-breakdown-number teacher-number-absent">
                        <?= number_format($absentCount) ?>
                    </span>

                </div>


                <!-- Permission -->

                <div class="teacher-breakdown-item teacher-breakdown-permission">

                    <div class="teacher-breakdown-left">

                        <div class="teacher-breakdown-icon">

                            <svg
                                width="16"
                                height="16"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 7v5"></path>
                                <path d="M12 16h.01"></path>
                            </svg>

                        </div>

                        <span>
                            Permission
                        </span>

                    </div>

                    <span class="teacher-breakdown-number teacher-number-permission">
                        <?= number_format($permissionCount) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- Total Records -->

        <div class="teacher-total-records">

            <div class="teacher-total-label">

                <strong>
                    Total Attendance Records
                </strong>

                <span>
                    All attendance records submitted by you
                </span>

            </div>

            <div class="teacher-total-description">
                <?= number_format($totalAttendance) ?>
            </div>

        </div>

    </div>


    <!-- =============================================
         TODAY'S ATTENDANCE
         ============================================= -->

    <div class="teacher-analytics-card teacher-today-card">

        <div class="teacher-analytics-header">

            <div class="teacher-analytics-heading">

                <div class="teacher-analytics-heading-icon teacher-heading-icon-orange">

                    <svg
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
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

                </div>

                <div>

                    <h2>
                        Today's Attendance
                    </h2>

                    <p>
                        Attendance recorded today
                    </p>

                </div>

            </div>

            <span class="teacher-today-status">

                <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <circle
                        cx="12"
                        cy="12"
                        r="9"
                    ></circle>

                    <path d="M12 7v5l3 2"></path>
                </svg>

                Today

            </span>

        </div>


        <?php if ($todayTotal > 0): ?>

            <div class="teacher-today-overview">


                <div class="teacher-today-total">

                    <div class="teacher-today-total-label">

                        <span>
                            Total Marked
                        </span>

                        <strong>
                            <?= number_format($todayTotal) ?>
                        </strong>

                    </div>

                    <div class="teacher-today-date">
                        <?= date('d M Y') ?>
                    </div>

                </div>


                <!-- Progress -->

                <div class="teacher-today-progress">

                    <div class="teacher-progress-header">

                        <span>
                            Present Rate
                        </span>

                        <span>
                            <?= number_format($todayPresentPercentage, 1) ?>%
                        </span>

                    </div>

                    <div class="teacher-progress-track">

                        <div
                            class="teacher-progress-fill"
                            style="width: <?= min(100, max(0, $todayPresentPercentage)) ?>%;"
                        ></div>

                    </div>

                </div>


                <!-- Today Breakdown -->

                <div class="teacher-today-breakdown">


                    <!-- Present -->

                    <div class="teacher-today-stat teacher-today-present">

                        <div class="teacher-today-stat-icon">

                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M20 6L9 17l-5-5"></path>
                            </svg>

                        </div>

                        <strong>
                            <?= number_format($todayPresent) ?>
                        </strong>

                        <span>
                            Present
                        </span>

                    </div>


                    <!-- Absent -->

                    <div class="teacher-today-stat teacher-today-absent">

                        <div class="teacher-today-stat-icon">

                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M18 6L6 18"></path>
                                <path d="M6 6l12 12"></path>
                            </svg>

                        </div>

                        <strong>
                            <?= number_format($todayAbsent) ?>
                        </strong>

                        <span>
                            Absent
                        </span>

                    </div>


                    <!-- Permission -->

                    <div class="teacher-today-stat teacher-today-permission">

                        <div class="teacher-today-stat-icon">

                            <svg
                                width="15"
                                height="15"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                ></circle>

                                <path d="M12 7v5"></path>

                                <path d="M12 16h.01"></path>
                            </svg>

                        </div>

                        <strong>
                            <?= number_format($todayPermission) ?>
                        </strong>

                        <span>
                            Permission
                        </span>

                    </div>

                </div>


                <a
                    href="/student-attendance-system/teacher/attendance-history.php?date_from=<?= date('Y-m-d') ?>&date_to=<?= date('Y-m-d') ?>"
                    class="teacher-history-button"
                >

                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M3 12a9 9 0 1 0 3-6.7"></path>
                        <path d="M3 4v5h5"></path>
                    </svg>

                    View Today's History

                </a>

            </div>

        <?php else: ?>

            <div class="teacher-today-empty">

                <div class="teacher-today-empty-icon">

                    <svg
                        width="23"
                        height="23"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
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

                </div>

                <h3>
                    No attendance recorded today
                </h3>

                <p>
                    You have not recorded any attendance
                    for today yet.
                </p>

                <a
                    href="/student-attendance-system/teacher/attendance.php"
                    class="teacher-take-attendance-button"
                >

                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>

                    Take Attendance

                </a>

            </div>

        <?php endif; ?>

    </div>

</section>


<!-- =================================================
     MY CLASSES
     ================================================= -->

<section class="teacher-dashboard dashboard-card">

    <div class="dashboard-card-header">

        <div>

            <h2>
                My Classes
            </h2>

            <p>
                Classes assigned to you
            </p>

        </div>

        <a
            href="/student-attendance-system/teacher/my-classes.php"
            class="teacher-view-link"
        >
            View All

            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M5 12h14"></path>
                <path d="M13 6l6 6-6 6"></path>
            </svg>

        </a>

    </div>


    <?php if (!empty($classes)): ?>

        <div class="teacher-class-grid">

            <?php foreach (array_slice($classes, 0, 3) as $class): ?>

                <?php

                $classId = (int) $class['id'];

                $classSubjectsList =
                    $subjectsByClass[$classId] ?? [];

                ?>

                <div class="teacher-class-panel">


                    <div class="teacher-class-panel-header">

                        <div>

                            <div class="teacher-class-icon">

                                <svg
                                    width="20"
                                    height="20"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M3 21h18"></path>
                                    <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                                    <path d="M9 7h1"></path>
                                    <path d="M14 7h1"></path>
                                    <path d="M9 11h1"></path>
                                    <path d="M14 11h1"></path>
                                    <path d="M9 15h1"></path>
                                    <path d="M14 15h1"></path>
                                </svg>

                            </div>

                        </div>

                        <div>

                            <h3>
                                <?= e($class['name']) ?>
                            </h3>

                            <p>
                                <?= e($class['code']) ?>
                            </p>

                        </div>

                    </div>


                    <div class="teacher-class-meta">

                        <span>

                            <svg
                                width="12"
                                height="12"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                            </svg>

                            <?= number_format((int) $class['student_count']) ?>
                            Students

                        </span>


                        <span>

                            <svg
                                width="12"
                                height="12"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                            </svg>

                            <?= number_format((int) $class['subject_count']) ?>
                            Subjects

                        </span>

                    </div>


                    <?php if (!empty($classSubjectsList)): ?>

                        <div class="teacher-subject-list">

                            <strong>
                                Subjects
                            </strong>

                            <div class="teacher-subject-tags">

                                <?php foreach ($classSubjectsList as $subject): ?>

                                    <span class="teacher-subject-tag">

                                        <?= e($subject['name']) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        </div>

                    <?php endif; ?>


                    <div class="teacher-class-panel-actions">

                        <a
                            href="/student-attendance-system/teacher/my-students.php?class_id=<?= $classId ?>"
                            class="teacher-class-action"
                        >
                            Students
                        </a>

                        <a
                            href="/student-attendance-system/teacher/attendance.php?class_id=<?= $classId ?>"
                            class="teacher-class-action teacher-class-action-primary"
                        >
                            Attendance
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="teacher-empty-state">

            <div class="teacher-empty-icon">

                <svg
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M3 21h18"></path>
                    <path d="M5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16"></path>
                </svg>

            </div>

            <h3>
                No classes assigned
            </h3>

            <p>
                You currently do not have any classes
                assigned to your teacher account.
            </p>

        </div>

    <?php endif; ?>

</section>


<!-- =================================================
     RECENT ATTENDANCE
     ================================================= -->

<section class="teacher-dashboard dashboard-card recent-attendance">

    <div class="dashboard-card-header">

        <div>

            <h2>
                Recent Attendance
            </h2>

            <p>
                Your latest attendance records
            </p>

        </div>

        <a
            href="/student-attendance-system/teacher/attendance-history.php"
            class="teacher-view-link"
        >
            View History

            <svg
                width="14"
                height="14"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="M5 12h14"></path>
                <path d="M13 6l6 6-6 6"></path>
            </svg>

        </a>

    </div>


    <?php if (!empty($recentAttendance)): ?>

        <div class="teacher-table-wrapper">

            <table class="teacher-table">

                <thead>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Student
                        </th>

                        <th>
                            Class
                        </th>

                        <th>
                            Subject
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($recentAttendance as $record): ?>

                        <?php

                        $studentName =
                            $record['student_name'] ?? 'Student';

                        $studentParts =
                            preg_split(
                                '/\s+/',
                                trim($studentName)
                            );

                        $studentInitials = '';

                        if (!empty($studentParts[0])) {
                            $studentInitials .= strtoupper(
                                substr(
                                    $studentParts[0],
                                    0,
                                    1
                                )
                            );
                        }

                        if (
                            count($studentParts) > 1 &&
                            !empty(
                                $studentParts[
                                    count($studentParts) - 1
                                ]
                            )
                        ) {
                            $studentInitials .= strtoupper(
                                substr(
                                    $studentParts[
                                        count($studentParts) - 1
                                    ],
                                    0,
                                    1
                                )
                            );
                        }

                        if ($studentInitials === '') {
                            $studentInitials = 'S';
                        }

                        ?>

                        <tr>

                            <td class="teacher-table-date">

                                <?= e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $record['attendance_date']
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <div class="teacher-student-cell">

                                    <?php if (!empty($record['photo'])): ?>

                                        <img
                                            src="<?= e($record['photo']) ?>"
                                            alt="<?= e($studentName) ?>"
                                            class="teacher-student-photo"
                                        >

                                    <?php else: ?>

                                        <div class="teacher-student-avatar">

                                            <?= e($studentInitials) ?>

                                        </div>

                                    <?php endif; ?>


                                    <div>

                                        <strong>
                                            <?= e($studentName) ?>
                                        </strong>

                                        <?php if (!empty($record['student_reference'])): ?>

                                            <span>
                                                <?= e(
                                                    $record['student_reference']
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            </td>


                            <td>

                                <?= e(
                                    $record['class_name']
                                ) ?>

                                <?php if (!empty($record['class_code'])): ?>

                                    <div>
                                        <small>
                                            <?= e(
                                                $record['class_code']
                                            ) ?>
                                        </small>
                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= e(
                                    $record['subject_name']
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="teacher-status-badge <?= e(
                                        teacherStatusClass(
                                            $record['status']
                                        )
                                    ) ?>"
                                >

                                    <?php
                                    $status = strtoupper(
                                        $record['status']
                                    );
                                    ?>

                                    <?php if ($status === 'PRESENT'): ?>

                                        <svg
                                            width="12"
                                            height="12"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path d="M20 6L9 17l-5-5"></path>
                                        </svg>

                                    <?php elseif ($status === 'ABSENT'): ?>

                                        <svg
                                            width="12"
                                            height="12"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path d="M18 6L6 18"></path>
                                            <path d="M6 6l12 12"></path>
                                        </svg>

                                    <?php else: ?>

                                        <svg
                                            width="12"
                                            height="12"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="9"
                                            ></circle>
                                            <path d="M12 7v5"></path>
                                            <path d="M12 16h.01"></path>
                                        </svg>

                                    <?php endif; ?>


                                    <?= e(
                                        teacherStatusLabel(
                                            $record['status']
                                        )
                                    ) ?>

                                </span>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <div class="teacher-empty-state">

            <div class="teacher-empty-icon">

                <svg
                    width="24"
                    height="24"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M4 4h16v16H4z"></path>
                    <path d="M8 9h8"></path>
                    <path d="M8 13h5"></path>
                </svg>

            </div>

            <h3>
                No attendance records yet
            </h3>

            <p>
                Attendance records submitted by you
                will appear here.
            </p>

        </div>

    <?php endif; ?>

</section>


<!-- =================================================
     QUICK ACTIONS
     ================================================= -->

<section class="teacher-dashboard quick-actions">

    <div class="dashboard-card-header">

        <div>

            <h2>
                Quick Actions
            </h2>

            <p>
                Frequently used teacher tools
            </p>

        </div>

    </div>


    <div class="quick-actions-grid">


        <!-- Take Attendance -->

        <a
            href="/student-attendance-system/teacher/attendance.php"
            class="quick-action-card"
        >

            <div class="quick-action-icon quick-action-icon-green">

                <svg
                    width="19"
                    height="19"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M9 11l3 3L22 4"></path>
                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                </svg>

            </div>

            <div class="quick-action-content">

                <strong>
                    Take Attendance
                </strong>

                <span>
                    Mark today's attendance
                </span>

            </div>

            <div class="quick-action-arrow">
                →
            </div>

        </a>


        <!-- My Students -->

        <a
            href="/student-attendance-system/teacher/my-students.php"
            class="quick-action-card"
        >

            <div class="quick-action-icon quick-action-icon-blue">

                <svg
                    width="19"
                    height="19"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>

            </div>

            <div class="quick-action-content">

                <strong>
                    My Students
                </strong>

                <span>
                    View assigned students
                </span>

            </div>

            <div class="quick-action-arrow">
                →
            </div>

        </a>


        <!-- Attendance History -->

        <a
            href="/student-attendance-system/teacher/attendance-history.php"
            class="quick-action-card"
        >

            <div class="quick-action-icon quick-action-icon-orange">

                <svg
                    width="19"
                    height="19"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M3 12a9 9 0 1 0 3-6.7"></path>
                    <path d="M3 4v5h5"></path>
                    <path d="M12 7v5l3 2"></path>
                </svg>

            </div>

            <div class="quick-action-content">

                <strong>
                    Attendance History
                </strong>

                <span>
                    Review previous attendance
                </span>

            </div>

            <div class="quick-action-arrow">
                →
            </div>

        </a>


        <!-- Reports -->

        <a
            href="/student-attendance-system/teacher/reports.php"
            class="quick-action-card"
        >

            <div class="quick-action-icon quick-action-icon-purple">

                <svg
                    width="19"
                    height="19"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M3 3v18h18"></path>
                    <path d="M7 16l4-5 3 3 5-7"></path>
                </svg>

            </div>

            <div class="quick-action-content">

                <strong>
                    Reports
                </strong>

                <span>
                    Analyse attendance performance
                </span>

            </div>

            <div class="quick-action-arrow">
                →
            </div>

        </a>

    </div>

</section>
```

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
