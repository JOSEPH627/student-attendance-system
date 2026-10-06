<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Add Teacher | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css',
    '/student-attendance-system/assets/css/forms.css'
];


$teacherId =
    (int) ($_GET['edit'] ?? 0);

$editMode =
    $teacherId > 0;


$error = '';
$success = '';

$teacher = null;


/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/

$form = [
    'name' => '',
    'email' => '',
    'employee_number' => '',
    'phone' => '',
    'department' => ''
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

$classes =
    $classStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Load Subjects
|--------------------------------------------------------------------------
*/

$subjectStmt = $pdo->query(
    "
    SELECT
        id,
        name,
        code
    FROM subjects
    ORDER BY name ASC
    "
);

$subjects =
    $subjectStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Load Existing Teacher
|--------------------------------------------------------------------------
*/

if ($editMode) {

    $teacherStmt = $pdo->prepare(
        "
        SELECT

            t.id,
            t.user_id,
            t.employee_number,
            t.phone,
            t.department,

            u.name,
            u.email

        FROM teachers t

        INNER JOIN users u
            ON u.id = t.user_id

        WHERE t.id = ?

        LIMIT 1
        "
    );

    $teacherStmt->execute([
        $teacherId
    ]);

    $teacher =
        $teacherStmt->fetch();


    if (!$teacher) {

        redirect(
            '/student-attendance-system/admin/teachers.php'
        );
    }


    $form['name'] =
        $teacher['name'];

    $form['email'] =
        $teacher['email'];

    $form['employee_number'] =
        $teacher['employee_number'];

    $form['phone'] =
        $teacher['phone'] ?? '';

    $form['department'] =
        $teacher['department'] ?? '';
}


/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';

    $csrfToken =
        $_POST['csrf_token'] ?? '';


    if (!verifyCsrfToken($csrfToken)) {

        $error =
            'Invalid security token. Please try again.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | ADD TEACHER
        |--------------------------------------------------------------------------
        */

        if (
            $action === 'add_teacher'
        ) {

            $form['name'] =
                trim(
                    $_POST['name'] ?? ''
                );

            $form['email'] =
                strtolower(
                    trim(
                        $_POST['email'] ?? ''
                    )
                );

            $password =
                $_POST['password'] ?? '';

            $confirmPassword =
                $_POST['confirm_password'] ?? '';

            $form['employee_number'] =
                strtoupper(
                    trim(
                        $_POST[
                            'employee_number'
                        ] ?? ''
                    )
                );

            $form['phone'] =
                trim(
                    $_POST['phone'] ?? ''
                );

            $form['department'] =
                trim(
                    $_POST['department'] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if (
                $form['name'] === ''
            ) {

                $error =
                    'Please enter the teacher name.';

            } elseif (
                !filter_var(
                    $form['email'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                $error =
                    'Please enter a valid email address.';

            } elseif (
                strlen($password) < 8
            ) {

                $error =
                    'Password must contain at least 8 characters.';

            } elseif (
                $password !==
                $confirmPassword
            ) {

                $error =
                    'Passwords do not match.';

            } elseif (
                $form['employee_number'] === ''
            ) {

                $error =
                    'Please enter the employee number.';

            }


            /*
            |--------------------------------------------------------------------------
            | Check Email
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                $emailCheck =
                    $pdo->prepare(
                        "
                        SELECT id
                        FROM users
                        WHERE email = ?
                        LIMIT 1
                        "
                    );

                $emailCheck->execute([
                    $form['email']
                ]);

                if (
                    $emailCheck->fetch()
                ) {

                    $error =
                        'A user with this email already exists.';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Check Employee Number
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                $employeeCheck =
                    $pdo->prepare(
                        "
                        SELECT id
                        FROM teachers
                        WHERE employee_number = ?
                        LIMIT 1
                        "
                    );

                $employeeCheck->execute([
                    $form['employee_number']
                ]);

                if (
                    $employeeCheck->fetch()
                ) {

                    $error =
                        'A teacher with this employee number already exists.';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Create Account
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                try {

                    $pdo->beginTransaction();


                    /*
                    | Password Hash
                    */

                    $passwordHash =
                        password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );


                    /*
                    | Create User
                    */

                    $userInsert =
                        $pdo->prepare(
                            "
                            INSERT INTO users
                            (
                                name,
                                email,
                                password_hash,
                                role
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                'TEACHER'
                            )
                            "
                        );

                    $userInsert->execute([
                        $form['name'],
                        $form['email'],
                        $passwordHash
                    ]);


                    $userId =
                        (int) $pdo->lastInsertId();


                    /*
                    | Create Teacher
                    */

                    $teacherInsert =
                        $pdo->prepare(
                            "
                            INSERT INTO teachers
                            (
                                user_id,
                                employee_number,
                                phone,
                                department
                            )
                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?
                            )
                            "
                        );

                    $teacherInsert->execute([
                        $userId,
                        $form['employee_number'],
                        $form['phone'] !== ''
                            ? $form['phone']
                            : null,
                        $form['department'] !== ''
                            ? $form['department']
                            : null
                    ]);


                    $pdo->commit();


                    setFlashMessage(
                        'success',
                        'Teacher account created successfully.'
                    );


                    redirect(
                        '/student-attendance-system/admin/teachers.php'
                    );


                } catch (PDOException $e) {

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();
                    }


                    $error =
                        'Unable to create the teacher account.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE TEACHER
        |--------------------------------------------------------------------------
        */

        elseif (
            $action === 'update_teacher' &&
            $editMode
        ) {

            $form['name'] =
                trim(
                    $_POST['name'] ?? ''
                );

            $form['email'] =
                strtolower(
                    trim(
                        $_POST['email'] ?? ''
                    )
                );

            $newPassword =
                $_POST['password'] ?? '';

            $confirmPassword =
                $_POST['confirm_password'] ?? '';

            $form['employee_number'] =
                strtoupper(
                    trim(
                        $_POST[
                            'employee_number'
                        ] ?? ''
                    )
                );

            $form['phone'] =
                trim(
                    $_POST['phone'] ?? ''
                );

            $form['department'] =
                trim(
                    $_POST['department'] ?? ''
                );


            /*
            |--------------------------------------------------------------------------
            | Validation
            |--------------------------------------------------------------------------
            */

            if (
                $form['name'] === ''
            ) {

                $error =
                    'Please enter the teacher name.';

            } elseif (
                !filter_var(
                    $form['email'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                $error =
                    'Please enter a valid email address.';

            } elseif (
                $form['employee_number'] === ''
            ) {

                $error =
                    'Please enter the employee number.';

            } elseif (
                $newPassword !== '' &&
                strlen($newPassword) < 8
            ) {

                $error =
                    'New password must contain at least 8 characters.';

            } elseif (
                $newPassword !== '' &&
                $newPassword !== $confirmPassword
            ) {

                $error =
                    'New passwords do not match.';

            }


            /*
            |--------------------------------------------------------------------------
            | Check Email
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                $emailCheck =
                    $pdo->prepare(
                        "
                        SELECT id
                        FROM users
                        WHERE email = ?
                        AND id != ?
                        LIMIT 1
                        "
                    );

                $emailCheck->execute([
                    $form['email'],
                    $teacher['user_id']
                ]);

                if (
                    $emailCheck->fetch()
                ) {

                    $error =
                        'Another user already uses this email.';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Check Employee Number
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                $employeeCheck =
                    $pdo->prepare(
                        "
                        SELECT id
                        FROM teachers
                        WHERE employee_number = ?
                        AND id != ?
                        LIMIT 1
                        "
                    );

                $employeeCheck->execute([
                    $form['employee_number'],
                    $teacherId
                ]);

                if (
                    $employeeCheck->fetch()
                ) {

                    $error =
                        'Another teacher already uses this employee number.';
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Update
            |--------------------------------------------------------------------------
            */

            if ($error === '') {

                try {

                    $pdo->beginTransaction();


                    /*
                    | Update User
                    */

                    if (
                        $newPassword !== ''
                    ) {

                        $passwordHash =
                            password_hash(
                                $newPassword,
                                PASSWORD_DEFAULT
                            );

                        $userUpdate =
                            $pdo->prepare(
                                "
                                UPDATE users
                                SET
                                    name = ?,
                                    email = ?,
                                    password_hash = ?
                                WHERE id = ?
                                LIMIT 1
                                "
                            );

                        $userUpdate->execute([
                            $form['name'],
                            $form['email'],
                            $passwordHash,
                            $teacher['user_id']
                        ]);

                    } else {

                        $userUpdate =
                            $pdo->prepare(
                                "
                                UPDATE users
                                SET
                                    name = ?,
                                    email = ?
                                WHERE id = ?
                                LIMIT 1
                                "
                            );

                        $userUpdate->execute([
                            $form['name'],
                            $form['email'],
                            $teacher['user_id']
                        ]);
                    }


                    /*
                    | Update Teacher
                    */

                    $teacherUpdate =
                        $pdo->prepare(
                            "
                            UPDATE teachers
                            SET
                                employee_number = ?,
                                phone = ?,
                                department = ?
                            WHERE id = ?
                            LIMIT 1
                            "
                        );

                    $teacherUpdate->execute([
                        $form['employee_number'],
                        $form['phone'] !== ''
                            ? $form['phone']
                            : null,
                        $form['department'] !== ''
                            ? $form['department']
                            : null,
                        $teacherId
                    ]);


                    $pdo->commit();


                    setFlashMessage(
                        'success',
                        'Teacher information updated successfully.'
                    );


                    redirect(
                        '/student-attendance-system/admin/teachers.php'
                    );


                } catch (PDOException $e) {

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();
                    }


                    $error =
                        'Unable to update the teacher.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | ADD ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        elseif (
            $action === 'add_assignment' &&
            $editMode
        ) {

            $classId =
                (int) (
                    $_POST['class_id'] ?? 0
                );

            $subjectId =
                (int) (
                    $_POST['subject_id'] ?? 0
                );


            if (
                $classId <= 0 ||
                $subjectId <= 0
            ) {

                $error =
                    'Please select both a class and a subject.';

            } else {

                try {

                    /*
                    | Verify Class
                    */

                    $classCheck =
                        $pdo->prepare(
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


                    /*
                    | Verify Subject
                    */

                    $subjectCheck =
                        $pdo->prepare(
                            "
                            SELECT id
                            FROM subjects
                            WHERE id = ?
                            LIMIT 1
                            "
                        );

                    $subjectCheck->execute([
                        $subjectId
                    ]);


                    if (
                        !$classCheck->fetch()
                    ) {

                        $error =
                            'The selected class does not exist.';

                    } elseif (
                        !$subjectCheck->fetch()
                    ) {

                        $error =
                            'The selected subject does not exist.';

                    } else {

                        /*
                        | Check Duplicate Assignment
                        */

                        $duplicateCheck =
                            $pdo->prepare(
                                "
                                SELECT id
                                FROM teaching_assignments
                                WHERE teacher_id = ?
                                AND class_id = ?
                                AND subject_id = ?
                                LIMIT 1
                                "
                            );

                        $duplicateCheck->execute([
                            $teacherId,
                            $classId,
                            $subjectId
                        ]);


                        if (
                            $duplicateCheck->fetch()
                        ) {

                            $error =
                                'This class and subject are already assigned to this teacher.';

                        } else {

                            $assignmentInsert =
                                $pdo->prepare(
                                    "
                                    INSERT INTO teaching_assignments
                                    (
                                        teacher_id,
                                        class_id,
                                        subject_id
                                    )
                                    VALUES
                                    (
                                        ?,
                                        ?,
                                        ?
                                    )
                                    "
                                );

                            $assignmentInsert->execute([
                                $teacherId,
                                $classId,
                                $subjectId
                            ]);


                            $success =
                                'Teaching assignment added successfully.';
                        }
                    }

                } catch (PDOException $e) {

                    $error =
                        'Unable to add the teaching assignment.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE ASSIGNMENT
        |--------------------------------------------------------------------------
        */

        elseif (
            $action === 'delete_assignment' &&
            $editMode
        ) {

            $assignmentId =
                (int) (
                    $_POST[
                        'assignment_id'
                    ] ?? 0
                );


            if (
                $assignmentId <= 0
            ) {

                $error =
                    'Invalid assignment selected.';

            } else {

                try {

                    $deleteAssignment =
                        $pdo->prepare(
                            "
                            DELETE FROM teaching_assignments

                            WHERE id = ?
                            AND teacher_id = ?

                            LIMIT 1
                            "
                        );

                    $deleteAssignment->execute([
                        $assignmentId,
                        $teacherId
                    ]);


                    if (
                        $deleteAssignment->rowCount() > 0
                    ) {

                        $success =
                            'Teaching assignment removed successfully.';

                    } else {

                        $error =
                            'The selected assignment was not found.';
                    }

                } catch (PDOException $e) {

                    $error =
                        'Unable to remove the teaching assignment.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Assignments
|--------------------------------------------------------------------------
*/

$assignments = [];


if ($editMode) {

    $assignmentStmt =
        $pdo->prepare(
            "
            SELECT

                ta.id,

                c.name AS class_name,
                c.code AS class_code,
                c.academic_year,

                s.name AS subject_name,
                s.code AS subject_code

            FROM teaching_assignments ta

            INNER JOIN classes c
                ON c.id = ta.class_id

            INNER JOIN subjects s
                ON s.id = ta.subject_id

            WHERE ta.teacher_id = ?

            ORDER BY
                c.academic_year DESC,
                c.name ASC,
                s.name ASC
            "
        );

    $assignmentStmt->execute([
        $teacherId
    ]);

    $assignments =
        $assignmentStmt->fetchAll();
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

                        <?= $editMode
                            ? 'TEACHER MANAGEMENT'
                            : 'TEACHER MANAGEMENT'
                        ?>

                    </span>


                    <h1>

                        <?= $editMode
                            ? 'Manage Teacher'
                            : 'Add Teacher'
                        ?>

                    </h1>


                    <p>

                        <?= $editMode
                            ? 'Update teacher information and manage teaching assignments.'
                            : 'Create a teacher account and add staff information.'
                        ?>

                    </p>

                </div>


                <div class="page-header-actions">

                    <a
                        href="teachers.php"
                        class="dashboard-secondary-button"
                    >
                        Back to Teachers
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
                 TEACHER INFORMATION FORM
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">


                    <div>

                        <span class="page-header-label">
                            ACCOUNT INFORMATION
                        </span>


                        <h2>
                            Teacher Details
                        </h2>

                    </div>


                </div>


                <form
                    method="POST"
                    action="add-teacher.php<?= $editMode
                        ? '?edit=' . $teacherId
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
                            ? 'update_teacher'
                            : 'add_teacher'
                        ?>"
                    >


                    <!-- =================================================
                         NAME / EMAIL
                         ================================================= -->

                    <div class="form-grid">


                        <div class="form-group">

                            <label for="name">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="name"
                                name="name"
                                class="form-input"
                                placeholder="e.g. John Michael"
                                value="<?= e(
                                    $form['name']
                                ) ?>"
                                maxlength="100"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="email">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                class="form-input"
                                placeholder="teacher@example.com"
                                value="<?= e(
                                    $form['email']
                                ) ?>"
                                maxlength="150"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="employee_number">
                                Employee Number
                            </label>

                            <input
                                type="text"
                                id="employee_number"
                                name="employee_number"
                                class="form-input"
                                placeholder="e.g. EMP-001"
                                value="<?= e(
                                    $form[
                                        'employee_number'
                                    ]
                                ) ?>"
                                maxlength="50"
                                required
                            >

                        </div>


                    </div>


                    <!-- =================================================
                         STAFF INFORMATION
                         ================================================= -->

                    <div class="form-grid">


                        <div class="form-group">

                            <label for="phone">
                                Phone Number
                            </label>

                            <input
                                type="text"
                                id="phone"
                                name="phone"
                                class="form-input"
                                placeholder="e.g. +255 7XX XXX XXX"
                                value="<?= e(
                                    $form['phone']
                                ) ?>"
                                maxlength="30"
                            >

                        </div>


                        <div class="form-group">

                            <label for="department">
                                Department
                            </label>

                            <input
                                type="text"
                                id="department"
                                name="department"
                                class="form-input"
                                placeholder="e.g. Science"
                                value="<?= e(
                                    $form['department']
                                ) ?>"
                                maxlength="100"
                            >

                        </div>


                    </div>


                    <!-- =================================================
                         PASSWORD
                         ================================================= -->

                    <div class="form-grid">


                        <div class="form-group">

                            <label for="password">

                                <?= $editMode
                                    ? 'New Password'
                                    : 'Password'
                                ?>

                            </label>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="form-input"
                                placeholder="<?= $editMode
                                    ? 'Leave blank to keep current password'
                                    : 'Minimum 8 characters'
                                ?>"
                                minlength="8"
                                <?= $editMode
                                    ? ''
                                    : 'required'
                                ?>
                            >


                            <?php if (
                                $editMode
                            ): ?>

                                <small>
                                    Leave blank if the password
                                    should remain unchanged.
                                </small>

                            <?php endif; ?>


                        </div>


                        <div class="form-group">

                            <label for="confirm_password">

                                <?= $editMode
                                    ? 'Confirm New Password'
                                    : 'Confirm Password'
                                ?>

                            </label>


                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="form-input"
                                placeholder="Confirm password"
                                minlength="8"
                                <?= $editMode
                                    ? ''
                                    : 'required'
                                ?>
                            >

                        </div>


                    </div>


                    <!-- =================================================
                         FORM ACTIONS
                         ================================================= -->

                    <div class="form-actions">


                        <button
                            type="submit"
                            class="dashboard-primary-button"
                        >

                            <?= $editMode
                                ? 'Save Changes'
                                : 'Create Teacher'
                            ?>

                        </button>


                        <a
                            href="teachers.php"
                            class="dashboard-secondary-button"
                        >
                            Cancel
                        </a>


                    </div>


                </form>


            </div>


            <!-- =================================================
                 TEACHING ASSIGNMENTS
                 ================================================= -->

            <?php if ($editMode): ?>


                <div class="table-panel">


                    <div class="table-panel-header">


                        <div>

                            <span class="page-header-label">
                                TEACHING ASSIGNMENTS
                            </span>


                            <h2>
                                Class & Subject Assignments
                            </h2>


                        </div>


                        <span class="record-count">

                            <?= count(
                                $assignments
                            ) ?>

                            assignment<?= count(
                                $assignments
                            ) === 1
                                ? ''
                                : 's'
                            ?>

                        </span>


                    </div>


                    <!-- =================================================
                         ADD ASSIGNMENT
                         ================================================= -->

                    <?php if (
                        empty($classes)
                    ): ?>


                        <div class="table-message error">

                            No classes are available.

                            Create a class first from
                            <a
                                href="classes.php#class-form"
                            >
                                Class Management
                            </a>.

                        </div>


                    <?php elseif (
                        empty($subjects)
                    ): ?>


                        <div class="table-message error">

                            No subjects are available.

                            Create a subject first from
                            <a
                                href="subjects.php#subject-form"
                            >
                                Subject Management
                            </a>.

                        </div>


                    <?php else: ?>


                        <form
                            method="POST"
                            action="add-teacher.php?edit=<?= $teacherId ?>"
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
                                value="add_assignment"
                            >


                            <div class="form-grid">


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
                                                    $class[
                                                        'academic_year'
                                                    ]
                                                ) ?>
                                                )

                                            </option>

                                        <?php endforeach; ?>


                                    </select>

                                </div>


                                <div class="form-group">

                                    <label for="subject_id">
                                        Subject
                                    </label>


                                    <select
                                        id="subject_id"
                                        name="subject_id"
                                        class="form-input"
                                        required
                                    >

                                        <option value="">
                                            Select Subject
                                        </option>


                                        <?php foreach (
                                            $subjects
                                            as $subject
                                        ): ?>

                                            <option
                                                value="<?= (int) $subject['id'] ?>"
                                            >

                                                <?= e(
                                                    $subject['name']
                                                ) ?>

                                                —

                                                <?= e(
                                                    $subject['code']
                                                ) ?>

                                            </option>

                                        <?php endforeach; ?>


                                    </select>

                                </div>


                                <div class="form-group">

                                    <label>
                                        &nbsp;
                                    </label>


                                    <button
                                        type="submit"
                                        class="dashboard-primary-button"
                                    >
                                        + Add Assignment
                                    </button>

                                </div>


                            </div>


                        </form>


                    <?php endif; ?>


                    <!-- =================================================
                         ASSIGNMENT LIST
                         ================================================= -->

                    <?php if (
                        empty($assignments)
                    ): ?>


                        <div class="table-empty-state">


                            <div class="empty-state-icon">
                                AS
                            </div>


                            <h3>
                                No teaching assignments
                            </h3>


                            <p>
                                Assign this teacher to a class
                                and subject above.
                            </p>


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
                                            Academic Year
                                        </th>

                                        <th>
                                            Subject
                                        </th>

                                        <th>
                                            Action
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>


                                    <?php foreach (
                                        $assignments
                                        as $assignment
                                    ): ?>


                                        <tr>


                                            <td>

                                                <div
                                                    class="table-primary-text"
                                                >

                                                    <?= e(
                                                        $assignment[
                                                            'class_name'
                                                        ]
                                                    ) ?>

                                                </div>


                                                <span
                                                    class="table-secondary-text"
                                                >

                                                    <?= e(
                                                        $assignment[
                                                            'class_code'
                                                        ]
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>

                                                <?= e(
                                                    $assignment[
                                                        'academic_year'
                                                    ]
                                                ) ?>

                                            </td>


                                            <td>

                                                <div
                                                    class="table-primary-text"
                                                >

                                                    <?= e(
                                                        $assignment[
                                                            'subject_name'
                                                        ]
                                                    ) ?>

                                                </div>


                                                <span
                                                    class="table-secondary-text"
                                                >

                                                    <?= e(
                                                        $assignment[
                                                            'subject_code'
                                                        ]
                                                    ) ?>

                                                </span>

                                            </td>


                                            <td>


                                                <form
                                                    method="POST"
                                                    action="add-teacher.php?edit=<?= $teacherId ?>"
                                                    onsubmit="return confirm('Remove this teaching assignment?');"
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
                                                        value="delete_assignment"
                                                    >


                                                    <input
                                                        type="hidden"
                                                        name="assignment_id"
                                                        value="<?= (int) $assignment['id'] ?>"
                                                    >


                                                    <button
                                                        type="submit"
                                                        class="table-action-button danger"
                                                    >
                                                        Remove
                                                    </button>


                                                </form>


                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                </tbody>


                            </table>


                        </div>


                    <?php endif; ?>


                </div>


            <?php endif; ?>


        </div>


    </main>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
