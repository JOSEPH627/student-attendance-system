<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Attendance History';

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

$status = strtoupper(trim($_GET['status'] ?? ''));


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
| STATUS
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $status,
        ['PRESENT', 'ABSENT', 'PERMISSION'],
        true
    )
) {

    $where[] = 'a.status = ?';
    $params[] = $status;
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
| ATTENDANCE HISTORY QUERY
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        a.id,
        a.attendance_date,
        a.status,
        a.permission_reason,

        s.student_reference,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,

        c.name AS class_name,
        c.code AS class_code,

        sub.name AS subject_name,
        sub.code AS subject_code,

        u.name AS teacher_name,
        t.employee_number

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

    ORDER BY
        a.attendance_date DESC,
        s.first_name ASC,
        s.last_name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$records = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| SUMMARY COUNTS
|--------------------------------------------------------------------------
*/

$totalRecords = count($records);

$presentCount = 0;
$absentCount = 0;
$permissionCount = 0;

foreach ($records as $record) {

    if ($record['status'] === 'PRESENT') {
        $presentCount++;
    }

    if ($record['status'] === 'ABSENT') {
        $absentCount++;
    }

    if ($record['status'] === 'PERMISSION') {
        $permissionCount++;
    }
}


/*
|--------------------------------------------------------------------------
| ATTENDANCE RATE
|--------------------------------------------------------------------------
*/

$attendanceRate = 0;

if ($totalRecords > 0) {

    $attendanceRate =
        ($presentCount / $totalRecords) * 100;
}


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
                    ATTENDANCE
                </span>

                <h1>
                    Attendance History
                </h1>

                <p>
                    Review historical student attendance records
                    and attendance patterns.
                </p>

            </div>


            <div class="page-header-actions">

                <a
                    href="/student-attendance-system/admin/attendance.php"
                    class="dashboard-primary-button"
                >
                    Manage Attendance
                </a>

            </div>

        </div>


        <!-- ==========================================================
             SUMMARY CARDS
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
                        aria-hidden="true"
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
                        aria-hidden="true"
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
                        aria-hidden="true"
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
                        aria-hidden="true"
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
                        Attendance Rate
                    </h2>

                    <p>
                        Percentage of attendance marked as present
                        within the selected records.
                    </p>

                </div>

                <strong
                    style="
                        font-size: 28px;
                        color: #15803d;
                    "
                >
                    <?= number_format($attendanceRate, 1) ?>%
                </strong>

            </div>


            <div
                style="
                    width:100%;
                    height:10px;
                    background:#e2e8f0;
                    border-radius:999px;
                    overflow:hidden;
                "
            >

                <div
                    style="
                        width:<?= min(100, max(0, $attendanceRate)) ?>%;
                        height:100%;
                        background:#16a34a;
                        border-radius:999px;
                    "
                ></div>

            </div>

        </section>


        <!-- ==========================================================
             FILTERS
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Filter Attendance History
                    </h2>

                    <p>
                        Narrow the records by date, student,
                        class, subject, teacher or status.
                    </p>

                </div>

            </div>


            <form
                method="GET"
                action="/student-attendance-system/admin/attendance-history.php"
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


                    <!-- STUDENT SEARCH -->

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


                    <!-- STATUS -->

                    <div class="form-group">

                        <label for="status">
                            Status
                        </label>

                        <select
                            name="status"
                            id="status"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="PRESENT"
                                <?= $status === 'PRESENT'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Present
                            </option>

                            <option
                                value="ABSENT"
                                <?= $status === 'ABSENT'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Absent
                            </option>

                            <option
                                value="PERMISSION"
                                <?= $status === 'PERMISSION'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Permission
                            </option>

                        </select>

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="dashboard-primary-button"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="/student-attendance-system/admin/attendance-history.php"
                        class="dashboard-secondary-button"
                    >
                        Clear Filters
                    </a>

                </div>

            </form>

        </section>


        <!-- ==========================================================
             HISTORY TABLE
        =========================================================== -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        Attendance Records
                    </h2>

                    <p>
                        <?= number_format($totalRecords) ?>
                        record(s) found.
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

                            <th>Status</th>

                            <th>Reason</th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php if (empty($records)): ?>

                            <tr>

                                <td
                                    colspan="7"
                                    class="empty-table-message"
                                >

                                    No attendance history
                                    matched your filters.

                                </td>

                            </tr>

                        <?php else: ?>


                            <?php foreach ($records as $record): ?>

                                <tr>


                                    <!-- STUDENT -->

                                    <td>

                                        <div class="student-table-profile">

                                            <div class="student-table-avatar">

                                                <?= e(
                                                    strtoupper(
                                                        substr(
                                                            $record['first_name'],
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>

                                            </div>


                                            <div>

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


                                                <span class="table-secondary-text">

                                                    <?= e(
                                                        $record['admission_number']
                                                    ) ?>

                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <!-- CLASS -->

                                    <td>

                                        <strong>
                                            <?= e(
                                                $record['class_name']
                                            ) ?>
                                        </strong>

                                        <span class="table-secondary-text">
                                            <?= e(
                                                $record['class_code']
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- SUBJECT -->

                                    <td>

                                        <strong>
                                            <?= e(
                                                $record['subject_name']
                                            ) ?>
                                        </strong>

                                        <span class="table-secondary-text">
                                            <?= e(
                                                $record['subject_code']
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- TEACHER -->

                                    <td>

                                        <strong>
                                            <?= e(
                                                $record['teacher_name']
                                            ) ?>
                                        </strong>

                                        <span class="table-secondary-text">
                                            <?= e(
                                                $record['employee_number']
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime(
                                                    $record['attendance_date']
                                                )
                                            )
                                        ) ?>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php if (
                                            $record['status'] === 'PRESENT'
                                        ): ?>

                                            <span
                                                class="status-badge status-active"
                                            >
                                                Present
                                            </span>


                                        <?php elseif (
                                            $record['status'] === 'ABSENT'
                                        ): ?>

                                            <span
                                                class="status-badge status-inactive"
                                            >
                                                Absent
                                            </span>


                                        <?php else: ?>

                                            <span
                                                class="status-badge status-permission"
                                            >
                                                Permission
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- REASON -->

                                    <td>

                                        <?php if (
                                            $record['status'] === 'PERMISSION'
                                            &&
                                            !empty(
                                                $record['permission_reason']
                                            )
                                        ): ?>

                                            <details>

                                                <summary>
                                                    View Reason
                                                </summary>

                                                <div
                                                    class="permission-reason"
                                                >

                                                    <?= e(
                                                        $record[
                                                            'permission_reason'
                                                        ]
                                                    ) ?>

                                                </div>

                                            </details>

                                        <?php else: ?>

                                            <span
                                                class="table-secondary-text"
                                            >
                                                -
                                            </span>

                                        <?php endif; ?>

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