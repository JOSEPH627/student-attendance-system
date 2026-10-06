<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Teachers | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css',
    '/student-attendance-system/assets/css/forms.css'
];


$search = trim($_GET['search'] ?? '');
$department = trim($_GET['department'] ?? '');


/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/

$flash = getFlashMessage();


/*
|--------------------------------------------------------------------------
| Delete Teacher
|--------------------------------------------------------------------------
*/

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {

        $error =
            'Invalid security token. Please try again.';

    } elseif ($action === 'delete') {

        $teacherId =
            (int) ($_POST['teacher_id'] ?? 0);

        if ($teacherId <= 0) {

            $error =
                'Invalid teacher selected.';

        } else {

            try {

                /*
                |--------------------------------------------------------------------------
                | Get User ID
                |--------------------------------------------------------------------------
                */

                $teacherStmt = $pdo->prepare(
                    "
                    SELECT
                        id,
                        user_id
                    FROM teachers
                    WHERE id = ?
                    LIMIT 1
                    "
                );

                $teacherStmt->execute([
                    $teacherId
                ]);

                $teacher =
                    $teacherStmt->fetch();


                if (!$teacher) {

                    $error =
                        'The selected teacher was not found.';

                } else {

                    $userId =
                        (int) $teacher['user_id'];


                    /*
                    |--------------------------------------------------------------------------
                    | Check Teaching Assignments
                    |--------------------------------------------------------------------------
                    */

                    $assignmentCheck =
                        $pdo->prepare(
                            "
                            SELECT COUNT(*)
                            FROM teaching_assignments
                            WHERE teacher_id = ?
                            "
                        );

                    $assignmentCheck->execute([
                        $teacherId
                    ]);

                    $assignmentCount =
                        (int) $assignmentCheck->fetchColumn();


                    /*
                    |--------------------------------------------------------------------------
                    | Check Attendance Records
                    |--------------------------------------------------------------------------
                    */

                    $attendanceCheck =
                        $pdo->prepare(
                            "
                            SELECT COUNT(*)
                            FROM attendance
                            WHERE teacher_id = ?
                            "
                        );

                    $attendanceCheck->execute([
                        $teacherId
                    ]);

                    $attendanceCount =
                        (int) $attendanceCheck->fetchColumn();


                    if (
                        $assignmentCount > 0 ||
                        $attendanceCount > 0
                    ) {

                        $error =
                            'This teacher cannot be deleted because the account is already in use.';

                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Delete User
                        |--------------------------------------------------------------------------
                        |
                        | teachers.user_id has ON DELETE CASCADE,
                        | so deleting the user removes the teacher record.
                        |
                        */

                        $delete =
                            $pdo->prepare(
                                "
                                DELETE FROM users
                                WHERE id = ?
                                LIMIT 1
                                "
                            );

                        $delete->execute([
                            $userId
                        ]);


                        if (
                            $delete->rowCount() > 0
                        ) {

                            setFlashMessage(
                                'success',
                                'Teacher deleted successfully.'
                            );

                            redirect(
                                '/student-attendance-system/admin/teachers.php'
                            );

                        } else {

                            $error =
                                'Unable to delete the selected teacher.';
                        }
                    }
                }

            } catch (PDOException $e) {

                $error =
                    'Unable to delete the teacher.';
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Build Teacher Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        t.id AS teacher_id,
        t.employee_number,
        t.phone,
        t.department,
        t.created_at,

        u.id AS user_id,
        u.name,
        u.email,
        u.role,

        (
            SELECT COUNT(*)
            FROM teaching_assignments ta
            WHERE ta.teacher_id = t.id
        ) AS assignment_count,

        (
            SELECT COUNT(*)
            FROM attendance a
            WHERE a.teacher_id = t.id
        ) AS attendance_count

    FROM teachers t

    INNER JOIN users u
        ON u.id = t.user_id

    WHERE u.role = 'TEACHER'
";


$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            u.name LIKE ?
            OR u.email LIKE ?
            OR t.employee_number LIKE ?
            OR t.phone LIKE ?
            OR t.department LIKE ?
        )
    ";

    $searchValue =
        '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Department Filter
|--------------------------------------------------------------------------
*/

if ($department !== '') {

    $sql .= "
        AND t.department = ?
    ";

    $params[] =
        $department;
}


$sql .= "
    ORDER BY
        u.name ASC
";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$teachers =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Departments
|--------------------------------------------------------------------------
*/

$departmentStmt = $pdo->query(
    "
    SELECT DISTINCT
        department
    FROM teachers
    WHERE department IS NOT NULL
      AND department != ''
    ORDER BY department ASC
    "
);

$departments =
    $departmentStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrfToken =
    csrfToken();


require_once __DIR__ . '/../includes/header.php';

?>


<div class="dashboard-layout">


    <?php
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>


    <main class="dashboard-main">


        <?php
        require_once __DIR__ . '/../includes/navbar.php';
        ?>


        <div class="dashboard-content">


            <!-- =================================================
                 PAGE HEADER
                 ================================================= -->

            <div class="page-header">

                <div>

                    <span class="page-header-label">
                        TEACHER MANAGEMENT
                    </span>

                    <h1>
                        Teachers
                    </h1>

                    <p>
                        Manage teacher accounts and teaching assignments.
                    </p>

                </div>


                <div class="page-header-actions">

                    <a
                        href="add-teacher.php"
                        class="dashboard-primary-button"
                    >
                        + Add Teacher
                    </a>

                </div>

            </div>


            <!-- =================================================
                 FLASH MESSAGE
                 ================================================= -->

            <?php if ($flash): ?>

                <div
                    class="table-message <?= e(
                        $flash['type']
                    ) ?>"
                >

                    <?= e(
                        $flash['message']
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 ERROR
                 ================================================= -->

            <?php if ($error !== ''): ?>

                <div class="table-message error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 FILTERS
                 ================================================= -->

            <div class="table-panel">


                <form
                    method="GET"
                    action="teachers.php"
                    class="student-filter-form"
                >


                    <div class="form-filter-group">

                        <label for="search">
                            Search Teachers
                        </label>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Name, email, employee number..."
                        >

                    </div>


                    <div class="form-filter-group">

                        <label for="department">
                            Department
                        </label>

                        <select
                            id="department"
                            name="department"
                        >

                            <option value="">
                                All Departments
                            </option>


                            <?php foreach (
                                $departments
                                as $item
                            ): ?>

                                <option
                                    value="<?= e(
                                        $item['department']
                                    ) ?>"
                                    <?= $department ===
                                        $item['department']
                                            ? 'selected'
                                            : ''
                                    ?>
                                >

                                    <?= e(
                                        $item['department']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


                    <div class="filter-actions">

                        <button
                            type="submit"
                            class="dashboard-primary-button"
                        >
                            Search
                        </button>


                        <a
                            href="teachers.php"
                            class="dashboard-secondary-button"
                        >
                            Clear
                        </a>

                    </div>


                </form>


            </div>


            <!-- =================================================
                 TEACHER LIST
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">


                    <div>

                        <span class="page-header-label">
                            TEACHER RECORDS
                        </span>

                        <h2>
                            Teacher List
                        </h2>

                    </div>


                    <span class="record-count">

                        <?= count($teachers) ?>

                        teacher<?= count(
                            $teachers
                        ) === 1
                            ? ''
                            : 's'
                        ?>

                    </span>


                </div>


                <?php if (
                    empty($teachers)
                ): ?>


                    <div class="table-empty-state">


                        <div class="empty-state-icon">
                            TR
                        </div>


                        <h3>
                            No teachers found
                        </h3>


                        <p>

                            <?php if (
                                $search !== '' ||
                                $department !== ''
                            ): ?>

                                No teachers match
                                your current filters.

                            <?php else: ?>

                                There are no teacher accounts
                                in the system yet.

                            <?php endif; ?>

                        </p>


                        <?php if (
                            $search !== '' ||
                            $department !== ''
                        ): ?>

                            <a
                                href="teachers.php"
                                class="dashboard-secondary-button"
                            >
                                Clear Filters
                            </a>

                        <?php else: ?>

                            <a
                                href="add-teacher.php"
                                class="dashboard-primary-button"
                            >
                                + Add First Teacher
                            </a>

                        <?php endif; ?>


                    </div>


                <?php else: ?>


                    <div class="table-wrapper">


                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        Teacher
                                    </th>

                                    <th>
                                        Employee No.
                                    </th>

                                    <th>
                                        Department
                                    </th>

                                    <th>
                                        Phone
                                    </th>

                                    <th>
                                        Assignments
                                    </th>

                                    <th>
                                        Attendance
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $teachers
                                    as $teacher
                                ): ?>


                                    <tr>


                                        <!-- Teacher -->

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
                                                                $teacher['name'],
                                                                0,
                                                                1
                                                            )
                                                        )
                                                    ) ?>

                                                </div>


                                                <div>

                                                    <div
                                                        class="table-primary-text"
                                                    >

                                                        <?= e(
                                                            $teacher['name']
                                                        ) ?>

                                                    </div>


                                                    <span
                                                        class="table-secondary-text"
                                                    >

                                                        <?= e(
                                                            $teacher['email']
                                                        ) ?>

                                                    </span>

                                                </div>

                                            </div>

                                        </td>


                                        <!-- Employee -->

                                        <td>

                                            <span class="table-code">

                                                <?= e(
                                                    $teacher[
                                                        'employee_number'
                                                    ]
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Department -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $teacher[
                                                        'department'
                                                    ]
                                                )
                                            ): ?>

                                                <?= e(
                                                    $teacher[
                                                        'department'
                                                    ]
                                                ) ?>

                                            <?php else: ?>

                                                <span
                                                    class="table-secondary-text"
                                                >
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Phone -->

                                        <td>

                                            <?php if (
                                                !empty(
                                                    $teacher['phone']
                                                )
                                            ): ?>

                                                <?= e(
                                                    $teacher['phone']
                                                ) ?>

                                            <?php else: ?>

                                                <span
                                                    class="table-secondary-text"
                                                >
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- Assignments -->

                                        <td>

                                            <span
                                                class="table-count"
                                            >

                                                <?= (int)
                                                    $teacher[
                                                        'assignment_count'
                                                    ] ?>

                                            </span>

                                        </td>


                                        <!-- Attendance -->

                                        <td>

                                            <span
                                                class="table-count"
                                            >

                                                <?= (int)
                                                    $teacher[
                                                        'attendance_count'
                                                    ] ?>

                                            </span>

                                        </td>


                                        <!-- Actions -->

                                        <td>


                                            <div
                                                class="table-actions"
                                            >


                                                <a
                                                    href="add-teacher.php?edit=<?= (int) $teacher['teacher_id'] ?>"
                                                    class="table-action-link"
                                                >
                                                    Manage
                                                </a>


                                                <?php if (
                                                    (int) $teacher[
                                                        'assignment_count'
                                                    ] === 0 &&
                                                    (int) $teacher[
                                                        'attendance_count'
                                                    ] === 0
                                                ): ?>


                                                    <form
                                                        method="POST"
                                                        action="teachers.php"
                                                        onsubmit="return confirm('Are you sure you want to delete this teacher account?');"
                                                    >


                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e(
                                                                $csrfToken
                                                            ) ?>"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="delete"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="teacher_id"
                                                            value="<?= (int) $teacher['teacher_id'] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="table-action-button danger"
                                                        >
                                                            Delete
                                                        </button>


                                                    </form>


                                                <?php else: ?>


                                                    <span
                                                        class="table-disabled-action"
                                                    >
                                                        In Use
                                                    </span>


                                                <?php endif; ?>


                                            </div>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php endif; ?>


            </div>


        </div>


    </main>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>