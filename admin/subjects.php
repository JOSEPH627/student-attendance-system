<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Subjects | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css',
    '/student-attendance-system/assets/css/forms.css'
];


$search = trim($_GET['search'] ?? '');

$error = '';
$success = '';

$editMode = false;
$editSubject = null;


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    $csrfToken = $_POST['csrf_token'] ?? '';


    if (!verifyCsrfToken($csrfToken)) {

        $error =
            'Invalid security token. Please try again.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | ADD SUBJECT
        |--------------------------------------------------------------------------
        */

        if ($action === 'add') {

            $name = trim($_POST['name'] ?? '');

            $code = strtoupper(
                trim($_POST['code'] ?? '')
            );


            if ($name === '') {

                $error =
                    'Please enter the subject name.';

            } elseif ($code === '') {

                $error =
                    'Please enter the subject code.';

            } elseif (strlen($code) > 30) {

                $error =
                    'Subject code cannot exceed 30 characters.';

            } else {

                try {

                    /*
                    | Check duplicate code
                    */

                    $checkCode = $pdo->prepare(
                        "
                        SELECT id
                        FROM subjects
                        WHERE code = ?
                        LIMIT 1
                        "
                    );

                    $checkCode->execute([
                        $code
                    ]);


                    if ($checkCode->fetch()) {

                        $error =
                            'A subject with this code already exists.';

                    } else {

                        /*
                        | Insert subject
                        */

                        $insert = $pdo->prepare(
                            "
                            INSERT INTO subjects
                            (
                                name,
                                code
                            )
                            VALUES
                            (
                                ?,
                                ?
                            )
                            "
                        );

                        $insert->execute([
                            $name,
                            $code
                        ]);


                        $success =
                            'Subject added successfully.';
                    }


                } catch (PDOException $e) {

                    $error =
                        'Unable to add the subject.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE SUBJECT
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update') {

            $subjectId = (int) (
                $_POST['subject_id'] ?? 0
            );

            $name = trim(
                $_POST['name'] ?? ''
            );

            $code = strtoupper(
                trim($_POST['code'] ?? '')
            );


            if ($subjectId <= 0) {

                $error =
                    'Invalid subject selected.';

            } elseif ($name === '') {

                $error =
                    'Please enter the subject name.';

            } elseif ($code === '') {

                $error =
                    'Please enter the subject code.';

            } elseif (strlen($code) > 30) {

                $error =
                    'Subject code cannot exceed 30 characters.';

            } else {

                try {

                    /*
                    | Check duplicate code
                    */

                    $checkCode = $pdo->prepare(
                        "
                        SELECT id
                        FROM subjects
                        WHERE code = ?
                        AND id != ?
                        LIMIT 1
                        "
                    );

                    $checkCode->execute([
                        $code,
                        $subjectId
                    ]);


                    if ($checkCode->fetch()) {

                        $error =
                            'Another subject already uses this code.';

                    } else {

                        /*
                        | Update subject
                        */

                        $update = $pdo->prepare(
                            "
                            UPDATE subjects
                            SET
                                name = ?,
                                code = ?
                            WHERE id = ?
                            LIMIT 1
                            "
                        );

                        $update->execute([
                            $name,
                            $code,
                            $subjectId
                        ]);


                        if ($update->rowCount() > 0) {

                            $success =
                                'Subject updated successfully.';

                        } else {

                            $success =
                                'Subject information is already up to date.';
                        }
                    }


                } catch (PDOException $e) {

                    $error =
                        'Unable to update the subject.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE SUBJECT
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            $subjectId = (int) (
                $_POST['subject_id'] ?? 0
            );


            if ($subjectId <= 0) {

                $error =
                    'Invalid subject selected.';

            } else {

                try {

                    /*
                    | Check teaching assignments
                    */

                    $assignmentCheck = $pdo->prepare(
                        "
                        SELECT COUNT(*)
                        FROM teaching_assignments
                        WHERE subject_id = ?
                        "
                    );

                    $assignmentCheck->execute([
                        $subjectId
                    ]);

                    $assignmentCount =
                        (int) $assignmentCheck->fetchColumn();


                    /*
                    | Check attendance records
                    */

                    $attendanceCheck = $pdo->prepare(
                        "
                        SELECT COUNT(*)
                        FROM attendance
                        WHERE subject_id = ?
                        "
                    );

                    $attendanceCheck->execute([
                        $subjectId
                    ]);

                    $attendanceCount =
                        (int) $attendanceCheck->fetchColumn();


                    if (
                        $assignmentCount > 0 ||
                        $attendanceCount > 0
                    ) {

                        $error =
                            'This subject cannot be deleted because it is already in use.';

                    } else {

                        $delete = $pdo->prepare(
                            "
                            DELETE FROM subjects
                            WHERE id = ?
                            LIMIT 1
                            "
                        );

                        $delete->execute([
                            $subjectId
                        ]);


                        if ($delete->rowCount() > 0) {

                            $success =
                                'Subject deleted successfully.';

                        } else {

                            $error =
                                'The selected subject was not found.';
                        }
                    }


                } catch (PDOException $e) {

                    $error =
                        'Unable to delete the subject.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Subject For Editing
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['edit']) &&
    (int) $_GET['edit'] > 0
) {

    $editId = (int) $_GET['edit'];


    $editStmt = $pdo->prepare(
        "
        SELECT
            id,
            name,
            code
        FROM subjects
        WHERE id = ?
        LIMIT 1
        "
    );

    $editStmt->execute([
        $editId
    ]);

    $editSubject =
        $editStmt->fetch();


    if ($editSubject) {

        $editMode = true;
    }
}


/*
|--------------------------------------------------------------------------
| Load Subjects
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id,
        s.name,
        s.code,
        s.created_at,

        (
            SELECT COUNT(*)
            FROM teaching_assignments ta
            WHERE ta.subject_id = s.id
        ) AS assignment_count,

        (
            SELECT COUNT(*)
            FROM attendance a
            WHERE a.subject_id = s.id
        ) AS attendance_count

    FROM subjects s
";


$params = [];


if ($search !== '') {

    $sql .= "
        WHERE
            s.name LIKE ?
            OR s.code LIKE ?
    ";

    $searchValue =
        '%' . $search . '%';

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;
}


$sql .= "
    ORDER BY
        s.name ASC
";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$subjects =
    $stmt->fetchAll();


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
                        SUBJECT MANAGEMENT
                    </span>

                    <h1>
                        Subjects
                    </h1>

                    <p>
                        Create and manage school subjects.
                    </p>

                </div>


                <div class="page-header-actions">

                    <a
                        href="subjects.php#subject-form"
                        class="dashboard-primary-button"
                    >
                        + Add Subject
                    </a>

                </div>

            </div>


            <!-- =================================================
                 MESSAGES
                 ================================================= -->

            <?php if ($success !== ''): ?>

                <div class="table-message success">

                    <?= e($success) ?>

                </div>

            <?php endif; ?>


            <?php if ($error !== ''): ?>

                <div class="table-message error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 ADD / EDIT SUBJECT
                 ================================================= -->

            <div
                class="table-panel class-form-panel"
                id="subject-form"
            >


                <div class="table-panel-header">

                    <div>

                        <span class="page-header-label">

                            <?= $editMode
                                ? 'EDIT SUBJECT'
                                : 'NEW SUBJECT'
                            ?>

                        </span>


                        <h2>

                            <?= $editMode
                                ? 'Edit Subject'
                                : 'Add New Subject'
                            ?>

                        </h2>

                    </div>

                </div>


                <form
                    method="POST"
                    action="subjects.php<?= $editMode
                        ? '?edit=' .
                          (int) $editSubject['id']
                        : ''
                    ?>"
                    class="dashboard-form"
                >


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >


                    <input
                        type="hidden"
                        name="action"
                        value="<?= $editMode
                            ? 'update'
                            : 'add'
                        ?>"
                    >


                    <?php if ($editMode): ?>

                        <input
                            type="hidden"
                            name="subject_id"
                            value="<?= (int) $editSubject['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="form-grid">


                        <div class="form-group">

                            <label for="name">
                                Subject Name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                placeholder="e.g. Mathematics"
                                value="<?= $editMode
                                    ? e($editSubject['name'])
                                    : ''
                                ?>"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="code">
                                Subject Code
                            </label>

                            <input
                                type="text"
                                id="code"
                                name="code"
                                placeholder="e.g. MATH"
                                value="<?= $editMode
                                    ? e($editSubject['code'])
                                    : ''
                                ?>"
                                maxlength="30"
                                required
                            >

                        </div>


                    </div>


                    <div class="form-actions">


                        <button
                            type="submit"
                            class="dashboard-primary-button"
                        >

                            <?= $editMode
                                ? 'Update Subject'
                                : 'Save Subject'
                            ?>

                        </button>


                        <?php if ($editMode): ?>

                            <a
                                href="subjects.php"
                                class="dashboard-secondary-button"
                            >
                                Cancel Edit
                            </a>

                        <?php else: ?>

                            <button
                                type="reset"
                                class="dashboard-secondary-button"
                            >
                                Clear
                            </button>

                        <?php endif; ?>


                    </div>


                </form>


            </div>


            <!-- =================================================
                 SEARCH
                 ================================================= -->

            <div class="table-panel">


                <form
                    method="GET"
                    action="subjects.php"
                    class="student-filter-form"
                >


                    <div class="form-filter-group">

                        <label for="search">
                            Search Subjects
                        </label>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Subject name or code"
                        >

                    </div>


                    <div class="filter-actions">


                        <button
                            type="submit"
                            class="dashboard-primary-button"
                        >
                            Search
                        </button>


                        <a
                            href="subjects.php"
                            class="dashboard-secondary-button"
                        >
                            Clear
                        </a>


                    </div>


                </form>


            </div>


            <!-- =================================================
                 SUBJECT LIST
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">


                    <div>

                        <span class="page-header-label">
                            SUBJECT RECORDS
                        </span>

                        <h2>
                            Subject List
                        </h2>

                    </div>


                    <span class="record-count">

                        <?= count($subjects) ?>

                        subject<?= count($subjects) === 1
                            ? ''
                            : 's'
                        ?>

                    </span>


                </div>


                <?php if (empty($subjects)): ?>


                    <div class="table-empty-state">


                        <div class="empty-state-icon">
                            SB
                        </div>


                        <h3>
                            No subjects found
                        </h3>


                        <p>

                            <?php if ($search !== ''): ?>

                                No subjects match
                                your current search.

                            <?php else: ?>

                                There are no subjects
                                in the system yet.

                            <?php endif; ?>

                        </p>


                        <?php if ($search !== ''): ?>

                            <a
                                href="subjects.php"
                                class="dashboard-secondary-button"
                            >
                                Clear Search
                            </a>

                        <?php else: ?>

                            <a
                                href="subjects.php#subject-form"
                                class="dashboard-primary-button"
                            >
                                + Add First Subject
                            </a>

                        <?php endif; ?>


                    </div>


                <?php else: ?>


                    <div class="table-wrapper">


                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Code
                                    </th>

                                    <th>
                                        Usage
                                    </th>

                                    <th>
                                        Created
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $subjects
                                    as $subject
                                ): ?>


                                    <tr>


                                        <!-- Subject -->

                                        <td>

                                            <div class="table-primary-text">

                                                <?= e(
                                                    $subject['name']
                                                ) ?>

                                            </div>

                                        </td>


                                        <!-- Code -->

                                        <td>

                                            <span class="table-code">

                                                <?= e(
                                                    $subject['code']
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Usage -->

                                        <td>

                                            <?php

                                            $assignmentCount =
                                                (int)
                                                $subject[
                                                    'assignment_count'
                                                ];

                                            $attendanceCount =
                                                (int)
                                                $subject[
                                                    'attendance_count'
                                                ];

                                            ?>


                                            <?php if (
                                                $assignmentCount > 0 ||
                                                $attendanceCount > 0
                                            ): ?>

                                                <span
                                                    class="status-badge status-active"
                                                >
                                                    In Use
                                                </span>

                                            <?php else: ?>

                                                <span
                                                    class="status-badge status-inactive"
                                                >
                                                    Not Used
                                                </span>

                                            <?php endif; ?>


                                        </td>


                                        <!-- Created -->

                                        <td>

                                            <span class="table-secondary-text">

                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $subject[
                                                                'created_at'
                                                            ]
                                                        )
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <!-- Actions -->

                                        <td>


                                            <div class="table-actions">


                                                <a
                                                    href="subjects.php?edit=<?= (int) $subject['id'] ?>#subject-form"
                                                    class="table-action-link"
                                                >
                                                    Edit
                                                </a>


                                                <?php if (
                                                    $assignmentCount === 0 &&
                                                    $attendanceCount === 0
                                                ): ?>


                                                    <form
                                                        method="POST"
                                                        action="subjects.php"
                                                        onsubmit="return confirm('Are you sure you want to delete this subject?');"
                                                    >


                                                        <input
                                                            type="hidden"
                                                            name="csrf_token"
                                                            value="<?= e($csrfToken) ?>"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="delete"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="subject_id"
                                                            value="<?= (int) $subject['id'] ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            class="table-action-button danger"
                                                        >
                                                            Delete
                                                        </button>


                                                    </form>


                                                <?php else: ?>


                                                    <span class="table-disabled-action">

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
