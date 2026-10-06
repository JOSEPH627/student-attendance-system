<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Attendance Reports';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css',
    '/student-attendance-system/assets/css/forms.css'
];

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$studentSearch = trim($_GET['student_search'] ?? '');

$classId = filter_input(
    INPUT_GET,
    'class_id',
    FILTER_VALIDATE_INT
) ?: null;

$subjectId = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
) ?: null;

$teacherId = filter_input(
    INPUT_GET,
    'teacher_id',
    FILTER_VALIDATE_INT
) ?: null;


/*
|--------------------------------------------------------------------------
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        id,
        name,
        code
     FROM classes
     ORDER BY name ASC"
);

$classes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        id,
        name,
        code
     FROM subjects
     ORDER BY name ASC"
);

$subjects = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| LOAD TEACHERS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    "SELECT
        t.id,
        u.name,
        t.employee_number
     FROM teachers t

     INNER JOIN users u
        ON u.id = t.user_id

     ORDER BY u.name ASC"
);

$teachers = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| BUILD WHERE CONDITIONS
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| DATE FROM
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $dateFrom
    );

    if (
        $dateObject &&
        $dateObject->format('Y-m-d') === $dateFrom
    ) {

        $where[] = 'a.attendance_date >= ?';
        $params[] = $dateFrom;
    }
}


/*
|--------------------------------------------------------------------------
| DATE TO
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $dateTo
    );

    if (
        $dateObject &&
        $dateObject->format('Y-m-d') === $dateTo
    ) {

        $where[] = 'a.attendance_date <= ?';
        $params[] = $dateTo;
    }
}


/*
|--------------------------------------------------------------------------
| CLASS
|--------------------------------------------------------------------------
*/

if ($classId) {

    $where[] = 'a.class_id = ?';
    $params[] = $classId;
}


/*
|--------------------------------------------------------------------------
| SUBJECT
|--------------------------------------------------------------------------
*/

if ($subjectId) {

    $where[] = 'a.subject_id = ?';
    $params[] = $subjectId;
}


/*
|--------------------------------------------------------------------------
| TEACHER
|--------------------------------------------------------------------------
*/

if ($teacherId) {

    $where[] = 'a.teacher_id = ?';
    $params[] = $teacherId;
}


/*
|--------------------------------------------------------------------------
| STUDENT SEARCH
|--------------------------------------------------------------------------
*/

if ($studentSearch !== '') {

    $where[] = "(
        s.student_reference LIKE ?
        OR s.admission_number LIKE ?
        OR s.first_name LIKE ?
        OR s.middle_name LIKE ?
        OR s.last_name LIKE ?
    )";

    $searchValue = '%' . $studentSearch . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| WHERE SQL
|--------------------------------------------------------------------------
*/

$whereSql = '';

if (!empty($where)) {

    $whereSql = 'WHERE ' . implode(
        ' AND ',
        $where
    );
}


/*
|--------------------------------------------------------------------------
| BASE QUERY
|--------------------------------------------------------------------------
*/

$baseFrom = "
    FROM attendance a

    INNER JOIN students s
        ON s.id = a.student_id

    INNER JOIN classes c
        ON c.id = a.class_id

    INNER JOIN subjects sub
        ON sub.id = a.subject_id

    INNER JOIN teachers t
        ON t.id = a.teacher_id

    INNER JOIN users u
        ON u.id = t.user_id

    $whereSql
";


/*
|--------------------------------------------------------------------------
| OVERALL SUMMARY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT
        COUNT(*) AS total_records,

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

    $baseFrom
    "
);

$stmt->execute($params);

$summary = $stmt->fetch();

$totalRecords = (int) ($summary['total_records'] ?? 0);
$presentCount = (int) ($summary['present_count'] ?? 0);
$absentCount = (int) ($summary['absent_count'] ?? 0);
$permissionCount = (int) ($summary['permission_count'] ?? 0);

$attendanceRate = 0;

if ($totalRecords > 0) {

    $attendanceRate =
        ($presentCount / $totalRecords) * 100;
}


/*
|--------------------------------------------------------------------------
| CLASS REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT

        c.id,
        c.name,
        c.code,

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

    $baseFrom

    GROUP BY
        c.id,
        c.name,
        c.code

    ORDER BY
        c.name ASC
    "
);

$stmt->execute($params);

$classReports = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| STUDENT REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT

        s.id,
        s.student_reference,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,

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

    $baseFrom

    GROUP BY
        s.id,
        s.student_reference,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,
        c.name,
        c.code

    ORDER BY
        s.first_name ASC,
        s.last_name ASC
    "
);

$stmt->execute($params);

$studentReports = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| SUBJECT REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT

        sub.id,
        sub.name,
        sub.code,

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

    $baseFrom

    GROUP BY
        sub.id,
        sub.name,
        sub.code

    ORDER BY
        sub.name ASC
    "
);

$stmt->execute($params);

$subjectReports = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| TEACHER REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    "
    SELECT

        t.id,
        u.name AS teacher_name,
        t.employee_number,

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

    $baseFrom

    GROUP BY
        t.id,
        u.name,
        t.employee_number

    ORDER BY
        u.name ASC
    "
);

$stmt->execute($params);

$teacherReports = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PERMISSION REPORT
|--------------------------------------------------------------------------
*/

$permissionWhere = $where;

$permissionParams = $params;

$permissionWhere[] = "a.status = 'PERMISSION'";

$permissionWhereSql =
    'WHERE ' . implode(
        ' AND ',
        $permissionWhere
    );

$permissionSql = "
    SELECT

        a.attendance_date,
        a.permission_reason,

        s.student_reference,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,

        c.name AS class_name,

        sub.name AS subject_name,

        u.name AS teacher_name

    FROM attendance a

    INNER JOIN students s
        ON s.id = a.student_id

    INNER JOIN classes c
        ON c.id = a.class_id

    INNER JOIN subjects sub
        ON sub.id = a.subject_id

    INNER JOIN teachers t
        ON t.id = a.teacher_id

    INNER JOIN users u
        ON u.id = t.user_id

    $permissionWhereSql

    ORDER BY
        a.attendance_date DESC
";

$stmt = $pdo->prepare($permissionSql);
$stmt->execute($permissionParams);

$permissionReports = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


    <main class="dashboard-main">


        <!-- ==========================================================
             PAGE HEADER
        =========================================================== -->

        <div class="page-header">

            <div>

                <span class="page-eyebrow">
                    REPORTS
                </span>

                <h1>
                    Attendance Reports
                </h1>

                <p>
                    Analyze attendance records by student,
                    class, subject and teacher.
                </p>

            </div>


            <div class="page-header-actions">

                <a
                    href="/student-attendance-system/admin/attendance.php"
                    class="dashboard-secondary-button"
                >
                    Manage Attendance
                </a>

                <a
                    href="/student-attendance-system/admin/attendance-history.php"
                    class="dashboard-primary-button"
                >
                    Attendance History
                </a>

            </div>

        </div>


        <!-- ==========================================================
             FILTERS
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Report Filters
                    </h2>

                    <p>
                        Apply filters to update all report sections.
                    </p>

                </div>

            </div>


            <form
                method="GET"
                action="/student-attendance-system/admin/reports.php"
                class="dashboard-form"
            >

                <div class="form-grid">


                    <!-- DATE FROM -->

                    <div class="form-group">

                        <label for="date_from">
                            Date From
                        </label>

                        <input
                            type="date"
                            name="date_from"
                            id="date_from"
                            value="<?= e($dateFrom) ?>"
                        >

                    </div>


                    <!-- DATE TO -->

                    <div class="form-group">

                        <label for="date_to">
                            Date To
                        </label>

                        <input
                            type="date"
                            name="date_to"
                            id="date_to"
                            value="<?= e($dateTo) ?>"
                        >

                    </div>


                    <!-- STUDENT -->

                    <div class="form-group">

                        <label for="student_search">
                            Student
                        </label>

                        <input
                            type="text"
                            name="student_search"
                            id="student_search"
                            value="<?= e($studentSearch) ?>"
                            placeholder="Name, admission or reference"
                        >

                    </div>


                    <!-- CLASS -->

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

                            <?php foreach ($classes as $class): ?>

                                <option
                                    value="<?= (int) $class['id'] ?>"
                                    <?= (
                                        $classId === (int) $class['id']
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= e($class['name']) ?>
                                    (<?= e($class['code']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SUBJECT -->

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

                            <?php foreach ($subjects as $subject): ?>

                                <option
                                    value="<?= (int) $subject['id'] ?>"
                                    <?= (
                                        $subjectId === (int) $subject['id']
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= e($subject['name']) ?>
                                    (<?= e($subject['code']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- TEACHER -->

                    <div class="form-group">

                        <label for="teacher_id">
                            Teacher
                        </label>

                        <select
                            name="teacher_id"
                            id="teacher_id"
                        >

                            <option value="">
                                All Teachers
                            </option>

                            <?php foreach ($teachers as $teacher): ?>

                                <option
                                    value="<?= (int) $teacher['id'] ?>"
                                    <?= (
                                        $teacherId === (int) $teacher['id']
                                    )
                                        ? 'selected'
                                        : '' ?>
                                >

                                    <?= e($teacher['name']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="dashboard-primary-button"
                    >
                        Generate Report
                    </button>


                    <a
                        href="/student-attendance-system/admin/reports.php"
                        class="dashboard-secondary-button"
                    >
                        Clear Filters
                    </a>

                </div>

            </form>

        </section>


        <!-- ==========================================================
             OVERALL SUMMARY
        =========================================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">

                <div class="stat-card-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M8 6h13"></path>
                        <path d="M8 12h13"></path>
                        <path d="M8 18h13"></path>
                        <path d="M3 6h.01"></path>
                        <path d="M3 12h.01"></path>
                        <path d="M3 18h.01"></path>
                    </svg>

                </div>

                <div>

                    <span class="stat-card-label">
                        Total Records
                    </span>

                    <strong class="stat-card-value">
                        <?= number_format($totalRecords) ?>
                    </strong>

                </div>

            </div>


            <!-- PRESENT -->

            <div class="stat-card">

                <div class="stat-card-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M20 6L9 17l-5-5"></path>
                    </svg>

                </div>

                <div>

                    <span class="stat-card-label">
                        Present
                    </span>

                    <strong class="stat-card-value">
                        <?= number_format($presentCount) ?>
                    </strong>

                </div>

            </div>


            <!-- ABSENT -->

            <div class="stat-card">

                <div class="stat-card-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M18 6L6 18"></path>
                        <path d="M6 6l12 12"></path>
                    </svg>

                </div>

                <div>

                    <span class="stat-card-label">
                        Absent
                    </span>

                    <strong class="stat-card-value">
                        <?= number_format($absentCount) ?>
                    </strong>

                </div>

            </div>


            <!-- PERMISSION -->

            <div class="stat-card">

                <div class="stat-card-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <circle
                            cx="12"
                            cy="12"
                            r="9"
                        ></circle>

                        <path d="M12 7v5l3 2"></path>
                    </svg>

                </div>

                <div>

                    <span class="stat-card-label">
                        Permission
                    </span>

                    <strong class="stat-card-value">
                        <?= number_format($permissionCount) ?>
                    </strong>

                </div>

            </div>


        </section>


        <!-- ==========================================================
             ATTENDANCE RATE
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Overall Attendance Rate
                    </h2>

                    <p>
                        Percentage of attendance records marked
                        as present.
                    </p>

                </div>


                <strong
                    style="
                        font-size:28px;
                        color:#15803d;
                    "
                >
                    <?= number_format(
                        $attendanceRate,
                        1
                    ) ?>%
                </strong>

            </div>


            <div
                style="
                    width:100%;
                    height:12px;
                    background:#e2e8f0;
                    border-radius:999px;
                    overflow:hidden;
                "
            >

                <div
                    style="
                        width:<?= min(
                            100,
                            max(0, $attendanceRate)
                        ) ?>%;
                        height:100%;
                        background:#16a34a;
                        border-radius:999px;
                    "
                ></div>

            </div>

        </section>


        <!-- ==========================================================
             CLASS REPORT
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Class Attendance Summary
                    </h2>

                    <p>
                        Attendance statistics grouped by class.
                    </p>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Class</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Permission</th>
                            <th>Attendance Rate</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($classReports)): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="empty-table-message"
                                >
                                    No class report data available.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($classReports as $report): ?>

                                <?php

                                $classTotal =
                                    (int) $report['total_records'];

                                $classPresent =
                                    (int) $report['present_count'];

                                $classRate =
                                    $classTotal > 0
                                        ? (
                                            $classPresent /
                                            $classTotal
                                        ) * 100
                                        : 0;

                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e(
                                                $report['name']
                                            ) ?>
                                        </strong>

                                        <span class="table-secondary-text">
                                            <?= e(
                                                $report['code']
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= number_format(
                                            $classTotal
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $classPresent
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int) $report['absent_count']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int) $report['permission_count']
                                        ) ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= number_format(
                                                $classRate,
                                                1
                                            ) ?>%
                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- ==========================================================
             STUDENT REPORT
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Student Attendance Summary
                    </h2>

                    <p>
                        Attendance statistics for individual students.
                    </p>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Student</th>
                            <th>Class</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Permission</th>
                            <th>Attendance Rate</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($studentReports)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="empty-table-message"
                                >
                                    No student report data available.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($studentReports as $report): ?>

                                <?php

                                $studentTotal =
                                    (int) $report['total_records'];

                                $studentPresent =
                                    (int) $report['present_count'];

                                $studentRate =
                                    $studentTotal > 0
                                        ? (
                                            $studentPresent /
                                            $studentTotal
                                        ) * 100
                                        : 0;

                                ?>

                                <tr>

                                    <td>

                                        <div
                                            class="student-table-profile"
                                        >

                                            <div
                                                class="student-table-avatar"
                                            >

                                                <?= e(
                                                    strtoupper(
                                                        substr(
                                                            $report['first_name'],
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>

                                            </div>


                                            <div>

                                                <strong>

                                                    <?= e(
                                                        $report['first_name']
                                                        . ' '
                                                        . (
                                                            $report['middle_name']
                                                                ? $report['middle_name'] . ' '
                                                                : ''
                                                        )
                                                        . $report['last_name']
                                                    ) ?>

                                                </strong>

                                                <span
                                                    class="table-secondary-text"
                                                >
                                                    <?= e(
                                                        $report[
                                                            'admission_number'
                                                        ]
                                                    ) ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <?= e(
                                            $report['class_name']
                                        ) ?>

                                    </td>


                                    <td>
                                        <?= number_format(
                                            $studentTotal
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= number_format(
                                            $studentPresent
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= number_format(
                                            (int) $report[
                                                'absent_count'
                                            ]
                                        ) ?>
                                    </td>


                                    <td>
                                        <?= number_format(
                                            (int) $report[
                                                'permission_count'
                                            ]
                                        ) ?>
                                    </td>


                                    <td>

                                        <strong>
                                            <?= number_format(
                                                $studentRate,
                                                1
                                            ) ?>%
                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- ==========================================================
             SUBJECT REPORT
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Subject Attendance Summary
                    </h2>

                    <p>
                        Attendance statistics grouped by subject.
                    </p>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Subject</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Permission</th>
                            <th>Attendance Rate</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($subjectReports)): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="empty-table-message"
                                >
                                    No subject report data available.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($subjectReports as $report): ?>

                                <?php

                                $subjectTotal =
                                    (int) $report['total_records'];

                                $subjectPresent =
                                    (int) $report['present_count'];

                                $subjectRate =
                                    $subjectTotal > 0
                                        ? (
                                            $subjectPresent /
                                            $subjectTotal
                                        ) * 100
                                        : 0;

                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e(
                                                $report['name']
                                            ) ?>
                                        </strong>

                                        <span class="table-secondary-text">
                                            <?= e(
                                                $report['code']
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= number_format(
                                            $subjectTotal
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $subjectPresent
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int) $report[
                                                'absent_count'
                                            ]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int) $report[
                                                'permission_count'
                                            ]
                                        ) ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= number_format(
                                                $subjectRate,
                                                1
                                            ) ?>%
                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- ==========================================================
             TEACHER REPORT
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Teacher Attendance Summary
                    </h2>

                    <p>
                        Attendance records handled by each teacher.
                    </p>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Teacher</th>
                            <th>Total</th>
                            <th>Present</th>
                            <th>Absent</th>
                            <th>Permission</th>
                            <th>Attendance Rate</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($teacherReports)): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="empty-table-message"
                                >
                                    No teacher report data available.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($teacherReports as $report): ?>

                                <?php

                                $teacherTotal =
                                    (int) $report['total_records'];

                                $teacherPresent =
                                    (int) $report['present_count'];

                                $teacherRate =
                                    $teacherTotal > 0
                                        ? (
                                            $teacherPresent /
                                            $teacherTotal
                                        ) * 100
                                        : 0;

                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= e(
                                                $report[
                                                    'teacher_name'
                                                ]
                                            ) ?>
                                        </strong>

                                        <span
                                            class="table-secondary-text"
                                        >
                                            <?= e(
                                                $report[
                                                    'employee_number'
                                                ]
                                            ) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= number_format(
                                            $teacherTotal
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $teacherPresent
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int) $report[
                                                'absent_count'
                                            ]
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (int) $report[
                                                'permission_count'
                                            ]
                                        ) ?>
                                    </td>

                                    <td>

                                        <strong>
                                            <?= number_format(
                                                $teacherRate,
                                                1
                                            ) ?>%
                                        </strong>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- ==========================================================
             PERMISSION REPORT
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Permission Records
                    </h2>

                    <p>
                        Students who were marked as permission,
                        including the recorded reason.
                    </p>

                </div>

            </div>


            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Student</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Date</th>
                            <th>Reason</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($permissionReports)): ?>

                            <tr>

                                <td
                                    colspan="6"
                                    class="empty-table-message"
                                >
                                    No permission records found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach (
                                $permissionReports
                                as $record
                            ): ?>

                                <tr>


                                    <!-- STUDENT -->

                                    <td>

                                        <strong>

                                            <?= e(
                                                $record['first_name']
                                                . ' '
                                                . (
                                                    $record['middle_name']
                                                        ? $record['middle_name'] . ' '
                                                        : ''
                                                )
                                                . $record['last_name']
                                            ) ?>

                                        </strong>

                                        <span
                                            class="table-secondary-text"
                                        >

                                            <?= e(
                                                $record[
                                                    'admission_number'
                                                ]
                                            ) ?>

                                        </span>

                                    </td>


                                    <!-- CLASS -->

                                    <td>
                                        <?= e(
                                            $record['class_name']
                                        ) ?>
                                    </td>


                                    <!-- SUBJECT -->

                                    <td>
                                        <?= e(
                                            $record['subject_name']
                                        ) ?>
                                    </td>


                                    <!-- TEACHER -->

                                    <td>
                                        <?= e(
                                            $record['teacher_name']
                                        ) ?>
                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $record[
                                                        'attendance_date'
                                                    ]
                                                )
                                            )
                                        ) ?>

                                    </td>


                                    <!-- REASON -->

                                    <td>

                                        <div
                                            class="permission-reason"
                                            style="margin:0; max-width:none;"
                                        >

                                            <?= e(
                                                $record[
                                                    'permission_reason'
                                                ]
                                                ?: '-'
                                            ) ?>

                                        </div>

                                    </td>


                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


    </main>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
