<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Add Student | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/forms.css'
];


$error = '';

$old = [
    'admission_number' => '',
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'gender' => '',
    'date_of_birth' => '',
    'class_id' => '',
    'status' => 'ACTIVE'
];


/*
|--------------------------------------------------------------------------
| Load Classes
|--------------------------------------------------------------------------
*/

$classStmt = $pdo->query(
    "
    SELECT
        id,
        name,
        code,
        academic_year
    FROM classes
    ORDER BY
        academic_year DESC,
        name ASC
    "
);

$classes = $classStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Generate Student Reference
|--------------------------------------------------------------------------
*/

$studentReference = generateStudentReference();


/*
|--------------------------------------------------------------------------
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {

        $error =
            'Invalid security token. Please try again.';

    } else {

        /*
        | Collect form values
        */

        $old['admission_number'] =
            trim($_POST['admission_number'] ?? '');

        $old['first_name'] =
            trim($_POST['first_name'] ?? '');

        $old['middle_name'] =
            trim($_POST['middle_name'] ?? '');

        $old['last_name'] =
            trim($_POST['last_name'] ?? '');

        $old['gender'] =
            strtoupper(
                trim($_POST['gender'] ?? '')
            );

        $old['date_of_birth'] =
            trim($_POST['date_of_birth'] ?? '');

        $old['class_id'] =
            (int) ($_POST['class_id'] ?? 0);

        $old['status'] =
            strtoupper(
                trim($_POST['status'] ?? 'ACTIVE')
            );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($old['admission_number'] === '') {

            $error =
                'Please enter the admission number.';

        } elseif ($old['first_name'] === '') {

            $error =
                'Please enter the first name.';

        } elseif ($old['last_name'] === '') {

            $error =
                'Please enter the last name.';

        } elseif (
            !in_array(
                $old['gender'],
                ['MALE', 'FEMALE'],
                true
            )
        ) {

            $error =
                'Please select a valid gender.';

        } elseif ($old['class_id'] <= 0) {

            $error =
                'Please select a class.';

        } elseif (
            !in_array(
                $old['status'],
                ['ACTIVE', 'INACTIVE'],
                true
            )
        ) {

            $error =
                'Please select a valid student status.';

        }


        /*
        |--------------------------------------------------------------------------
        | Validate Date
        |--------------------------------------------------------------------------
        */

        if (
            $error === '' &&
            $old['date_of_birth'] !== ''
        ) {

            $dateObject = DateTime::createFromFormat(
                'Y-m-d',
                $old['date_of_birth']
            );

            $validDate =
                $dateObject &&
                $dateObject->format('Y-m-d') ===
                $old['date_of_birth'];

            if (!$validDate) {

                $error =
                    'Please enter a valid date of birth.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Check Class
        |--------------------------------------------------------------------------
        */

        if (
            $error === '' &&
            $old['class_id'] > 0
        ) {

            $classCheck = $pdo->prepare(
                "
                SELECT id
                FROM classes
                WHERE id = ?
                LIMIT 1
                "
            );

            $classCheck->execute([
                $old['class_id']
            ]);

            if (!$classCheck->fetch()) {

                $error =
                    'The selected class does not exist.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Check Admission Number
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $admissionCheck = $pdo->prepare(
                "
                SELECT id
                FROM students
                WHERE admission_number = ?
                LIMIT 1
                "
            );

            $admissionCheck->execute([
                $old['admission_number']
            ]);

            if ($admissionCheck->fetch()) {

                $error =
                    'A student with this admission number already exists.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Handle Photo Upload
        |--------------------------------------------------------------------------
        */

        $photoPath = null;

        if (
            $error === '' &&
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            $photo = $_FILES['photo'];


            if (
                $photo['error'] !== UPLOAD_ERR_OK
            ) {

                $error =
                    'Unable to upload the student photo.';

            } elseif (
                $photo['size'] > 2 * 1024 * 1024
            ) {

                $error =
                    'Student photo must not exceed 2 MB.';

            } else {

                $allowedMimeTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                $fileInfo =
                    new finfo(FILEINFO_MIME_TYPE);

                $mimeType =
                    $fileInfo->file(
                        $photo['tmp_name']
                    );


                if (
                    !isset(
                        $allowedMimeTypes[$mimeType]
                    )
                ) {

                    $error =
                        'Only JPG, PNG and WEBP images are allowed.';

                } else {

                    $uploadDirectory =
                        __DIR__ .
                        '/../assets/uploads/students/';


                    if (
                        !is_dir($uploadDirectory)
                    ) {

                        mkdir(
                            $uploadDirectory,
                            0755,
                            true
                        );
                    }


                    $fileName =
                        bin2hex(
                            random_bytes(16)
                        ) .
                        '.' .
                        $allowedMimeTypes[$mimeType];


                    $destination =
                        $uploadDirectory .
                        $fileName;


                    if (
                        !move_uploaded_file(
                            $photo['tmp_name'],
                            $destination
                        )
                    ) {

                        $error =
                            'Unable to save the student photo.';

                    } else {

                        $photoPath =
                            'assets/uploads/students/' .
                            $fileName;
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Insert Student
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            try {

                $insert = $pdo->prepare(
                    "
                    INSERT INTO students
                    (
                        student_reference,
                        admission_number,
                        first_name,
                        middle_name,
                        last_name,
                        gender,
                        date_of_birth,
                        class_id,
                        photo_path,
                        status
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                    "
                );


                $insert->execute([
                    $studentReference,
                    $old['admission_number'],
                    $old['first_name'],
                    $old['middle_name'] !== ''
                        ? $old['middle_name']
                        : null,
                    $old['last_name'],
                    $old['gender'],
                    $old['date_of_birth'] !== ''
                        ? $old['date_of_birth']
                        : null,
                    $old['class_id'],
                    $photoPath,
                    $old['status']
                ]);


                setFlashMessage(
                    'success',
                    'Student added successfully.'
                );


                redirect(
                    '/student-attendance-system/admin/students.php'
                );


            } catch (PDOException $e) {

                /*
                | Remove uploaded photo if database insert fails
                */

                if (
                    $photoPath !== null
                ) {

                    $uploadedFile =
                        __DIR__ .
                        '/../' .
                        $photoPath;

                    if (
                        file_exists(
                            $uploadedFile
                        )
                    ) {

                        unlink($uploadedFile);
                    }
                }


                $error =
                    'Unable to add the student. Please try again.';
            }
        }
    }
}


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
                        STUDENT MANAGEMENT
                    </span>

                    <h1>
                        Add Student
                    </h1>

                    <p>
                        Register a new student in the school system.
                    </p>

                </div>


                <div class="page-header-actions">

                    <a
                        href="students.php"
                        class="dashboard-secondary-button"
                    >
                        Back to Students
                    </a>

                </div>

            </div>


            <!-- =================================================
                 NO CLASSES WARNING
                 ================================================= -->

            <?php if (empty($classes)): ?>

                <div class="table-message error">

                    <strong>
                        No classes available.
                    </strong>

                    Please create a class before adding
                    a student.

                    <br><br>

                    <a
                        href="classes.php#class-form"
                        class="dashboard-secondary-button"
                    >
                        Create Class
                    </a>

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
                 STUDENT FORM
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">

                    <div>

                        <span class="page-header-label">
                            STUDENT INFORMATION
                        </span>

                        <h2>
                            Student Details
                        </h2>

                    </div>

                </div>


                <form
                    method="POST"
                    action="add-student.php"
                    enctype="multipart/form-data"
                    class="dashboard-form"
                >


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >


                    <!-- =================================================
                         STUDENT REFERENCE
                         ================================================= -->

                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Student Reference
                            </label>

                            <input
                                type="text"
                                value="<?= e($studentReference) ?>"
                                class="form-input"
                                readonly
                            >

                            <small>
                                Automatically generated by the system.
                            </small>

                        </div>


                        <div class="form-group">

                            <label for="admission_number">
                                Admission Number
                            </label>

                            <input
                                type="text"
                                id="admission_number"
                                name="admission_number"
                                class="form-input"
                                placeholder="e.g. ADM-001"
                                value="<?= e(
                                    $old['admission_number']
                                ) ?>"
                                maxlength="50"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="class_id">
                                Class
                            </label>

                            <select
                                id="class_id"
                                name="class_id"
                                class="form-input"
                                required
                            >

                                <option value="">
                                    Select Class
                                </option>


                                <?php foreach (
                                    $classes
                                    as $class
                                ): ?>

                                    <option
                                        value="<?= (int) $class['id'] ?>"
                                        <?= (int) $old['class_id'] ===
                                            (int) $class['id']
                                                ? 'selected'
                                                : ''
                                        ?>
                                    >

                                        <?= e(
                                            $class['name']
                                        ) ?>

                                        —

                                        <?= e(
                                            $class['code']
                                        ) ?>

                                        (
                                        <?= e(
                                            $class['academic_year']
                                        ) ?>
                                        )

                                    </option>

                                <?php endforeach; ?>


                            </select>

                        </div>


                    </div>


                    <!-- =================================================
                         NAME
                         ================================================= -->

                    <div class="form-grid">


                        <div class="form-group">

                            <label for="first_name">
                                First Name
                            </label>

                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                class="form-input"
                                placeholder="Enter first name"
                                value="<?= e(
                                    $old['first_name']
                                ) ?>"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="middle_name">
                                Middle Name
                            </label>

                            <input
                                type="text"
                                id="middle_name"
                                name="middle_name"
                                class="form-input"
                                placeholder="Optional"
                                value="<?= e(
                                    $old['middle_name']
                                ) ?>"
                                maxlength="100"
                            >

                        </div>


                        <div class="form-group">

                            <label for="last_name">
                                Last Name
                            </label>

                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                class="form-input"
                                placeholder="Enter last name"
                                value="<?= e(
                                    $old['last_name']
                                ) ?>"
                                maxlength="100"
                                required
                            >

                        </div>


                    </div>


                    <!-- =================================================
                         PERSONAL INFORMATION
                         ================================================= -->

                    <div class="form-grid">


                        <div class="form-group">

                            <label for="gender">
                                Gender
                            </label>

                            <select
                                id="gender"
                                name="gender"
                                class="form-input"
                                required
                            >

                                <option value="">
                                    Select Gender
                                </option>

                                <option
                                    value="MALE"
                                    <?= $old['gender'] === 'MALE'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="FEMALE"
                                    <?= $old['gender'] === 'FEMALE'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Female
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label for="date_of_birth">
                                Date of Birth
                            </label>

                            <input
                                type="date"
                                id="date_of_birth"
                                name="date_of_birth"
                                class="form-input"
                                value="<?= e(
                                    $old['date_of_birth']
                                ) ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label for="status">
                                Status
                            </label>

                            <select
                                id="status"
                                name="status"
                                class="form-input"
                            >

                                <option
                                    value="ACTIVE"
                                    <?= $old['status'] === 'ACTIVE'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="INACTIVE"
                                    <?= $old['status'] === 'INACTIVE'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Inactive
                                </option>

                            </select>

                        </div>


                    </div>


                    <!-- =================================================
                         PHOTO
                         ================================================= -->

                    <div class="form-group">


                        <label for="photo">
                            Student Photo
                        </label>


                        <input
                            type="file"
                            id="photo"
                            name="photo"
                            class="form-input"
                            accept=".jpg,.jpeg,.png,.webp"
                        >


                        <small>
                            Optional. JPG, PNG or WEBP.
                            Maximum size: 2 MB.
                        </small>


                    </div>


                    <!-- =================================================
                         ACTIONS
                         ================================================= -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="dashboard-primary-button"
                            <?= empty($classes)
                                ? 'disabled'
                                : ''
                            ?>
                        >
                            Save Student
                        </button>


                        <a
                            href="students.php"
                            class="dashboard-secondary-button"
                        >
                            Cancel
                        </a>


                    </div>


                </form>


            </div>


        </div>


    </main>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>