<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Edit Student | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/forms.css'
];


$error = '';

$studentId = (int) ($_GET['id'] ?? 0);

if ($studentId <= 0) {

    redirect(
        '/student-attendance-system/admin/students.php'
    );
}


/*
|--------------------------------------------------------------------------
| Load Student
|--------------------------------------------------------------------------
*/

$studentStmt = $pdo->prepare(
    "
    SELECT
        id,
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
    FROM students
    WHERE id = ?
    LIMIT 1
    "
);

$studentStmt->execute([
    $studentId
]);

$student = $studentStmt->fetch();


if (!$student) {

    http_response_code(404);

    die('Student not found.');
}


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
| Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {

        $error =
            'Invalid security token. Please try again.';

    } else {

        $admissionNumber =
            trim($_POST['admission_number'] ?? '');

        $firstName =
            trim($_POST['first_name'] ?? '');

        $middleName =
            trim($_POST['middle_name'] ?? '');

        $lastName =
            trim($_POST['last_name'] ?? '');

        $gender =
            strtoupper(
                trim($_POST['gender'] ?? '')
            );

        $dateOfBirth =
            trim($_POST['date_of_birth'] ?? '');

        $classId =
            (int) ($_POST['class_id'] ?? 0);

        $status =
            strtoupper(
                trim($_POST['status'] ?? '')
            );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($admissionNumber === '') {

            $error =
                'Please enter the admission number.';

        } elseif ($firstName === '') {

            $error =
                'Please enter the first name.';

        } elseif ($lastName === '') {

            $error =
                'Please enter the last name.';

        } elseif (
            !in_array(
                $gender,
                ['MALE', 'FEMALE'],
                true
            )
        ) {

            $error =
                'Please select a valid gender.';

        } elseif ($classId <= 0) {

            $error =
                'Please select a class.';

        } elseif (
            !in_array(
                $status,
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
            $dateOfBirth !== ''
        ) {

            $dateObject =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $dateOfBirth
                );

            $validDate =
                $dateObject &&
                $dateObject->format('Y-m-d') ===
                $dateOfBirth;

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
            $classId > 0
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
                $classId
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
                AND id != ?
                LIMIT 1
                "
            );

            $admissionCheck->execute([
                $admissionNumber,
                $studentId
            ]);

            if ($admissionCheck->fetch()) {

                $error =
                    'Another student already uses this admission number.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Current Photo
        |--------------------------------------------------------------------------
        */

        $photoPath =
            $student['photo_path'];


        /*
        |--------------------------------------------------------------------------
        | Handle New Photo
        |--------------------------------------------------------------------------
        */

        if (
            $error === '' &&
            isset($_FILES['photo']) &&
            $_FILES['photo']['error'] !==
            UPLOAD_ERR_NO_FILE
        ) {

            $photo =
                $_FILES['photo'];


            if (
                $photo['error'] !==
                UPLOAD_ERR_OK
            ) {

                $error =
                    'Unable to upload the student photo.';

            } elseif (
                $photo['size'] >
                2 * 1024 * 1024
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
                    new finfo(
                        FILEINFO_MIME_TYPE
                    );


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
                        !is_dir(
                            $uploadDirectory
                        )
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

                        $newPhotoPath =
                            'assets/uploads/students/' .
                            $fileName;
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Update Student
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            try {

                /*
                | If a new photo was uploaded,
                | use it. Otherwise keep old photo.
                */

                if (
                    isset($newPhotoPath)
                ) {

                    $photoPath =
                        $newPhotoPath;
                }


                $update = $pdo->prepare(
                    "
                    UPDATE students
                    SET
                        admission_number = ?,
                        first_name = ?,
                        middle_name = ?,
                        last_name = ?,
                        gender = ?,
                        date_of_birth = ?,
                        class_id = ?,
                        photo_path = ?,
                        status = ?
                    WHERE id = ?
                    LIMIT 1
                    "
                );


                $update->execute([
                    $admissionNumber,
                    $firstName,
                    $middleName !== ''
                        ? $middleName
                        : null,
                    $lastName,
                    $gender,
                    $dateOfBirth !== ''
                        ? $dateOfBirth
                        : null,
                    $classId,
                    $photoPath,
                    $status,
                    $studentId
                ]);


                /*
                |--------------------------------------------------------------------------
                | Delete Old Photo
                |--------------------------------------------------------------------------
                */

                if (
                    isset($newPhotoPath) &&
                    !empty($student['photo_path']) &&
                    $student['photo_path'] !==
                    $newPhotoPath
                ) {

                    $oldPhoto =
                        __DIR__ .
                        '/../' .
                        $student['photo_path'];


                    if (
                        file_exists($oldPhoto)
                    ) {

                        unlink($oldPhoto);
                    }
                }


                setFlashMessage(
                    'success',
                    'Student information updated successfully.'
                );


                redirect(
                    '/student-attendance-system/admin/student-profile.php?id=' .
                    $studentId
                );


            } catch (PDOException $e) {

                /*
                | Remove newly uploaded photo
                | if database update fails.
                */

                if (
                    isset($newPhotoPath)
                ) {

                    $newUploadedFile =
                        __DIR__ .
                        '/../' .
                        $newPhotoPath;


                    if (
                        file_exists(
                            $newUploadedFile
                        )
                    ) {

                        unlink(
                            $newUploadedFile
                        );
                    }
                }


                $error =
                    'Unable to update the student. Please try again.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Rebuild Student Values For Form
        |--------------------------------------------------------------------------
        */

        if ($error !== '') {

            $student['admission_number'] =
                $admissionNumber;

            $student['first_name'] =
                $firstName;

            $student['middle_name'] =
                $middleName;

            $student['last_name'] =
                $lastName;

            $student['gender'] =
                $gender;

            $student['date_of_birth'] =
                $dateOfBirth;

            $student['class_id'] =
                $classId;

            $student['status'] =
                $status;
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
                        Edit Student
                    </h1>

                    <p>
                        Update the student's information.
                    </p>

                </div>


                <div class="page-header-actions">

                    <a
                        href="student-profile.php?id=<?= $studentId ?>"
                        class="dashboard-secondary-button"
                    >
                        View Profile
                    </a>

                    <a
                        href="students.php"
                        class="dashboard-secondary-button"
                    >
                        Back to Students
                    </a>

                </div>

            </div>


            <!-- =================================================
                 ERROR
                 ================================================= -->

            <?php if ($error !== ''): ?>

                <div class="table-message error">

                    <?= e($error) ?>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 FORM
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">

                    <div>

                        <span class="page-header-label">
                            STUDENT INFORMATION
                        </span>

                        <h2>
                            <?= e(
                                $student['first_name']
                            ) ?>

                            <?= e(
                                $student['last_name']
                            ) ?>
                        </h2>

                    </div>

                </div>


                <form
                    method="POST"
                    action="edit-student.php?id=<?= $studentId ?>"
                    enctype="multipart/form-data"
                    class="dashboard-form"
                >


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >


                    <!-- =================================================
                         REFERENCE / ADMISSION / CLASS
                         ================================================= -->

                    <div class="form-grid">


                        <div class="form-group">

                            <label>
                                Student Reference
                            </label>

                            <input
                                type="text"
                                value="<?= e(
                                    $student['student_reference']
                                ) ?>"
                                class="form-input"
                                readonly
                            >

                            <small>
                                Student reference cannot be changed.
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
                                value="<?= e(
                                    $student['admission_number']
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
                                        <?= (int) $student['class_id'] ===
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
                                value="<?= e(
                                    $student['first_name']
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
                                value="<?= e(
                                    $student['middle_name']
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
                                value="<?= e(
                                    $student['last_name']
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
                                    <?= $student['gender'] === 'MALE'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="FEMALE"
                                    <?= $student['gender'] === 'FEMALE'
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
                                    $student['date_of_birth'] ?? ''
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
                                    <?= $student['status'] === 'ACTIVE'
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    Active
                                </option>

                                <option
                                    value="INACTIVE"
                                    <?= $student['status'] === 'INACTIVE'
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
                         CURRENT PHOTO
                         ================================================= -->

                    <?php if (
                        !empty(
                            $student['photo_path']
                        )
                    ): ?>

                        <div class="form-group">

                            <label>
                                Current Photo
                            </label>

                            <img
                                src="/student-attendance-system/<?= e(
                                    $student['photo_path']
                                ) ?>"
                                alt="Current student photo"
                                style="
                                    width: 120px;
                                    height: 120px;
                                    object-fit: cover;
                                    border-radius: 12px;
                                    border: 1px solid #e2e8f0;
                                "
                            >

                        </div>

                    <?php endif; ?>


                    <!-- =================================================
                         NEW PHOTO
                         ================================================= -->

                    <div class="form-group">

                        <label for="photo">
                            Replace Photo
                        </label>

                        <input
                            type="file"
                            id="photo"
                            name="photo"
                            class="form-input"
                            accept=".jpg,.jpeg,.png,.webp"
                        >

                        <small>
                            Optional. Upload only if you want
                            to replace the current photo.
                            JPG, PNG or WEBP. Maximum 2 MB.
                        </small>

                    </div>


                    <!-- =================================================
                         ACTIONS
                         ================================================= -->

                    <div class="form-actions">

                        <button
                            type="submit"
                            class="dashboard-primary-button"
                        >
                            Save Changes
                        </button>

                        <a
                            href="student-profile.php?id=<?= $studentId ?>"
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