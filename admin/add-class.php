<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Add Class | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css'
];

$error = '';

$name = '';
$code = '';
$academicYear = '';


/*
|--------------------------------------------------------------------------
| Handle Form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $academicYear = trim($_POST['academic_year'] ?? '');

    $csrfToken = $_POST['csrf_token'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    if (!verifyCsrfToken($csrfToken)) {

        $error =
            'Invalid security token. Please refresh the page and try again.';

    } elseif ($name === '') {

        $error =
            'Please enter the class name.';

    } elseif ($code === '') {

        $error =
            'Please enter the class code.';

    } elseif (!preg_match('/^[A-Z0-9_-]+$/', $code)) {

        $error =
            'Class code may contain only letters, numbers, hyphens and underscores.';

    } elseif ($academicYear === '') {

        $error =
            'Please enter the academic year.';

    } elseif (!preg_match('/^[0-9]{4}$/', $academicYear)) {

        $error =
            'Academic year must be a four-digit year.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Code
            |--------------------------------------------------------------------------
            */

            $checkCode = $pdo->prepare(
                "
                SELECT id
                FROM classes
                WHERE code = ?
                LIMIT 1
                "
            );

            $checkCode->execute([
                $code
            ]);

            if ($checkCode->fetch()) {

                $error =
                    'A class with this code already exists.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Insert Class
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
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

                $stmt->execute([
                    $name,
                    $code,
                    $academicYear
                ]);


                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

                redirect(
                    '/student-attendance-system/admin/classes.php'
                );
            }

        } catch (PDOException $e) {

            $error =
                'Unable to create the class. Please try again.';
        }
    }
}


$csrfToken = csrfToken();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">

        <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

        <div class="dashboard-content">


            <!-- Page Header -->

            <div class="page-header">

                <div>

                    <span class="page-header-label">
                        CLASS MANAGEMENT
                    </span>

                    <h1>
                        Add Class
                    </h1>

                    <p>
                        Create a new school class and academic year.
                    </p>

                </div>

                <div class="page-header-actions">

                    <a
                        href="classes.php"
                        class="dashboard-secondary-button"
                    >
                        ← Back to Classes
                    </a>

                </div>

            </div>


            <!-- Error -->

            <?php if ($error !== ''): ?>

                <div class="table-message error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- Form -->

            <div class="form-panel">

                <div class="form-panel-header">

                    <div>

                        <h2>
                            Class Information
                        </h2>

                        <p>
                            Enter the basic information for the class.
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    action="add-class.php"
                    class="system-form"
                >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e(csrfToken()) ?>"
                    >


                    <!-- Class Name -->

                    <div class="form-grid">

                        <div class="form-group">

                            <label
                                for="name"
                                class="form-label"
                            >
                                Class Name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form-input"
                                value="<?= e($name) ?>"
                                placeholder="e.g. Form Four"
                                maxlength="100"
                                required
                            >

                            <small class="form-help">
                                Enter the full name of the class.
                            </small>

                        </div>


                        <!-- Class Code -->

                        <div class="form-group">

                            <label
                                for="code"
                                class="form-label"
                            >
                                Class Code
                            </label>

                            <input
                                type="text"
                                id="code"
                                name="code"
                                class="form-input"
                                value="<?= e($code) ?>"
                                placeholder="e.g. F4"
                                maxlength="30"
                                required
                            >

                            <small class="form-help">
                                Use a short unique code.
                            </small>

                        </div>


                        <!-- Academic Year -->

                        <div class="form-group">

                            <label
                                for="academic_year"
                                class="form-label"
                            >
                                Academic Year
                            </label>

                            <input
                                type="text"
                                id="academic_year"
                                name="academic_year"
                                class="form-input"
                                value="<?= e($academicYear) ?>"
                                placeholder="e.g. 2026"
                                maxlength="4"
                                inputmode="numeric"
                                required
                            >

                            <small class="form-help">
                                Enter the four-digit academic year.
                            </small>

                        </div>

                    </div>


                    <!-- Actions -->

                    <div class="form-actions">

                        <a
                            href="classes.php"
                            class="dashboard-secondary-button"
                        >
                            Cancel
                        </a>

                        <button
                            type="submit"
                            class="dashboard-primary-button"
                        >
                            Save Class
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </main>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
