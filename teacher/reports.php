<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('TEACHER');

$teacherId = (int) currentUserId();

$error = '';

/*
|--------------------------------------------------------------------------
| Get Teacher Profile
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        t.id,
        u.name
    FROM teachers t
    INNER JOIN users u ON u.id = t.user_id
    WHERE t.user_id = ?
    LIMIT 1
");

$stmt->execute([$teacherId]);

$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher profile not found.');
}

$teacherProfileId = (int) $teacher['id'];
$teacherName = $teacher['name'];


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$classId = isset($_GET['class_id'])
    ? (int) $_GET['class_id']
    : 0;

$subjectId = isset($_GET['subject_id'])
    ? (int) $_GET['subject_id']
    : 0;

$studentId = isset($_GET['student_id'])
    ? (int) $_GET['student_id']
    : 0;

$dateFrom = trim($_GET['date_from'] ?? '');

$dateTo = trim($_GET['date_to'] ?? '');


/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $dateFrom
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $dateFrom
    ) {
        $dateFrom = '';
    }
}

if ($dateTo !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $dateTo
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $dateTo
    ) {
        $dateTo = '';
    }
}


/*
|--------------------------------------------------------------------------
| Teacher Classes
|--------------------------------------------------------------------------
*/

$classStmt = $pdo->prepare("
    SELECT DISTINCT
        c.id,
        c.name,
        c.code,
        c.academic_year
    FROM teaching_assignments ta
    INNER JOIN classes c
        ON c.id = ta.class_id
    WHERE ta.teacher_id = ?
    ORDER BY c.name ASC
");

$classStmt->execute([$teacherProfileId]);

$teacherClasses = $classStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Teacher Subjects
|--------------------------------------------------------------------------
*/

$subjectStmt = $pdo->prepare("
    SELECT DISTINCT
        s.id,
        s.name,
        s.code
    FROM teaching_assignments ta
    INNER JOIN subjects s
        ON s.id = ta.subject_id
    WHERE ta.teacher_id = ?
    ORDER BY s.name ASC
");

$subjectStmt->execute([$teacherProfileId]);

$teacherSubjects = $subjectStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Teacher Students
|--------------------------------------------------------------------------
*/

$studentStmt = $pdo->prepare("
    SELECT DISTINCT
        s.id,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,
        c.name AS class_name
    FROM students s

    INNER JOIN teaching_assignments ta
        ON ta.class_id = s.class_id

    INNER JOIN classes c
        ON c.id = s.class_id

    WHERE ta.teacher_id = ?
      AND s.status = 'ACTIVE'

    ORDER BY
        s.first_name ASC,
        s.last_name ASC
");

$studentStmt->execute([$teacherProfileId]);

$teacherStudents = $studentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Validate Selected Filters Against Teacher Assignments
|--------------------------------------------------------------------------
*/

if ($classId > 0) {

    $classExists = false;

    foreach ($teacherClasses as $class) {

        if ((int) $class['id'] === $classId) {
            $classExists = true;
            break;
        }
    }

    if (!$classExists) {
        $classId = 0;
    }
}


if ($subjectId > 0) {

    $subjectExists = false;

    foreach ($teacherSubjects as $subject) {

        if ((int) $subject['id'] === $subjectId) {
            $subjectExists = true;
            break;
        }
    }

    if (!$subjectExists) {
        $subjectId = 0;
    }
}


if ($studentId > 0) {

    $studentExists = false;

    foreach ($teacherStudents as $student) {

        if ((int) $student['id'] === $studentId) {
            $studentExists = true;
            break;
        }
    }

    if (!$studentExists) {
        $studentId = 0;
    }
}


/*
|--------------------------------------------------------------------------
| Base Conditions
|--------------------------------------------------------------------------
*/

$where = [
    "a.teacher_id = ?"
];

$params = [
    $teacherProfileId
];


/*
|--------------------------------------------------------------------------
| Class Filter
|--------------------------------------------------------------------------
*/

if ($classId > 0) {

    $where[] = "a.class_id = ?";
    $params[] = $classId;
}


/*
|--------------------------------------------------------------------------
| Subject Filter
|--------------------------------------------------------------------------
*/

if ($subjectId > 0) {

    $where[] = "a.subject_id = ?";
    $params[] = $subjectId;
}


/*
|--------------------------------------------------------------------------
| Student Filter
|--------------------------------------------------------------------------
*/

if ($studentId > 0) {

    $where[] = "a.student_id = ?";
    $params[] = $studentId;
}


/*
|--------------------------------------------------------------------------
| Date From
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $where[] = "a.attendance_date >= ?";
    $params[] = $dateFrom;
}


/*
|--------------------------------------------------------------------------
| Date To
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $where[] = "a.attendance_date <= ?";
    $params[] = $dateTo;
}


$whereSql = implode(
    ' AND ',
    $where
);


/*
|--------------------------------------------------------------------------
| Overall Summary
|--------------------------------------------------------------------------
*/

$summary = [
    'total' => 0,
    'present' => 0,
    'absent' => 0,
    'permission' => 0
];

try {

    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,

            SUM(
                CASE
                    WHEN a.status = 'PRESENT'
                    THEN 1
                    ELSE 0
                END
            ) AS present_count,

            SUM(
                CASE
                    WHEN a.status = 'ABSENT'
                    THEN 1
                    ELSE 0
                END
            ) AS absent_count,

            SUM(
                CASE
                    WHEN a.status = 'PERMISSION'
                    THEN 1
                    ELSE 0
                END
            ) AS permission_count

        FROM attendance a

        WHERE $whereSql
    ");

    $summaryStmt->execute($params);

    $summaryResult = $summaryStmt->fetch();

    if ($summaryResult) {

        $summary['total'] =
            (int) ($summaryResult['total'] ?? 0);

        $summary['present'] =
            (int) ($summaryResult['present_count'] ?? 0);

        $summary['absent'] =
            (int) ($summaryResult['absent_count'] ?? 0);

        $summary['permission'] =
            (int) ($summaryResult['permission_count'] ?? 0);
    }

} catch (PDOException $e) {

    $error = 'Unable to generate attendance summary.';
}


/*
|--------------------------------------------------------------------------
| Attendance Rate
|--------------------------------------------------------------------------
*/

$attendanceRate = 0;

if ($summary['total'] > 0) {

    $attendanceRate = round(
        ($summary['present'] / $summary['total']) * 100,
        1
    );
}


/*
|--------------------------------------------------------------------------
| Class Summary
|--------------------------------------------------------------------------
*/

$classSummary = [];

try {

    $classSummaryStmt = $pdo->prepare("
        SELECT

            c.id,
            c.name AS class_name,
            c.code AS class_code,

            COUNT(a.id) AS total_records,

            SUM(
                CASE
                    WHEN a.status = 'PRESENT'
                    THEN 1
                    ELSE 0
                END
            ) AS present_count,

            SUM(
                CASE
                    WHEN a.status = 'ABSENT'
                    THEN 1
                    ELSE 0
                END
            ) AS absent_count,

            SUM(
                CASE
                    WHEN a.status = 'PERMISSION'
                    THEN 1
                    ELSE 0
                END
            ) AS permission_count

        FROM attendance a

        INNER JOIN classes c
            ON c.id = a.class_id

        WHERE $whereSql

        GROUP BY
            c.id,
            c.name,
            c.code

        ORDER BY
            c.name ASC
    ");

    $classSummaryStmt->execute($params);

    $classSummary = $classSummaryStmt->fetchAll();

} catch (PDOException $e) {

    $classSummary = [];
}


/*
|--------------------------------------------------------------------------
| Subject Summary
|--------------------------------------------------------------------------
*/

$subjectSummary = [];

try {

    $subjectSummaryStmt = $pdo->prepare("
        SELECT

            s.id,
            s.name AS subject_name,
            s.code AS subject_code,

            COUNT(a.id) AS total_records,

            SUM(
                CASE
                    WHEN a.status = 'PRESENT'
                    THEN 1
                    ELSE 0
                END
            ) AS present_count,

            SUM(
                CASE
                    WHEN a.status = 'ABSENT'
                    THEN 1
                    ELSE 0
                END
            ) AS absent_count,

            SUM(
                CASE
                    WHEN a.status = 'PERMISSION'
                    THEN 1
                    ELSE 0
                END
            ) AS permission_count

        FROM attendance a

        INNER JOIN subjects s
            ON s.id = a.subject_id

        WHERE $whereSql

        GROUP BY
            s.id,
            s.name,
            s.code

        ORDER BY
            s.name ASC
    ");

    $subjectSummaryStmt->execute($params);

    $subjectSummary = $subjectSummaryStmt->fetchAll();

} catch (PDOException $e) {

    $subjectSummary = [];
}


/*
|--------------------------------------------------------------------------
| Student Summary
|--------------------------------------------------------------------------
*/

$studentSummary = [];

try {

    $studentSummaryStmt = $pdo->prepare("
        SELECT

            st.id,

            st.admission_number,
            st.first_name,
            st.middle_name,
            st.last_name,

            c.name AS class_name,

            COUNT(a.id) AS total_records,

            SUM(
                CASE
                    WHEN a.status = 'PRESENT'
                    THEN 1
                    ELSE 0
                END
            ) AS present_count,

            SUM(
                CASE
                    WHEN a.status = 'ABSENT'
                    THEN 1
                    ELSE 0
                END
            ) AS absent_count,

            SUM(
                CASE
                    WHEN a.status = 'PERMISSION'
                    THEN 1
                    ELSE 0
                END
            ) AS permission_count

        FROM attendance a

        INNER JOIN students st
            ON st.id = a.student_id

        INNER JOIN classes c
            ON c.id = a.class_id

        WHERE $whereSql

        GROUP BY

            st.id,
            st.admission_number,
            st.first_name,
            st.middle_name,
            st.last_name,
            c.name

        ORDER BY
            st.first_name ASC,
            st.last_name ASC
    ");

    $studentSummaryStmt->execute($params);

    $studentSummary = $studentSummaryStmt->fetchAll();

} catch (PDOException $e) {

    $studentSummary = [];
}


/*
|--------------------------------------------------------------------------
| Detailed Records
|--------------------------------------------------------------------------
*/

$detailedRecords = [];

try {

    $detailStmt = $pdo->prepare("
        SELECT

            a.id,
            a.attendance_date,
            a.status,
            a.permission_reason,

            st.admission_number,
            st.first_name,
            st.middle_name,
            st.last_name,

            c.name AS class_name,
            c.code AS class_code,

            s.name AS subject_name,
            s.code AS subject_code

        FROM attendance a

        INNER JOIN students st
            ON st.id = a.student_id

        INNER JOIN classes c
            ON c.id = a.class_id

        INNER JOIN subjects s
            ON s.id = a.subject_id

        WHERE $whereSql

        ORDER BY
            a.attendance_date DESC,
            st.first_name ASC,
            st.last_name ASC

        LIMIT 500
    ");

    $detailStmt->execute($params);

    $detailedRecords = $detailStmt->fetchAll();

} catch (PDOException $e) {

    $detailedRecords = [];
}


/*
|--------------------------------------------------------------------------
| Helper For Percentage
|--------------------------------------------------------------------------
*/

function teacherReportPercentage(
    int $part,
    int $total
): float {

    if ($total <= 0) {
        return 0;
    }

    return round(
        ($part / $total) * 100,
        1
    );
}


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Attendance Reports';

$pageType = 'teacher';

$additionalStyles = [
    '/student-attendance-system/assets/css/teacher-reports.css'
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">

        <!-- =====================================================
             PAGE HEADER
             ===================================================== -->

        <div class="page-header">

            <div>

                <span class="page-eyebrow">
                    TEACHER PORTAL
                </span>

                <h1>
                    Attendance Reports
                </h1>

                <p>
                    Analyse attendance performance for your assigned classes and subjects.
                </p>

            </div>

        </div>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <!-- =====================================================
             FILTERS
             ===================================================== -->

        <section class="teacher-report-filter-card">

            <div class="teacher-report-filter-header">

                <div>

                    <h2>
                        Report Filters
                    </h2>

                    <p>
                        Select the information you want to analyse.
                    </p>

                </div>

                <a
                    href="/student-attendance-system/teacher/reports.php"
                    class="teacher-report-reset"
                >
                    Reset
                </a>

            </div>


            <form
                method="GET"
                class="teacher-report-filter-form"
            >

                <div class="form-group">

                    <label for="class_id">
                        Class
                    </label>

                    <select
                        name="class_id"
                        id="class_id"
                    >

                        <option value="">
                            All Classes
                        </option>

                        <?php foreach ($teacherClasses as $class): ?>

                            <option
                                value="<?= (int) $class['id'] ?>"
                                <?= $classId === (int) $class['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($class['name']) ?>
                                — <?= e($class['code']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="subject_id">
                        Subject
                    </label>

                    <select
                        name="subject_id"
                        id="subject_id"
                    >

                        <option value="">
                            All Subjects
                        </option>

                        <?php foreach ($teacherSubjects as $subject): ?>

                            <option
                                value="<?= (int) $subject['id'] ?>"
                                <?= $subjectId === (int) $subject['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e($subject['name']) ?>
                                — <?= e($subject['code']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="student_id">
                        Student
                    </label>

                    <select
                        name="student_id"
                        id="student_id"
                    >

                        <option value="">
                            All Students
                        </option>

                        <?php foreach ($teacherStudents as $student): ?>

                            <option
                                value="<?= (int) $student['id'] ?>"
                                <?= $studentId === (int) $student['id']
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= e(
                                    trim(
                                        $student['first_name']
                                        . ' '
                                        . ($student['middle_name'] ?? '')
                                        . ' '
                                        . $student['last_name']
                                    )
                                ) ?>

                                — <?= e($student['admission_number']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="date_from">
                        From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        id="date_from"
                        value="<?= e($dateFrom) ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="date_to">
                        To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        id="date_to"
                        value="<?= e($dateTo) ?>"
                    >

                </div>


                <div class="teacher-report-filter-button">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Generate Report
                    </button>

                </div>

            </form>

        </section>


        <!-- =====================================================
             OVERALL SUMMARY
             ===================================================== -->

        <section class="teacher-report-stats">

            <div class="teacher-report-stat-card">

                <div class="teacher-report-stat-icon">
                    #
                </div>

                <div>

                    <span>Total Records</span>

                    <strong>
                        <?= $summary['total'] ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-report-stat-card present">

                <div class="teacher-report-stat-icon">
                    ✓
                </div>

                <div>

                    <span>Present</span>

                    <strong>
                        <?= $summary['present'] ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-report-stat-card absent">

                <div class="teacher-report-stat-icon">
                    ×
                </div>

                <div>

                    <span>Absent</span>

                    <strong>
                        <?= $summary['absent'] ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-report-stat-card permission">

                <div class="teacher-report-stat-icon">
                    !
                </div>

                <div>

                    <span>Permission</span>

                    <strong>
                        <?= $summary['permission'] ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-report-stat-card rate">

                <div class="teacher-report-stat-icon">
                    %
                </div>

                <div>

                    <span>Attendance Rate</span>

                    <strong>
                        <?= e((string) $attendanceRate) ?>%
                    </strong>

                </div>

            </div>

        </section>


        <!-- =====================================================
             PERFORMANCE OVERVIEW
             ===================================================== -->

        <section class="teacher-report-overview-card">

            <div class="teacher-report-overview-header">

                <div>

                    <span class="page-eyebrow">
                        PERFORMANCE
                    </span>

                    <h2>
                        Attendance Overview
                    </h2>

                </div>

                <div class="teacher-report-rate-circle">

                    <strong>
                        <?= e((string) $attendanceRate) ?>%
                    </strong>

                    <span>
                        Present
                    </span>

                </div>

            </div>


            <div class="teacher-report-progress-area">

                <div class="teacher-report-progress-row">

                    <div class="teacher-report-progress-label">

                        <span>
                            Present
                        </span>

                        <strong>
                            <?= teacherReportPercentage(
                                $summary['present'],
                                $summary['total']
                            ) ?>%
                        </strong>

                    </div>

                    <div class="teacher-report-progress">

                        <span
                            class="present-progress"
                            style="width: <?= teacherReportPercentage(
                                $summary['present'],
                                $summary['total']
                            ) ?>%;"
                        ></span>

                    </div>

                </div>


                <div class="teacher-report-progress-row">

                    <div class="teacher-report-progress-label">

                        <span>
                            Absent
                        </span>

                        <strong>
                            <?= teacherReportPercentage(
                                $summary['absent'],
                                $summary['total']
                            ) ?>%
                        </strong>

                    </div>

                    <div class="teacher-report-progress">

                        <span
                            class="absent-progress"
                            style="width: <?= teacherReportPercentage(
                                $summary['absent'],
                                $summary['total']
                            ) ?>%;"
                        ></span>

                    </div>

                </div>


                <div class="teacher-report-progress-row">

                    <div class="teacher-report-progress-label">

                        <span>
                            Permission
                        </span>

                        <strong>
                            <?= teacherReportPercentage(
                                $summary['permission'],
                                $summary['total']
                            ) ?>%
                        </strong>

                    </div>

                    <div class="teacher-report-progress">

                        <span
                            class="permission-progress"
                            style="width: <?= teacherReportPercentage(
                                $summary['permission'],
                                $summary['total']
                            ) ?>%;"
                        ></span>

                    </div>

                </div>

            </div>

        </section>


        <!-- =====================================================
             CLASS SUMMARY
             ===================================================== -->

        <section class="teacher-report-section">

            <div class="teacher-report-section-header">

                <div>

                    <span class="page-eyebrow">
                        BY CLASS
                    </span>

                    <h2>
                        Class Performance
                    </h2>

                </div>

            </div>


            <?php if (!empty($classSummary)): ?>

                <div class="teacher-report-table-wrapper">

                    <table class="teacher-report-table">

                        <thead>

                            <tr>

                                <th>
                                    Class
                                </th>

                                <th>
                                    Records
                                </th>

                                <th>
                                    Present
                                </th>

                                <th>
                                    Absent
                                </th>

                                <th>
                                    Permission
                                </th>

                                <th>
                                    Rate
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($classSummary as $row): ?>

                                <?php

                                $total = (int) $row['total_records'];

                                $present = (int) $row['present_count'];

                                $rate = teacherReportPercentage(
                                    $present,
                                    $total
                                );

                                ?>

                                <tr>

                                    <td>

                                        <div class="teacher-report-name-cell">

                                            <strong>
                                                <?= e($row['class_name']) ?>
                                            </strong>

                                            <span>
                                                <?= e($row['class_code']) ?>
                                            </span>

                                        </div>

                                    </td>

                                    <td>
                                        <?= $total ?>
                                    </td>

                                    <td>

                                        <span class="report-number present">
                                            <?= $present ?>
                                        </span>

                                    </td>

                                    <td>

                                        <span class="report-number absent">
                                            <?= (int) $row['absent_count'] ?>
                                        </span>

                                    </td>

                                    <td>

                                        <span class="report-number permission">
                                            <?= (int) $row['permission_count'] ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="teacher-report-rate-cell">

                                            <strong>
                                                <?= $rate ?>%
                                            </strong>

                                            <div class="teacher-mini-progress">

                                                <span
                                                    style="width: <?= $rate ?>%;"
                                                ></span>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="teacher-report-empty-small">
                    No class data available for the selected filters.
                </div>

            <?php endif; ?>

        </section>


        <!-- =====================================================
             SUBJECT SUMMARY
             ===================================================== -->

        <section class="teacher-report-section">

            <div class="teacher-report-section-header">

                <div>

                    <span class="page-eyebrow">
                        BY SUBJECT
                    </span>

                    <h2>
                        Subject Performance
                    </h2>

                </div>

            </div>


            <?php if (!empty($subjectSummary)): ?>

                <div class="teacher-report-table-wrapper">

                    <table class="teacher-report-table">

                        <thead>

                            <tr>

                                <th>
                                    Subject
                                </th>

                                <th>
                                    Records
                                </th>

                                <th>
                                    Present
                                </th>

                                <th>
                                    Absent
                                </th>

                                <th>
                                    Permission
                                </th>

                                <th>
                                    Rate
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($subjectSummary as $row): ?>

                                <?php

                                $total = (int) $row['total_records'];

                                $present = (int) $row['present_count'];

                                $rate = teacherReportPercentage(
                                    $present,
                                    $total
                                );

                                ?>

                                <tr>

                                    <td>

                                        <div class="teacher-report-name-cell">

                                            <strong>
                                                <?= e($row['subject_name']) ?>
                                            </strong>

                                            <span>
                                                <?= e($row['subject_code']) ?>
                                            </span>

                                        </div>

                                    </td>

                                    <td>
                                        <?= $total ?>
                                    </td>

                                    <td>

                                        <span class="report-number present">
                                            <?= $present ?>
                                        </span>

                                    </td>

                                    <td>

                                        <span class="report-number absent">
                                            <?= (int) $row['absent_count'] ?>
                                        </span>

                                    </td>

                                    <td>

                                        <span class="report-number permission">
                                            <?= (int) $row['permission_count'] ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="teacher-report-rate-cell">

                                            <strong>
                                                <?= $rate ?>%
                                            </strong>

                                            <div class="teacher-mini-progress">

                                                <span
                                                    style="width: <?= $rate ?>%;"
                                                ></span>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="teacher-report-empty-small">
                    No subject data available for the selected filters.
                </div>

            <?php endif; ?>

        </section>


        <!-- =====================================================
             STUDENT SUMMARY
             ===================================================== -->

        <section class="teacher-report-section">

            <div class="teacher-report-section-header">

                <div>

                    <span class="page-eyebrow">
                        BY STUDENT
                    </span>

                    <h2>
                        Student Attendance Performance
                    </h2>

                </div>

            </div>


            <?php if (!empty($studentSummary)): ?>

                <div class="teacher-report-table-wrapper">

                    <table class="teacher-report-table">

                        <thead>

                            <tr>

                                <th>
                                    Student
                                </th>

                                <th>
                                    Class
                                </th>

                                <th>
                                    Records
                                </th>

                                <th>
                                    Present
                                </th>

                                <th>
                                    Absent
                                </th>

                                <th>
                                    Permission
                                </th>

                                <th>
                                    Rate
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($studentSummary as $row): ?>

                                <?php

                                $total = (int) $row['total_records'];

                                $present = (int) $row['present_count'];

                                $rate = teacherReportPercentage(
                                    $present,
                                    $total
                                );

                                ?>

                                <tr>

                                    <td>

                                        <div class="teacher-report-student-cell">

                                            <div class="teacher-report-student-avatar">

                                                <?= strtoupper(
                                                    substr(
                                                        $row['first_name'],
                                                        0,
                                                        1
                                                    )
                                                ) ?>

                                            </div>

                                            <div>

                                                <strong>

                                                    <?= e(
                                                        trim(
                                                            $row['first_name']
                                                            . ' '
                                                            . ($row['middle_name'] ?? '')
                                                            . ' '
                                                            . $row['last_name']
                                                        )
                                                    ) ?>

                                                </strong>

                                                <span>
                                                    <?= e($row['admission_number']) ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <td>
                                        <?= e($row['class_name']) ?>
                                    </td>


                                    <td>
                                        <?= $total ?>
                                    </td>


                                    <td>

                                        <span class="report-number present">
                                            <?= $present ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span class="report-number absent">
                                            <?= (int) $row['absent_count'] ?>
                                        </span>

                                    </td>


                                    <td>

                                        <span class="report-number permission">
                                            <?= (int) $row['permission_count'] ?>
                                        </span>

                                    </td>


                                    <td>

                                        <div class="teacher-report-rate-cell">

                                            <strong>
                                                <?= $rate ?>%
                                            </strong>

                                            <div class="teacher-mini-progress">

                                                <span
                                                    style="width: <?= $rate ?>%;"
                                                ></span>

                                            </div>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="teacher-report-empty-small">
                    No student data available for the selected filters.
                </div>

            <?php endif; ?>

        </section>


        <!-- =====================================================
             DETAILED RECORDS
             ===================================================== -->

        <section class="teacher-report-section">

            <div class="teacher-report-section-header">

                <div>

                    <span class="page-eyebrow">
                        DETAILED RECORDS
                    </span>

                    <h2>
                        Attendance Details
                    </h2>

                    <p>
                        Showing up to 500 records.
                    </p>

                </div>

            </div>


            <?php if (!empty($detailedRecords)): ?>

                <div class="teacher-report-table-wrapper">

                    <table class="teacher-report-table">

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

                                <th>
                                    Reason
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($detailedRecords as $record): ?>

                                <tr>

                                    <td>

                                        <div class="teacher-report-date">

                                            <strong>
                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $record['attendance_date']
                                                        )
                                                    )
                                                ) ?>
                                            </strong>

                                        </div>

                                    </td>


                                    <td>

                                        <div class="teacher-report-detail-student">

                                            <strong>

                                                <?= e(
                                                    trim(
                                                        $record['first_name']
                                                        . ' '
                                                        . ($record['middle_name'] ?? '')
                                                        . ' '
                                                        . $record['last_name']
                                                    )
                                                ) ?>

                                            </strong>

                                            <span>
                                                <?= e($record['admission_number']) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>

                                        <div class="teacher-report-name-cell">

                                            <strong>
                                                <?= e($record['class_name']) ?>
                                            </strong>

                                            <span>
                                                <?= e($record['class_code']) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>

                                        <div class="teacher-report-name-cell">

                                            <strong>
                                                <?= e($record['subject_name']) ?>
                                            </strong>

                                            <span>
                                                <?= e($record['subject_code']) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <td>

                                        <?php if (
                                            $record['status'] === 'PRESENT'
                                        ): ?>

                                            <span class="teacher-report-status present">
                                                Present
                                            </span>

                                        <?php elseif (
                                            $record['status'] === 'ABSENT'
                                        ): ?>

                                            <span class="teacher-report-status absent">
                                                Absent
                                            </span>

                                        <?php else: ?>

                                            <span class="teacher-report-status permission">
                                                Permission
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            $record['status'] === 'PERMISSION'
                                        ): ?>

                                            <span
                                                class="teacher-report-reason"
                                                title="<?= e(
                                                    $record['permission_reason']
                                                ) ?>"
                                            >
                                                <?= e(
                                                    $record['permission_reason']
                                                ) ?>
                                            </span>

                                        <?php else: ?>

                                            <span class="teacher-report-no-reason">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="teacher-report-empty">

                    <div class="teacher-report-empty-icon">
                        %
                    </div>

                    <h3>
                        No Attendance Data
                    </h3>

                    <p>
                        No records match the selected report filters.
                    </p>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>