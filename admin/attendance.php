<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Attendance Management';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css',
    '/student-attendance-system/assets/css/forms.css'
];

$flash = getFlashMessage();
$error = null;
$editAttendance = null;

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function attendanceRedirect(): never
{
    header('Location: /student-attendance-system/admin/attendance.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| DELETE ATTENDANCE
|--------------------------------------------------------------------------
*/

if (isPost() && isset($_POST['delete_attendance'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token. Please try again.';

    } else {

        $attendanceId = filter_input(
            INPUT_POST,
            'attendance_id',
            FILTER_VALIDATE_INT
        );

        if (!$attendanceId) {

            $error = 'Invalid attendance record.';

        } else {

            try {

                $stmt = $pdo->prepare(
                    "DELETE FROM attendance WHERE id = ?"
                );

                $stmt->execute([$attendanceId]);

                if ($stmt->rowCount() > 0) {

                    setFlashMessage(
                        'success',
                        'Attendance record deleted successfully.'
                    );

                } else {

                    setFlashMessage(
                        'error',
                        'Attendance record was not found.'
                    );
                }

                attendanceRedirect();

            } catch (PDOException $e) {

                $error = 'Unable to delete the attendance record.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE ATTENDANCE
|--------------------------------------------------------------------------
*/

if (isPost() && isset($_POST['save_attendance'])) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $error = 'Invalid security token. Please try again.';

    } else {

        $attendanceId = filter_input(
            INPUT_POST,
            'attendance_id',
            FILTER_VALIDATE_INT
        );

        $studentId = filter_input(
            INPUT_POST,
            'student_id',
            FILTER_VALIDATE_INT
        );

        $classId = filter_input(
            INPUT_POST,
            'class_id',
            FILTER_VALIDATE_INT
        );

        $subjectId = filter_input(
            INPUT_POST,
            'subject_id',
            FILTER_VALIDATE_INT
        );

        $teacherId = filter_input(
            INPUT_POST,
            'teacher_id',
            FILTER_VALIDATE_INT
        );

        $attendanceDate = trim($_POST['attendance_date'] ?? '');
        $status = strtoupper(trim($_POST['status'] ?? ''));
        $permissionReason = trim($_POST['permission_reason'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (!$studentId) {

            $error = 'Please select a student.';

        } elseif (!$classId) {

            $error = 'Please select a class.';

        } elseif (!$subjectId) {

            $error = 'Please select a subject.';

        } elseif (!$teacherId) {

            $error = 'Please select a teacher.';

        } elseif ($attendanceDate === '') {

            $error = 'Please select the attendance date.';

        } elseif (!in_array(
            $status,
            ['PRESENT', 'ABSENT', 'PERMISSION'],
            true
        )) {

            $error = 'Invalid attendance status.';

        } elseif (
            $status === 'PERMISSION' &&
            $permissionReason === ''
        ) {

            $error = 'Please provide a reason for permission.';

        } elseif ($status !== 'PERMISSION') {

            $permissionReason = null;

        } elseif (strlen($permissionReason) > 255) {

            $error = 'Permission reason must not exceed 255 characters.';
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Date
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $attendanceDate
            );

            $dateErrors = DateTime::getLastErrors();

            if (
                !$dateObject ||
                (
                    $dateErrors !== false &&
                    (
                        $dateErrors['warning_count'] > 0 ||
                        $dateErrors['error_count'] > 0
                    )
                ) ||
                $dateObject->format('Y-m-d') !== $attendanceDate
            ) {

                $error = 'Please enter a valid attendance date.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Student
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            $stmt = $pdo->prepare(
                "SELECT id, class_id
                 FROM students
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$studentId]);

            $student = $stmt->fetch();

            if (!$student) {

                $error = 'Selected student was not found.';

            } elseif ((int) $student['class_id'] !== (int) $classId) {

                $error = 'The selected student does not belong to the selected class.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Class
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM classes
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$classId]);

            if (!$stmt->fetch()) {
                $error = 'Selected class was not found.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Subject
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM subjects
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$subjectId]);

            if (!$stmt->fetch()) {
                $error = 'Selected subject was not found.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Teacher
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM teachers
                 WHERE id = ?
                 LIMIT 1"
            );

            $stmt->execute([$teacherId]);

            if (!$stmt->fetch()) {
                $error = 'Selected teacher was not found.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Validate Teaching Assignment
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM teaching_assignments
                 WHERE teacher_id = ?
                   AND class_id = ?
                   AND subject_id = ?
                 LIMIT 1"
            );

            $stmt->execute([
                $teacherId,
                $classId,
                $subjectId
            ]);

            if (!$stmt->fetch()) {

                $error = 'The selected teacher is not assigned to this class and subject.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Check Duplicate
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            if ($attendanceId) {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM attendance
                     WHERE student_id = ?
                       AND class_id = ?
                       AND subject_id = ?
                       AND attendance_date = ?
                       AND id != ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $studentId,
                    $classId,
                    $subjectId,
                    $attendanceDate,
                    $attendanceId
                ]);

            } else {

                $stmt = $pdo->prepare(
                    "SELECT id
                     FROM attendance
                     WHERE student_id = ?
                       AND class_id = ?
                       AND subject_id = ?
                       AND attendance_date = ?
                     LIMIT 1"
                );

                $stmt->execute([
                    $studentId,
                    $classId,
                    $subjectId,
                    $attendanceDate
                ]);
            }

            if ($stmt->fetch()) {

                $error = 'Attendance already exists for this student, class, subject and date.';
            }
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE
        |--------------------------------------------------------------------------
        */

        if ($error === null) {

            try {

                if ($attendanceId) {

                    $stmt = $pdo->prepare(
                        "UPDATE attendance
                         SET student_id = ?,
                             class_id = ?,
                             subject_id = ?,
                             teacher_id = ?,
                             attendance_date = ?,
                             status = ?,
                             permission_reason = ?
                         WHERE id = ?"
                    );

                    $stmt->execute([
                        $studentId,
                        $classId,
                        $subjectId,
                        $teacherId,
                        $attendanceDate,
                        $status,
                        $permissionReason,
                        $attendanceId
                    ]);

                    setFlashMessage(
                        'success',
                        'Attendance record updated successfully.'
                    );

                } else {

                    $stmt = $pdo->prepare(
                        "INSERT INTO attendance
                        (
                            student_id,
                            class_id,
                            subject_id,
                            teacher_id,
                            attendance_date,
                            status,
                            permission_reason
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $studentId,
                        $classId,
                        $subjectId,
                        $teacherId,
                        $attendanceDate,
                        $status,
                        $permissionReason
                    ]);

                    setFlashMessage(
                        'success',
                        'Attendance recorded successfully.'
                    );
                }

                attendanceRedirect();

            } catch (PDOException $e) {

                if ($e->getCode() === '23000') {

                    $error = 'This attendance record already exists.';

                } else {

                    $error = 'Unable to save attendance. Please try again.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| EDIT ATTENDANCE
|--------------------------------------------------------------------------
*/

if (isGet() && isset($_GET['edit'])) {

    $editId = filter_input(
        INPUT_GET,
        'edit',
        FILTER_VALIDATE_INT
    );

    if ($editId) {

        $stmt = $pdo->prepare(
            "SELECT *
             FROM attendance
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->execute([$editId]);

        $editAttendance = $stmt->fetch();

        if (!$editAttendance) {

            setFlashMessage(
                'error',
                'Attendance record was not found.'
            );

            attendanceRedirect();
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD CLASSES
|--------------------------------------------------------------------------
*/

$classesStmt = $pdo->query(
    "SELECT id, name, code
     FROM classes
     ORDER BY name ASC"
);

$classes = $classesStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| LOAD SUBJECTS
|--------------------------------------------------------------------------
*/

$subjectsStmt = $pdo->query(
    "SELECT id, name, code
     FROM subjects
     ORDER BY name ASC"
);

$subjects = $subjectsStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| LOAD TEACHERS
|--------------------------------------------------------------------------
*/

$teachersStmt = $pdo->query(
    "SELECT
        t.id,
        u.name,
        t.employee_number
     FROM teachers t
     INNER JOIN users u
        ON u.id = t.user_id
     ORDER BY u.name ASC"
);

$teachers = $teachersStmt->fetchAll();

/*
|--------------------------------------------------------------------------
| STUDENTS FOR FORM
|--------------------------------------------------------------------------
*/

$formClassId = $editAttendance['class_id'] ?? null;

$studentsForForm = [];

if ($formClassId) {

    $stmt = $pdo->prepare(
        "SELECT
            id,
            student_reference,
            admission_number,
            first_name,
            middle_name,
            last_name,
            class_id
         FROM students
         WHERE class_id = ?
           AND status = 'ACTIVE'
         ORDER BY first_name ASC, last_name ASC"
    );

    $stmt->execute([$formClassId]);

    $studentsForForm = $stmt->fetchAll();

} else {

    $stmt = $pdo->query(
        "SELECT
            id,
            class_id,
            student_reference,
            admission_number,
            first_name,
            middle_name,
            last_name
         FROM students
         WHERE status = 'ACTIVE'
         ORDER BY first_name ASC, last_name ASC"
    );

    $studentsForForm = $stmt->fetchAll();
}

/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$filterDate = trim($_GET['date'] ?? '');

$filterClass = filter_input(
    INPUT_GET,
    'class_id',
    FILTER_VALIDATE_INT
) ?: null;

$filterSubject = filter_input(
    INPUT_GET,
    'subject_id',
    FILTER_VALIDATE_INT
) ?: null;

$filterTeacher = filter_input(
    INPUT_GET,
    'teacher_id',
    FILTER_VALIDATE_INT
) ?: null;

$filterStatus = strtoupper(
    trim($_GET['status'] ?? '')
);

/*
|--------------------------------------------------------------------------
| ATTENDANCE QUERY
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($filterDate !== '') {

    $where[] = 'a.attendance_date = ?';
    $params[] = $filterDate;
}

if ($filterClass) {

    $where[] = 'a.class_id = ?';
    $params[] = $filterClass;
}

if ($filterSubject) {

    $where[] = 'a.subject_id = ?';
    $params[] = $filterSubject;
}

if ($filterTeacher) {

    $where[] = 'a.teacher_id = ?';
    $params[] = $filterTeacher;
}

if (in_array(
    $filterStatus,
    ['PRESENT', 'ABSENT', 'PERMISSION'],
    true
)) {

    $where[] = 'a.status = ?';
    $params[] = $filterStatus;
}

$whereSql = '';

if (!empty($where)) {

    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

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

$attendanceRecords = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| ATTENDANCE SUMMARY
|--------------------------------------------------------------------------
*/

$totalAttendance = count($attendanceRecords);
$presentCount = 0;
$absentCount = 0;
$permissionCount = 0;

foreach ($attendanceRecords as $record) {

    if ($record['status'] === 'PRESENT') {
        $presentCount++;
    } elseif ($record['status'] === 'ABSENT') {
        $absentCount++;
    } elseif ($record['status'] === 'PERMISSION') {
        $permissionCount++;
    }
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

        <!-- PAGE HEADER -->

        <div class="page-header attendance-page-header">

            <div>

                <span class="page-eyebrow">
                    ATTENDANCE MANAGEMENT
                </span>

                <h1>
                    Attendance
                </h1>

                <p>
                    Record and manage student attendance across classes and subjects.
                </p>

            </div>

        </div>


        <!-- ALERTS -->

        <?php if ($flash): ?>

            <div class="alert alert-<?= e($flash['type']) ?>">
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <!-- SUMMARY -->

        <div class="attendance-summary-grid">

            <div class="attendance-summary-card">

                <div class="attendance-summary-icon">
                    <span>Σ</span>
                </div>

                <div>
                    <span class="attendance-summary-label">
                        Total Records
                    </span>

                    <strong>
                        <?= number_format($totalAttendance) ?>
                    </strong>
                </div>

            </div>


            <div class="attendance-summary-card attendance-summary-present">

                <div class="attendance-summary-icon">
                    <span>✓</span>
                </div>

                <div>
                    <span class="attendance-summary-label">
                        Present
                    </span>

                    <strong>
                        <?= number_format($presentCount) ?>
                    </strong>
                </div>

            </div>


            <div class="attendance-summary-card attendance-summary-absent">

                <div class="attendance-summary-icon">
                    <span>×</span>
                </div>

                <div>
                    <span class="attendance-summary-label">
                        Absent
                    </span>

                    <strong>
                        <?= number_format($absentCount) ?>
                    </strong>
                </div>

            </div>


            <div class="attendance-summary-card attendance-summary-permission">

                <div class="attendance-summary-icon">
                    <span>!</span>
                </div>

                <div>
                    <span class="attendance-summary-label">
                        Permission
                    </span>

                    <strong>
                        <?= number_format($permissionCount) ?>
                    </strong>
                </div>

            </div>

        </div>


        <!-- RECORD ATTENDANCE -->

        <section class="dashboard-card attendance-form-card">

            <div class="dashboard-card-header attendance-section-header">

                <div>

                    <span class="attendance-section-label">
                        <?= $editAttendance ? 'UPDATE RECORD' : 'NEW RECORD' ?>
                    </span>

                    <h2>
                        <?= $editAttendance
                            ? 'Edit Attendance'
                            : 'Record Attendance' ?>
                    </h2>

                    <p>
                        <?= $editAttendance
                            ? 'Update the selected attendance information below.'
                            : 'Enter the student attendance information below.' ?>
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="/student-attendance-system/admin/attendance.php"
                class="dashboard-form"
                id="attendance-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <?php if ($editAttendance): ?>

                    <input
                        type="hidden"
                        name="attendance_id"
                        value="<?= (int) $editAttendance['id'] ?>"
                    >

                <?php endif; ?>


                <div class="attendance-form-grid">

                    <!-- CLASS -->

                    <div class="form-group">

                        <label for="class_id">
                            Class
                        </label>

                        <select
                            name="class_id"
                            id="class_id"
                            required
                            onchange="filterAttendanceStudents()"
                        >

                            <option value="">
                                Select class
                            </option>

                            <?php foreach ($classes as $class): ?>

                                <option
                                    value="<?= (int) $class['id'] ?>"
                                    <?= (
                                        (string) (
                                            $editAttendance['class_id'] ?? ''
                                        ) === (string) $class['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($class['name']) ?>
                                    (<?= e($class['code']) ?>)
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- STUDENT -->

                    <div class="form-group">

                        <label for="student_id">
                            Student
                        </label>

                        <select
                            name="student_id"
                            id="student_id"
                            required
                        >

                            <option value="">
                                Select student
                            </option>

                            <?php foreach ($studentsForForm as $student): ?>

                                <option
                                    value="<?= (int) $student['id'] ?>"
                                    data-class-id="<?= (int) $student['class_id'] ?>"
                                    <?= (
                                        (string) (
                                            $editAttendance['student_id'] ?? ''
                                        ) === (string) $student['id']
                                    ) ? 'selected' : '' ?>
                                >

                                    <?= e(
                                        $student['first_name']
                                        . ' '
                                        . (
                                            $student['middle_name']
                                                ? $student['middle_name'] . ' '
                                                : ''
                                        )
                                        . $student['last_name']
                                    ) ?>

                                    —
                                    <?= e($student['admission_number']) ?>

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
                            required
                        >

                            <option value="">
                                Select subject
                            </option>

                            <?php foreach ($subjects as $subject): ?>

                                <option
                                    value="<?= (int) $subject['id'] ?>"
                                    <?= (
                                        (string) (
                                            $editAttendance['subject_id'] ?? ''
                                        ) === (string) $subject['id']
                                    ) ? 'selected' : '' ?>
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
                            required
                        >

                            <option value="">
                                Select teacher
                            </option>

                            <?php foreach ($teachers as $teacher): ?>

                                <option
                                    value="<?= (int) $teacher['id'] ?>"
                                    <?= (
                                        (string) (
                                            $editAttendance['teacher_id'] ?? ''
                                        ) === (string) $teacher['id']
                                    ) ? 'selected' : '' ?>
                                >

                                    <?= e($teacher['name']) ?>

                                    —
                                    <?= e($teacher['employee_number']) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- DATE -->

                    <div class="form-group">

                        <label for="attendance_date">
                            Attendance Date
                        </label>

                        <input
                            type="date"
                            name="attendance_date"
                            id="attendance_date"
                            value="<?= e(
                                $editAttendance['attendance_date']
                                ?? date('Y-m-d')
                            ) ?>"
                            required
                        >

                    </div>


                    <!-- STATUS -->

                    <div class="form-group">

                        <label for="status">
                            Attendance Status
                        </label>

                        <select
                            name="status"
                            id="status"
                            required
                            onchange="togglePermissionReason()"
                        >

                            <option value="">
                                Select status
                            </option>

                            <option
                                value="PRESENT"
                                <?= (
                                    ($editAttendance['status'] ?? '') === 'PRESENT'
                                ) ? 'selected' : '' ?>
                            >
                                Present
                            </option>

                            <option
                                value="ABSENT"
                                <?= (
                                    ($editAttendance['status'] ?? '') === 'ABSENT'
                                ) ? 'selected' : '' ?>
                            >
                                Absent
                            </option>

                            <option
                                value="PERMISSION"
                                <?= (
                                    ($editAttendance['status'] ?? '') === 'PERMISSION'
                                ) ? 'selected' : '' ?>
                            >
                                Permission
                            </option>

                        </select>

                    </div>

                </div>


                <!-- PERMISSION REASON -->

                <div
                    class="form-group permission-reason-group"
                    id="permission-reason-group"
                    style="display:none;"
                >

                    <label for="permission_reason">
                        Permission Reason
                    </label>

                    <textarea
                        name="permission_reason"
                        id="permission_reason"
                        maxlength="255"
                        placeholder="Enter the reason for permission..."
                    ><?= e($editAttendance['permission_reason'] ?? '') ?></textarea>

                    <small class="form-help-text">
                        Required only when the attendance status is Permission.
                    </small>

                </div>


                <div class="form-actions attendance-form-actions">

                    <button
                        type="submit"
                        name="save_attendance"
                        class="dashboard-primary-button"
                    >
                        <?= $editAttendance
                            ? 'Update Attendance'
                            : 'Save Attendance' ?>
                    </button>

                    <?php if ($editAttendance): ?>

                        <a
                            href="/student-attendance-system/admin/attendance.php"
                            class="dashboard-secondary-button"
                        >
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- FILTERS -->

        <section class="dashboard-card attendance-filter-card">

            <div class="dashboard-card-header attendance-section-header">

                <div>

                    <span class="attendance-section-label">
                        RECORDS
                    </span>

                    <h2>
                        Attendance Records
                    </h2>

                    <p>
                        Use the filters below to find specific attendance records.
                    </p>

                </div>

                <div class="attendance-record-count">

                    <strong>
                        <?= number_format($totalAttendance) ?>
                    </strong>

                    <span>
                        Records
                    </span>

                </div>

            </div>


            <form
                method="GET"
                action="/student-attendance-system/admin/attendance.php"
                class="attendance-filter-form"
            >

                <div class="attendance-filter-grid">

                    <div class="form-group">

                        <label for="filter-date">
                            Date
                        </label>

                        <input
                            type="date"
                            id="filter-date"
                            name="date"
                            value="<?= e($filterDate) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="filter-class">
                            Class
                        </label>

                        <select
                            id="filter-class"
                            name="class_id"
                        >

                            <option value="">
                                All Classes
                            </option>

                            <?php foreach ($classes as $class): ?>

                                <option
                                    value="<?= (int) $class['id'] ?>"
                                    <?= (
                                        $filterClass === (int) $class['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($class['name']) ?>
                                    (<?= e($class['code']) ?>)
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="filter-subject">
                            Subject
                        </label>

                        <select
                            id="filter-subject"
                            name="subject_id"
                        >

                            <option value="">
                                All Subjects
                            </option>

                            <?php foreach ($subjects as $subject): ?>

                                <option
                                    value="<?= (int) $subject['id'] ?>"
                                    <?= (
                                        $filterSubject === (int) $subject['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($subject['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="filter-teacher">
                            Teacher
                        </label>

                        <select
                            id="filter-teacher"
                            name="teacher_id"
                        >

                            <option value="">
                                All Teachers
                            </option>

                            <?php foreach ($teachers as $teacher): ?>

                                <option
                                    value="<?= (int) $teacher['id'] ?>"
                                    <?= (
                                        $filterTeacher === (int) $teacher['id']
                                    ) ? 'selected' : '' ?>
                                >
                                    <?= e($teacher['name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="filter-status">
                            Status
                        </label>

                        <select
                            id="filter-status"
                            name="status"
                        >

                            <option value="">
                                All Statuses
                            </option>

                            <option
                                value="PRESENT"
                                <?= $filterStatus === 'PRESENT'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Present
                            </option>

                            <option
                                value="ABSENT"
                                <?= $filterStatus === 'ABSENT'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Absent
                            </option>

                            <option
                                value="PERMISSION"
                                <?= $filterStatus === 'PERMISSION'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Permission
                            </option>

                        </select>

                    </div>

                </div>


                <div class="filter-actions attendance-filter-actions">

                    <button
                        type="submit"
                        class="dashboard-primary-button"
                    >
                        Apply Filters
                    </button>

                    <a
                        href="/student-attendance-system/admin/attendance.php"
                        class="dashboard-secondary-button"
                    >
                        Clear Filters
                    </a>

                </div>

            </form>

        </section>


        <!-- ATTENDANCE TABLE -->

        <section class="dashboard-card attendance-table-card">

            <div class="attendance-table-heading">

                <div>

                    <span class="attendance-section-label">
                        ATTENDANCE LIST
                    </span>

                    <h2>
                        Recorded Attendance
                    </h2>

                </div>

            </div>


            <div class="table-wrapper attendance-table-wrapper">

                <table class="data-table attendance-data-table">

                    <thead>

                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Subject</th>
                            <th>Teacher</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Reason</th>
                            <th>Actions</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($attendanceRecords)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="empty-table-message"
                                >

                                    <div class="attendance-empty-state">

                                        <div class="attendance-empty-icon">
                                            —
                                        </div>

                                        <strong>
                                            No attendance records found
                                        </strong>

                                        <span>
                                            Try changing your filters or record a new attendance entry.
                                        </span>

                                    </div>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($attendanceRecords as $record): ?>

                                <tr>

                                    <!-- STUDENT -->

                                    <td>

                                        <div class="student-table-profile">

                                            <div class="student-table-avatar attendance-avatar">

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

                                            <div class="attendance-student-info">

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

                                        <div class="attendance-table-main">
                                            <?= e($record['class_name']) ?>
                                        </div>

                                        <span class="table-secondary-text">
                                            <?= e($record['class_code']) ?>
                                        </span>

                                    </td>


                                    <!-- SUBJECT -->

                                    <td>

                                        <div class="attendance-table-main">
                                            <?= e($record['subject_name']) ?>
                                        </div>

                                        <span class="table-secondary-text">
                                            <?= e($record['subject_code']) ?>
                                        </span>

                                    </td>


                                    <!-- TEACHER -->

                                    <td>

                                        <div class="attendance-table-main">
                                            <?= e($record['teacher_name']) ?>
                                        </div>

                                        <span class="table-secondary-text">
                                            <?= e($record['employee_number']) ?>
                                        </span>

                                    </td>


                                    <!-- DATE -->

                                    <td>

                                        <span class="attendance-date">
                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $record['attendance_date']
                                                    )
                                                )
                                            ) ?>
                                        </span>

                                    </td>


                                    <!-- STATUS -->

                                    <td>

                                        <?php if ($record['status'] === 'PRESENT'): ?>

                                            <span class="attendance-status attendance-status-present">
                                                <span>✓</span>
                                                Present
                                            </span>

                                        <?php elseif ($record['status'] === 'ABSENT'): ?>

                                            <span class="attendance-status attendance-status-absent">
                                                <span>×</span>
                                                Absent
                                            </span>

                                        <?php else: ?>

                                            <span class="attendance-status attendance-status-permission">
                                                <span>!</span>
                                                Permission
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- REASON -->

                                    <td>

                                        <?php if (
                                            $record['status'] === 'PERMISSION' &&
                                            !empty($record['permission_reason'])
                                        ): ?>

                                            <details class="attendance-reason-details">

                                                <summary>
                                                    View reason
                                                </summary>

                                                <div class="permission-reason">
                                                    <?= e(
                                                        $record['permission_reason']
                                                    ) ?>
                                                </div>

                                            </details>

                                        <?php else: ?>

                                            <span class="table-secondary-text">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td>

                                        <div class="attendance-actions">

                                            <a
                                                href="/student-attendance-system/admin/attendance.php?edit=<?= (int) $record['id'] ?>#attendance-form"
                                                class="attendance-edit-button"
                                            >
                                                Edit
                                            </a>

                                            <form
                                                method="POST"
                                                action="/student-attendance-system/admin/attendance.php"
                                                onsubmit="return confirm('Are you sure you want to delete this attendance record?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e(csrfToken()) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="attendance_id"
                                                    value="<?= (int) $record['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="delete_attendance"
                                                    class="attendance-delete-button"
                                                >
                                                    Delete
                                                </button>

                                            </form>

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


<script>

/*
|--------------------------------------------------------------------------
| Permission Reason
|--------------------------------------------------------------------------
*/

function togglePermissionReason() {

    const status = document.getElementById('status');
    const reasonGroup = document.getElementById('permission-reason-group');
    const reason = document.getElementById('permission_reason');

    if (!status || !reasonGroup || !reason) {
        return;
    }

    if (status.value === 'PERMISSION') {

        reasonGroup.style.display = 'flex';
        reason.required = true;

    } else {

        reasonGroup.style.display = 'none';
        reason.required = false;
        reason.value = '';
    }
}


/*
|--------------------------------------------------------------------------
| Filter Students By Class
|--------------------------------------------------------------------------
*/

function filterAttendanceStudents() {

    const classSelect = document.getElementById('class_id');
    const studentSelect = document.getElementById('student_id');

    if (!classSelect || !studentSelect) {
        return;
    }

    const selectedClass = classSelect.value;
    const options = studentSelect.querySelectorAll('option');

    let selectedStillVisible = false;

    options.forEach(function(option, index) {

        if (index === 0) {
            option.hidden = false;
            return;
        }

        const optionClass = option.getAttribute('data-class-id');

        if (!selectedClass || optionClass === selectedClass) {

            option.hidden = false;

            if (option.selected) {
                selectedStillVisible = true;
            }

        } else {

            option.hidden = true;
            option.selected = false;
        }
    });

    if (!selectedStillVisible) {
        studentSelect.value = '';
    }
}


/*
|--------------------------------------------------------------------------
| Initialize
|--------------------------------------------------------------------------
*/

document.addEventListener('DOMContentLoaded', function() {

    togglePermissionReason();
    filterAttendanceStudents();

});

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
