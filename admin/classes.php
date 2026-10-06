<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Classes | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css',
    '/student-attendance-system/assets/css/forms.css'
];

$search = trim($_GET['search'] ?? '');
$academicYear = trim($_GET['academic_year'] ?? '');

$error = '';
$success = '';

$editMode = false;
$editClass = null;


/*
|--------------------------------------------------------------------------
| POST Actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {

        $error = 'Invalid security token. Please try again.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | ADD CLASS
        |--------------------------------------------------------------------------
        */

        if ($action === 'add') {

            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $year = trim($_POST['academic_year'] ?? '');

            if ($name === '') {

                $error = 'Please enter the class name.';

            } elseif ($code === '') {

                $error = 'Please enter the class code.';

            } elseif ($year === '') {

                $error = 'Please enter the academic year.';

            } else {

                try {

                    $check = $pdo->prepare(
                        "
                        SELECT id
                        FROM classes
                        WHERE code = ?
                        LIMIT 1
                        "
                    );

                    $check->execute([$code]);

                    if ($check->fetch()) {

                        $error =
                            'A class with this code already exists.';

                    } else {

                        $insert = $pdo->prepare(
                            "
                            INSERT INTO classes
                            (
                                name,
                                code,
                                academic_year
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?
                            )
                            "
                        );

                        $insert->execute([
                            $name,
                            $code,
                            $year
                        ]);

                        $success =
                            'Class added successfully.';
                    }

                } catch (PDOException $e) {

                    $error =
                        'Unable to add the class.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE CLASS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update') {

            $classId = (int) ($_POST['class_id'] ?? 0);

            $name = trim($_POST['name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $year = trim($_POST['academic_year'] ?? '');

            if ($classId <= 0) {

                $error = 'Invalid class selected.';

            } elseif ($name === '') {

                $error = 'Please enter the class name.';

            } elseif ($code === '') {

                $error = 'Please enter the class code.';

            } elseif ($year === '') {

                $error = 'Please enter the academic year.';

            } else {

                try {

                    $check = $pdo->prepare(
                        "
                        SELECT id
                        FROM classes
                        WHERE code = ?
                        AND id != ?
                        LIMIT 1
                        "
                    );

                    $check->execute([
                        $code,
                        $classId
                    ]);

                    if ($check->fetch()) {

                        $error =
                            'Another class already uses this code.';

                    } else {

                        $update = $pdo->prepare(
                            "
                            UPDATE classes
                            SET
                                name = ?,
                                code = ?,
                                academic_year = ?
                            WHERE id = ?
                            LIMIT 1
                            "
                        );

                        $update->execute([
                            $name,
                            $code,
                            $year,
                            $classId
                        ]);

                        if ($update->rowCount() > 0) {

                            $success =
                                'Class updated successfully.';

                        } else {

                            $success =
                                'Class information is already up to date.';
                        }
                    }

                } catch (PDOException $e) {

                    $error =
                        'Unable to update the class.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE CLASS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'delete') {

            $classId = (int) ($_POST['class_id'] ?? 0);

            if ($classId <= 0) {

                $error = 'Invalid class selected.';

            } else {

                try {

                    /*
                    | Check students
                    */

                    $studentCheck = $pdo->prepare(
                        "
                        SELECT COUNT(*)
                        FROM students
                        WHERE class_id = ?
                        "
                    );

                    $studentCheck->execute([$classId]);

                    $studentCount =
                        (int) $studentCheck->fetchColumn();


                    /*
                    | Check teaching assignments
                    */

                    $assignmentCheck = $pdo->prepare(
                        "
                        SELECT COUNT(*)
                        FROM teaching_assignments
                        WHERE class_id = ?
                        "
                    );

                    $assignmentCheck->execute([$classId]);

                    $assignmentCount =
                        (int) $assignmentCheck->fetchColumn();


                    if (
                        $studentCount > 0 ||
                        $assignmentCount > 0
                    ) {

                        $error =
                            'This class cannot be deleted because it is already in use.';

                    } else {

                        $delete = $pdo->prepare(
                            "
                            DELETE FROM classes
                            WHERE id = ?
                            LIMIT 1
                            "
                        );

                        $delete->execute([$classId]);

                        if ($delete->rowCount() > 0) {

                            $success =
                                'Class deleted successfully.';

                        } else {

                            $error =
                                'The selected class was not found.';
                        }
                    }

                } catch (PDOException $e) {

                    $error =
                        'Unable to delete the class.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Class For Editing
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
            code,
            academic_year
        FROM classes
        WHERE id = ?
        LIMIT 1
        "
    );

    $editStmt->execute([$editId]);

    $editClass = $editStmt->fetch();

    if ($editClass) {

        $editMode = true;
    }
}


/*
|--------------------------------------------------------------------------
| Load Classes
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        c.id,
        c.name,
        c.code,
        c.academic_year,
        c.created_at,

        (
            SELECT COUNT(*)
            FROM students s
            WHERE s.class_id = c.id
        ) AS student_count

    FROM classes c
";

$where = [];
$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $where[] = "
        (
            c.name LIKE ?
            OR c.code LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Academic Year
|--------------------------------------------------------------------------
*/

if ($academicYear !== '') {

    $where[] = 'c.academic_year = ?';

    $params[] = $academicYear;
}


if (!empty($where)) {

    $sql .=
        ' WHERE ' .
        implode(' AND ', $where);
}


$sql .= "
    ORDER BY
        c.academic_year DESC,
        c.name ASC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$classes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Academic Years
|--------------------------------------------------------------------------
*/

$yearStmt = $pdo->query(
    "
    SELECT DISTINCT academic_year
    FROM classes
    ORDER BY academic_year DESC
    "
);

$academicYears = $yearStmt->fetchAll();

$csrfToken = csrfToken();


require_once __DIR__ . '/../includes/header.php';

?>

<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


    <main class="dashboard-main">

        <?php require_once __DIR__ . '/../includes/navbar.php'; ?>


        <div class="dashboard-content">


            <!-- PAGE HEADER -->

            <div class="page-header">

                <div>

                    <span class="page-header-label">
                        CLASS MANAGEMENT
                    </span>

                    <h1>
                        Classes
                    </h1>

                    <p>
                        Create and manage school classes and academic years.
                    </p>

                </div>

                <div class="page-header-actions">

                    <a
                        href="classes.php#class-form"
                        class="dashboard-primary-button"
                    >
                        + Add Class
                    </a>

                </div>

            </div>


            <!-- MESSAGES -->

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


            <!-- ADD / EDIT FORM -->

            <div
                class="table-panel class-form-panel"
                id="class-form"
            >

                <div class="table-panel-header">

                    <div>

                        <span class="page-header-label">
                            <?= $editMode
                                ? 'EDIT CLASS'
                                : 'NEW CLASS'
                            ?>
                        </span>

                        <h2>
                            <?= $editMode
                                ? 'Edit Class'
                                : 'Add New Class'
                            ?>
                        </h2>

                    </div>

                </div>


                <form
                    method="POST"
                    action="classes.php<?= $editMode
                        ? '?edit=' . (int) $editClass['id']
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
                            name="class_id"
                            value="<?= (int) $editClass['id'] ?>"
                        >

                    <?php endif; ?>


                    <div class="form-grid">


                        <div class="form-group">

                            <label for="name">
                                Class Name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                placeholder="e.g. Form One A"
                                value="<?= $editMode
                                    ? e($editClass['name'])
                                    : ''
                                ?>"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="code">
                                Class Code
                            </label>

                            <input
                                type="text"
                                id="code"
                                name="code"
                                placeholder="e.g. F1A"
                                value="<?= $editMode
                                    ? e($editClass['code'])
                                    : ''
                                ?>"
                                maxlength="30"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="academic_year">
                                Academic Year
                            </label>

                            <input
                                type="text"
                                id="academic_year"
                                name="academic_year"
                                placeholder="e.g. 2026"
                                value="<?= $editMode
                                    ? e($editClass['academic_year'])
                                    : date('Y')
                                ?>"
                                maxlength="20"
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
                                ? 'Update Class'
                                : 'Save Class'
                            ?>
                        </button>


                        <?php if ($editMode): ?>

                            <a
                                href="classes.php"
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


            <!-- FILTERS -->

            <div class="table-panel">

                <form
                    method="GET"
                    action="classes.php"
                    class="student-filter-form"
                >

                    <div class="form-filter-group">

                        <label for="search">
                            Search
                        </label>

                        <input
                            type="search"
                            id="search"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Class name or code"
                        >

                    </div>


                    <div class="form-filter-group">

                        <label for="filter_academic_year">
                            Academic Year
                        </label>

                        <select
                            id="filter_academic_year"
                            name="academic_year"
                        >

                            <option value="">
                                All Years
                            </option>

                            <?php foreach ($academicYears as $year): ?>

                                <option
                                    value="<?= e($year['academic_year']) ?>"
                                    <?= $academicYear === $year['academic_year']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= e($year['academic_year']) ?>
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
                            href="classes.php"
                            class="dashboard-secondary-button"
                        >
                            Clear
                        </a>

                    </div>

                </form>

            </div>


            <!-- CLASS LIST -->

            <div class="table-panel">

                <div class="table-panel-header">

                    <div>

                        <h2>
                            Class List
                        </h2>

                        <span class="record-count">

                            <?= count($classes) ?>

                            class<?= count($classes) === 1
                                ? ''
                                : 'es'
                            ?>

                        </span>

                    </div>

                </div>


                <?php if (empty($classes)): ?>

                    <div class="table-empty-state">

                        <div class="empty-state-icon">
                            CL
                        </div>

                        <h3>
                            No classes found
                        </h3>

                        <p>
                            Create your first school class
                            to start managing students.
                        </p>

                        <a
                            href="classes.php#class-form"
                            class="dashboard-primary-button"
                        >
                            + Add First Class
                        </a>

                    </div>

                <?php else: ?>

                    <div class="table-wrapper">

                        <table class="data-table">

                            <thead>

                                <tr>

                                    <th>
                                        Class
                                    </th>

                                    <th>
                                        Code
                                    </th>

                                    <th>
                                        Academic Year
                                    </th>

                                    <th>
                                        Students
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

                                <?php foreach ($classes as $class): ?>

                                    <tr>

                                        <td>

                                            <div class="table-primary-text">

                                                <?= e($class['name']) ?>

                                            </div>

                                        </td>


                                        <td>

                                            <span class="table-code">

                                                <?= e($class['code']) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= e($class['academic_year']) ?>

                                        </td>


                                        <td>

                                            <span class="table-count">

                                                <?= (int) $class['student_count'] ?>

                                            </span>

                                        </td>


                                        <td>

                                            <span class="table-secondary-text">

                                                <?= e(
                                                    date(
                                                        'd M Y',
                                                        strtotime(
                                                            $class['created_at']
                                                        )
                                                    )
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <div class="table-actions">

                                                <a
                                                    href="classes.php?edit=<?= (int) $class['id'] ?>#class-form"
                                                    class="table-action-link"
                                                >
                                                    Edit
                                                </a>


                                                <?php if (
                                                    (int) $class['student_count'] === 0
                                                ): ?>

                                                    <form
                                                        method="POST"
                                                        action="classes.php"
                                                        onsubmit="return confirm('Are you sure you want to delete this class?');"
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
                                                            name="class_id"
                                                            value="<?= (int) $class['id'] ?>"
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


<?php require_once __DIR__ . '/../includes/footer.php'; ?>