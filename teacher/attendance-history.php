<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('TEACHER');

$teacherId = null;
$error = '';

/*
|--------------------------------------------------------------------------
| Get Teacher
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

$stmt->execute([currentUserId()]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher profile not found.');
}

$teacherId = (int) $teacher['id'];


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

$search = trim($_GET['search'] ?? '');

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');


/*
|--------------------------------------------------------------------------
| Validate Dates
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $dateObject = DateTime::createFromFormat('Y-m-d', $dateFrom);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $dateFrom) {
        $dateFrom = '';
    }
}

if ($dateTo !== '') {

    $dateObject = DateTime::createFromFormat('Y-m-d', $dateTo);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $dateTo) {
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

$classStmt->execute([$teacherId]);

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

$subjectStmt->execute([$teacherId]);

$teacherSubjects = $subjectStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Build Attendance Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id,
        a.attendance_date,
        a.status,
        a.permission_reason,

        s.id AS student_id,
        s.student_reference,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.gender,
        s.photo_path,

        c.id AS class_id,
        c.name AS class_name,
        c.code AS class_code,

        sub.id AS subject_id,
        sub.name AS subject_name,
        sub.code AS subject_code

    FROM attendance a

    INNER JOIN students s
        ON s.id = a.student_id

    INNER JOIN classes c
        ON c.id = a.class_id

    INNER JOIN subjects sub
        ON sub.id = a.subject_id

    INNER JOIN teaching_assignments ta
        ON ta.teacher_id = a.teacher_id
        AND ta.class_id = a.class_id
        AND ta.subject_id = a.subject_id

    WHERE a.teacher_id = ?
";

$params = [$teacherId];


/*
|--------------------------------------------------------------------------
| Class Filter
|--------------------------------------------------------------------------
*/

if ($classId > 0) {

    $sql .= " AND a.class_id = ?";
    $params[] = $classId;
}


/*
|--------------------------------------------------------------------------
| Subject Filter
|--------------------------------------------------------------------------
*/

if ($subjectId > 0) {

    $sql .= " AND a.subject_id = ?";
    $params[] = $subjectId;
}


/*
|--------------------------------------------------------------------------
| Search Filter
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            s.first_name LIKE ?
            OR s.middle_name LIKE ?
            OR s.last_name LIKE ?
            OR s.admission_number LIKE ?
            OR s.student_reference LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Date From
|--------------------------------------------------------------------------
*/

if ($dateFrom !== '') {

    $sql .= " AND a.attendance_date >= ?";
    $params[] = $dateFrom;
}


/*
|--------------------------------------------------------------------------
| Date To
|--------------------------------------------------------------------------
*/

if ($dateTo !== '') {

    $sql .= " AND a.attendance_date <= ?";
    $params[] = $dateTo;
}


$sql .= "
    ORDER BY
        a.attendance_date DESC,
        c.name ASC,
        sub.name ASC,
        s.first_name ASC,
        s.last_name ASC
";


/*
|--------------------------------------------------------------------------
| Get Attendance
|--------------------------------------------------------------------------
*/

try {

    $attendanceStmt = $pdo->prepare($sql);
    $attendanceStmt->execute($params);

    $attendanceRecords = $attendanceStmt->fetchAll();

} catch (PDOException $e) {

    $attendanceRecords = [];
    $error = 'Unable to load attendance history.';
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalRecords = count($attendanceRecords);

$presentCount = 0;
$absentCount = 0;
$permissionCount = 0;

foreach ($attendanceRecords as $record) {

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
| Attendance Rate
|--------------------------------------------------------------------------
*/

$attendanceRate = 0;

if ($totalRecords > 0) {

    $attendanceRate = round(
        ($presentCount / $totalRecords) * 100,
        1
    );
}


/*
|--------------------------------------------------------------------------
| Selected Class / Subject Validation
|--------------------------------------------------------------------------
*/

$validClass = true;
$validSubject = true;

if ($classId > 0) {

    $validClass = false;

    foreach ($teacherClasses as $class) {

        if ((int) $class['id'] === $classId) {
            $validClass = true;
            break;
        }
    }

    if (!$validClass) {
        $classId = 0;
    }
}

if ($subjectId > 0) {

    $validSubject = false;

    foreach ($teacherSubjects as $subject) {

        if ((int) $subject['id'] === $subjectId) {
            $validSubject = true;
            break;
        }
    }

    if (!$validSubject) {
        $subjectId = 0;
    }
}


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Attendance History';

$pageType = 'teacher';

$additionalStyles = [
    '/student-attendance-system/assets/css/teacher-history.css'
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">

        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <span class="page-eyebrow">
                    TEACHER PORTAL
                </span>

                <h1>
                    Attendance History
                </h1>

                <p>
                    Review attendance records for your assigned classes and subjects.
                </p>

            </div>

        </div>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <!-- FILTER CARD -->

        <section class="teacher-history-filter-card">

            <div class="teacher-history-filter-header">

                <div>

                    <h2>
                        Filter Attendance
                    </h2>

                    <p>
                        Narrow the records by class, subject, student or date.
                    </p>

                </div>

                <a
                    href="/student-attendance-system/teacher/attendance-history.php"
                    class="teacher-history-reset"
                >
                    Reset Filters
                </a>

            </div>


            <form
                method="GET"
                class="teacher-history-filter-form"
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
                                <?= $classId === (int) $class['id'] ? 'selected' : '' ?>
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
                                <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>
                            >
                                <?= e($subject['name']) ?>
                                — <?= e($subject['code']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="search">
                        Student
                    </label>

                    <input
                        type="search"
                        name="search"
                        id="search"
                        value="<?= e($search) ?>"
                        placeholder="Name, admission no..."
                    >

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


                <div class="teacher-history-filter-button">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Apply Filters
                    </button>

                </div>

            </form>

        </section>


        <!-- STATISTICS -->

        <section class="teacher-history-stats">

            <div class="teacher-history-stat-card">

                <div class="teacher-history-stat-icon">
                    #
                </div>

                <div>

                    <span>Total Records</span>

                    <strong>
                        <?= $totalRecords ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-history-stat-card present">

                <div class="teacher-history-stat-icon">
                    ✓
                </div>

                <div>

                    <span>Present</span>

                    <strong>
                        <?= $presentCount ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-history-stat-card absent">

                <div class="teacher-history-stat-icon">
                    ×
                </div>

                <div>

                    <span>Absent</span>

                    <strong>
                        <?= $absentCount ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-history-stat-card permission">

                <div class="teacher-history-stat-icon">
                    !
                </div>

                <div>

                    <span>Permission</span>

                    <strong>
                        <?= $permissionCount ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-history-stat-card rate">

                <div class="teacher-history-stat-icon">
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


        <!-- HISTORY TABLE -->

        <section class="teacher-history-card">

            <div class="teacher-history-card-header">

                <div>

                    <span class="page-eyebrow">
                        RECORDS
                    </span>

                    <h2>
                        Attendance Records
                    </h2>

                </div>

                <span class="teacher-history-record-count">
                    <?= $totalRecords ?>
                    record<?= $totalRecords === 1 ? '' : 's' ?>
                </span>

            </div>


            <?php if (!empty($attendanceRecords)): ?>

                <div class="teacher-history-table-wrapper">

                    <table class="teacher-history-table">

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
                                    Permission Reason
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            <?php foreach ($attendanceRecords as $record): ?>

                                <tr>

                                    <!-- DATE -->

                                    <td>

                                        <div class="history-date">

                                            <strong>
                                                <?= e(
                                                    date(
                                                        'd',
                                                        strtotime($record['attendance_date'])
                                                    )
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= e(
                                                    date(
                                                        'M Y',
                                                        strtotime($record['attendance_date'])
                                                    )
                                                ) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- STUDENT -->

                                    <td>

                                        <div class="history-student">

                                            <?php if (!empty($record['photo_path'])): ?>

                                                <img
                                                    src="/student-attendance-system/<?= e($record['photo_path']) ?>"
                                                    alt="<?= e($record['first_name']) ?>"
                                                    class="history-student-avatar"
                                                >

                                            <?php else: ?>

                                                <div class="history-student-avatar history-avatar-placeholder">

                                                    <?= strtoupper(
                                                        substr(
                                                            $record['first_name'],
                                                            0,
                                                            1
                                                        )
                                                    ) ?>

                                                </div>

                                            <?php endif; ?>


                                            <div>

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

                                        </div>

                                    </td>


                                    <!-- CLASS -->

                                    <td>

                                        <div class="history-class">

                                            <strong>
                                                <?= e($record['class_name']) ?>
                                            </strong>

                                            <span>
                                                <?= e($record['class_code']) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- SUBJECT -->

                                    <td>

                                        <div class="history-subject">

                                            <strong>
                                                <?= e($record['subject_name']) ?>
                                            </strong>

                                            <span>
                                                <?= e($record['subject_code']) ?>
                                            </span>

                                        </div>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php if ($record['status'] === 'PRESENT'): ?>

                                            <span class="teacher-history-status present">
                                                Present
                                            </span>

                                        <?php elseif ($record['status'] === 'ABSENT'): ?>

                                            <span class="teacher-history-status absent">
                                                Absent
                                            </span>

                                        <?php else: ?>

                                            <span class="teacher-history-status permission">
                                                Permission
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- PERMISSION -->

                                    <td>

                                        <?php if ($record['status'] === 'PERMISSION'): ?>

                                            <button
                                                type="button"
                                                class="history-reason-button"
                                                onclick="showPermissionReason(this)"
                                                data-reason="<?= e($record['permission_reason']) ?>"
                                            >
                                                View Reason
                                            </button>

                                        <?php else: ?>

                                            <span class="history-no-reason">
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

                <div class="teacher-history-empty">

                    <div class="teacher-history-empty-icon">
                        ✓
                    </div>

                    <h3>
                        No Attendance Records
                    </h3>

                    <p>
                        No attendance records match your current filters.
                    </p>

                    <a
                        href="/student-attendance-system/teacher/attendance-history.php"
                        class="btn btn-secondary"
                    >
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>


<!-- PERMISSION REASON MODAL -->

<div
    class="teacher-reason-modal"
    id="permissionReasonModal"
    aria-hidden="true"
>

    <div
        class="teacher-reason-modal-overlay"
        onclick="closePermissionReason()"
    ></div>


    <div class="teacher-reason-modal-content">

        <button
            type="button"
            class="teacher-reason-modal-close"
            onclick="closePermissionReason()"
            aria-label="Close"
        >
            &times;
        </button>


        <div class="teacher-reason-modal-icon">
            !
        </div>


        <span class="page-eyebrow">
            PERMISSION
        </span>

        <h2>
            Permission Reason
        </h2>

        <p
            id="permissionReasonText"
            class="teacher-reason-text"
        ></p>

    </div>

</div>


<script>

function showPermissionReason(button) {

    const modal = document.getElementById(
        'permissionReasonModal'
    );

    const reasonText = document.getElementById(
        'permissionReasonText'
    );

    const reason = button.getAttribute(
        'data-reason'
    );

    reasonText.textContent =
        reason || 'No reason was provided.';

    modal.classList.add('show');

    modal.setAttribute(
        'aria-hidden',
        'false'
    );

    document.body.classList.add(
        'modal-open'
    );
}


function closePermissionReason() {

    const modal = document.getElementById(
        'permissionReasonModal'
    );

    modal.classList.remove('show');

    modal.setAttribute(
        'aria-hidden',
        'true'
    );

    document.body.classList.remove(
        'modal-open'
    );
}


document.addEventListener(
    'keydown',
    function (event) {

        if (event.key === 'Escape') {
            closePermissionReason();
        }

    }
);

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>