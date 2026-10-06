<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Users';
$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css',
    '/student-attendance-system/assets/css/forms.css'
];

$flash = getFlashMessage();

$editUser = null;
$editTeacher = null;

$formData = [
    'name' => '',
    'email' => '',
    'role' => 'TEACHER'
];

$errors = [];

/*
|--------------------------------------------------------------------------
| Handle POST requests
|--------------------------------------------------------------------------
*/

if (isPost()) {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid security token. Please refresh the page and try again.';
    } else {

        $action = $_POST['action'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | CREATE USER
        |--------------------------------------------------------------------------
        */
        if ($action === 'create') {

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = strtoupper(trim($_POST['role'] ?? 'TEACHER'));
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $formData = [
                'name' => $name,
                'email' => $email,
                'role' => $role
            ];

            if ($name === '') {
                $errors[] = 'Full name is required.';
            } elseif (mb_strlen($name) < 2) {
                $errors[] = 'Full name must contain at least 2 characters.';
            } elseif (mb_strlen($name) > 100) {
                $errors[] = 'Full name must not exceed 100 characters.';
            }

            if ($email === '') {
                $errors[] = 'Email address is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif (mb_strlen($email) > 150) {
                $errors[] = 'Email address must not exceed 150 characters.';
            }

            if (!in_array($role, ['ADMIN', 'TEACHER'], true)) {
                $errors[] = 'Invalid user role selected.';
            }

            if ($password === '') {
                $errors[] = 'Password is required.';
            } elseif (strlen($password) < 8) {
                $errors[] = 'Password must contain at least 8 characters.';
            }

            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }

            /*
            |--------------------------------------------------------------------------
            | Check duplicate email
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    $errors[] = 'A user with this email address already exists.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Create user
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                try {

                    $pdo->beginTransaction();

                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                    $stmt = $pdo->prepare("
                        INSERT INTO users (
                            name,
                            email,
                            password_hash,
                            role
                        )
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $name,
                        $email,
                        $passwordHash,
                        $role
                    ]);

                    $userId = (int) $pdo->lastInsertId();

                    /*
                    |--------------------------------------------------------------------------
                    | If TEACHER, create teacher profile automatically
                    |--------------------------------------------------------------------------
                    */

                    if ($role === 'TEACHER') {

                        $employeeNumber = 'EMP-' . strtoupper(
                            substr(bin2hex(random_bytes(5)), 0, 8)
                        );

                        $checkEmployee = $pdo->prepare("
                            SELECT id
                            FROM teachers
                            WHERE employee_number = ?
                            LIMIT 1
                        ");

                        $checkEmployee->execute([$employeeNumber]);

                        while ($checkEmployee->fetch()) {

                            $employeeNumber = 'EMP-' . strtoupper(
                                substr(bin2hex(random_bytes(5)), 0, 8)
                            );

                            $checkEmployee->execute([$employeeNumber]);
                        }

                        $stmt = $pdo->prepare("
                            INSERT INTO teachers (
                                user_id,
                                employee_number
                            )
                            VALUES (?, ?)
                        ");

                        $stmt->execute([
                            $userId,
                            $employeeNumber
                        ]);
                    }

                    $pdo->commit();

                    setFlashMessage(
                        'success',
                        'User account created successfully.'
                    );

                    redirect('/student-attendance-system/admin/users.php');

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $errors[] = 'Unable to create the user account. Please try again.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | UPDATE USER
        |--------------------------------------------------------------------------
        */
        elseif ($action === 'update') {

            $userId = (int) ($_POST['user_id'] ?? 0);

            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = strtoupper(trim($_POST['role'] ?? 'TEACHER'));

            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $formData = [
                'name' => $name,
                'email' => $email,
                'role' => $role
            ];

            if ($userId <= 0) {
                $errors[] = 'Invalid user selected.';
            }

            if ($name === '') {
                $errors[] = 'Full name is required.';
            } elseif (mb_strlen($name) < 2) {
                $errors[] = 'Full name must contain at least 2 characters.';
            } elseif (mb_strlen($name) > 100) {
                $errors[] = 'Full name must not exceed 100 characters.';
            }

            if ($email === '') {
                $errors[] = 'Email address is required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please enter a valid email address.';
            } elseif (mb_strlen($email) > 150) {
                $errors[] = 'Email address must not exceed 150 characters.';
            }

            if (!in_array($role, ['ADMIN', 'TEACHER'], true)) {
                $errors[] = 'Invalid user role selected.';
            }

            if ($password !== '' && strlen($password) < 8) {
                $errors[] = 'New password must contain at least 8 characters.';
            }

            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match.';
            }

            /*
            |--------------------------------------------------------------------------
            | Load existing user
            |--------------------------------------------------------------------------
            */

            $existingUser = null;

            if ($userId > 0) {

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([$userId]);

                $existingUser = $stmt->fetch();

                if (!$existingUser) {
                    $errors[] = 'The selected user was not found.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Check duplicate email
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                    AND id != ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $email,
                    $userId
                ]);

                if ($stmt->fetch()) {
                    $errors[] = 'Another user is already using this email address.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Prevent changing the last ADMIN
            |--------------------------------------------------------------------------
            */

            if (
                empty($errors) &&
                $existingUser &&
                $existingUser['role'] === 'ADMIN' &&
                $role !== 'ADMIN'
            ) {

                $stmt = $pdo->query("
                    SELECT COUNT(*)
                    FROM users
                    WHERE role = 'ADMIN'
                ");

                $adminCount = (int) $stmt->fetchColumn();

                if ($adminCount <= 1) {
                    $errors[] = 'You cannot change the role of the last administrator.';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Update user
            |--------------------------------------------------------------------------
            */

            if (empty($errors)) {

                try {

                    $pdo->beginTransaction();

                    if ($password !== '') {

                        $passwordHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET
                                name = ?,
                                email = ?,
                                password_hash = ?,
                                role = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $name,
                            $email,
                            $passwordHash,
                            $role,
                            $userId
                        ]);

                    } else {

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET
                                name = ?,
                                email = ?,
                                role = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $name,
                            $email,
                            $role,
                            $userId
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | TEACHER profile management
                    |--------------------------------------------------------------------------
                    */

                    if ($role === 'TEACHER') {

                        $stmt = $pdo->prepare("
                            SELECT id
                            FROM teachers
                            WHERE user_id = ?
                            LIMIT 1
                        ");

                        $stmt->execute([$userId]);

                        $teacher = $stmt->fetch();

                        if (!$teacher) {

                            $employeeNumber = 'EMP-' . strtoupper(
                                substr(bin2hex(random_bytes(5)), 0, 8)
                            );

                            $checkEmployee = $pdo->prepare("
                                SELECT id
                                FROM teachers
                                WHERE employee_number = ?
                                LIMIT 1
                            ");

                            $checkEmployee->execute([$employeeNumber]);

                            while ($checkEmployee->fetch()) {

                                $employeeNumber = 'EMP-' . strtoupper(
                                    substr(bin2hex(random_bytes(5)), 0, 8)
                                );

                                $checkEmployee->execute([$employeeNumber]);
                            }

                            $stmt = $pdo->prepare("
                                INSERT INTO teachers (
                                    user_id,
                                    employee_number
                                )
                                VALUES (?, ?)
                            ");

                            $stmt->execute([
                                $userId,
                                $employeeNumber
                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | If ADMIN, teacher profile is intentionally NOT deleted.
                    |
                    | This protects historical attendance/assignment relationships.
                    |--------------------------------------------------------------------------
                    */

                    $pdo->commit();

                    /*
                    |--------------------------------------------------------------------------
                    | Update current session if admin edited own account
                    |--------------------------------------------------------------------------
                    */

                    if ((int) currentUserId() === $userId) {

                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $_SESSION['user_role'] = $role;
                    }

                    setFlashMessage(
                        'success',
                        'User account updated successfully.'
                    );

                    redirect('/student-attendance-system/admin/users.php');

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $errors[] = 'Unable to update the user account. Please try again.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | DELETE USER
        |--------------------------------------------------------------------------
        */
        elseif ($action === 'delete') {

            $userId = (int) ($_POST['user_id'] ?? 0);

            if ($userId <= 0) {

                $errors[] = 'Invalid user selected.';

            } elseif ((int) currentUserId() === $userId) {

                $errors[] = 'You cannot delete your own account.';

            } else {

                $stmt = $pdo->prepare("
                    SELECT id, name, role
                    FROM users
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->execute([$userId]);

                $userToDelete = $stmt->fetch();

                if (!$userToDelete) {

                    $errors[] = 'The selected user was not found.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Prevent deleting the last ADMIN
                    |--------------------------------------------------------------------------
                    */

                    if ($userToDelete['role'] === 'ADMIN') {

                        $stmt = $pdo->query("
                            SELECT COUNT(*)
                            FROM users
                            WHERE role = 'ADMIN'
                        ");

                        $adminCount = (int) $stmt->fetchColumn();

                        if ($adminCount <= 1) {
                            $errors[] = 'You cannot delete the last administrator.';
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Delete user
                    |--------------------------------------------------------------------------
                    */

                    if (empty($errors)) {

                        try {

                            $pdo->beginTransaction();

                            /*
                            |--------------------------------------------------------------------------
                            | Check teacher relationships before deletion
                            |--------------------------------------------------------------------------
                            */

                            $stmt = $pdo->prepare("
                                SELECT id
                                FROM teachers
                                WHERE user_id = ?
                                LIMIT 1
                            ");

                            $stmt->execute([$userId]);

                            $teacherRecord = $stmt->fetch();

                            if ($teacherRecord) {

                                $teacherId = (int) $teacherRecord['id'];

                                /*
                                |--------------------------------------------------------------------------
                                | Teaching assignments
                                |--------------------------------------------------------------------------
                                */

                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*)
                                    FROM teaching_assignments
                                    WHERE teacher_id = ?
                                ");

                                $stmt->execute([$teacherId]);

                                $assignmentCount = (int) $stmt->fetchColumn();

                                /*
                                |--------------------------------------------------------------------------
                                | Attendance records
                                |--------------------------------------------------------------------------
                                */

                                $stmt = $pdo->prepare("
                                    SELECT COUNT(*)
                                    FROM attendance
                                    WHERE teacher_id = ?
                                ");

                                $stmt->execute([$teacherId]);

                                $attendanceCount = (int) $stmt->fetchColumn();

                                if ($assignmentCount > 0 || $attendanceCount > 0) {

                                    $pdo->rollBack();

                                    $errors[] =
                                        'This teacher cannot be deleted because they have teaching assignments or attendance records. Remove or reassign those records first.';
                                }
                            }

                            if (empty($errors)) {

                                $stmt = $pdo->prepare("
                                    DELETE FROM users
                                    WHERE id = ?
                                ");

                                $stmt->execute([$userId]);

                                $pdo->commit();

                                setFlashMessage(
                                    'success',
                                    'User account deleted successfully.'
                                );

                                redirect('/student-attendance-system/admin/users.php');
                            }

                        } catch (PDOException $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            $errors[] =
                                'Unable to delete the user account. The account may still be linked to other records.';
                        }
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load edit user
|--------------------------------------------------------------------------
*/

if (isset($_GET['edit'])) {

    $editId = (int) $_GET['edit'];

    if ($editId > 0) {

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.name,
                u.email,
                u.role,
                t.id AS teacher_id,
                t.employee_number,
                t.phone,
                t.department
            FROM users u
            LEFT JOIN teachers t
                ON t.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ");

        $stmt->execute([$editId]);

        $editUser = $stmt->fetch();

        if ($editUser) {

            $formData = [
                'name' => $editUser['name'],
                'email' => $editUser['email'],
                'role' => $editUser['role']
            ];

            $editTeacher = $editUser;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Search and filter
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$roleFilter = strtoupper(trim($_GET['role'] ?? ''));

$allowedRoles = ['ADMIN', 'TEACHER'];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = '';
}

/*
|--------------------------------------------------------------------------
| Load users
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        u.role,
        u.created_at,

        t.id AS teacher_id,
        t.employee_number,
        t.phone,
        t.department,

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

    FROM users u

    LEFT JOIN teachers t
        ON t.user_id = u.id

    WHERE 1 = 1
";

$params = [];

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

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

if ($roleFilter !== '') {

    $sql .= " AND u.role = ? ";

    $params[] = $roleFilter;
}

$sql .= "
    ORDER BY
        CASE
            WHEN u.role = 'ADMIN' THEN 1
            ELSE 2
        END,
        u.name ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_users,
        SUM(role = 'ADMIN') AS total_admins,
        SUM(role = 'TEACHER') AS total_teachers
    FROM users
");

$userStats = $stmt->fetch();

$totalUsers = (int) ($userStats['total_users'] ?? 0);
$totalAdmins = (int) ($userStats['total_admins'] ?? 0);
$totalTeachers = (int) ($userStats['total_teachers'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="dashboard-main">

        <div class="dashboard-page-header">

            <div>
                <span class="dashboard-eyebrow">
                    USER MANAGEMENT
                </span>

                <h1>
                    Users
                </h1>

                <p>
                    Manage administrator and teacher accounts.
                </p>
            </div>

            <div class="page-header-actions">

                <?php if ($editUser): ?>

                    <a
                        href="/student-attendance-system/admin/users.php"
                        class="dashboard-secondary-button"
                    >
                        Cancel Edit
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <?php if ($flash): ?>

            <div class="alert alert-<?= e($flash['type']) ?>">
                <?= e($flash['message']) ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($errors)): ?>

            <div class="alert alert-error">

                <strong>
                    Please correct the following:
                </strong>

                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>

            </div>

        <?php endif; ?>


        <!-- Statistics -->

        <section class="dashboard-stats-grid">

            <div class="stat-card">

                <div class="stat-card-icon">
                    <svg
                        width="21"
                        height="21"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>

                <div>
                    <span class="stat-card-label">
                        Total Users
                    </span>

                    <strong class="stat-card-value">
                        <?= $totalUsers ?>
                    </strong>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-card-icon">

                    <svg
                        width="21"
                        height="21"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M12 2l3 7h7l-5.5 4.5L18.5 21 12 17l-6.5 4L7.5 13.5 2 9h7z"/>
                    </svg>

                </div>

                <div>

                    <span class="stat-card-label">
                        Administrators
                    </span>

                    <strong class="stat-card-value">
                        <?= $totalAdmins ?>
                    </strong>

                </div>

            </div>


            <div class="stat-card">

                <div class="stat-card-icon">

                    <svg
                        width="21"
                        height="21"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>

                </div>

                <div>

                    <span class="stat-card-label">
                        Teachers
                    </span>

                    <strong class="stat-card-value">
                        <?= $totalTeachers ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- Add / Edit Form -->

        <section class="dashboard-card class-form-panel">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        <?= $editUser ? 'Edit User' : 'Create User' ?>
                    </h2>

                    <p>
                        <?= $editUser
                            ? 'Update the selected user account.'
                            : 'Create a new administrator or teacher account.'
                        ?>
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="/student-attendance-system/admin/users.php<?= $editUser ? '?edit=' . (int) $editUser['id'] : '' ?>"
                class="dashboard-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e(csrfToken()) ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="<?= $editUser ? 'update' : 'create' ?>"
                >

                <?php if ($editUser): ?>

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= (int) $editUser['id'] ?>"
                    >

                <?php endif; ?>


                <div class="form-grid">

                    <div class="form-group">

                        <label for="name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="100"
                            value="<?= e($formData['name']) ?>"
                            placeholder="Enter full name"
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
                            maxlength="150"
                            value="<?= e($formData['email']) ?>"
                            placeholder="Enter email address"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="role">
                            Role
                        </label>

                        <select
                            id="role"
                            name="role"
                            required
                        >

                            <option
                                value="TEACHER"
                                <?= $formData['role'] === 'TEACHER' ? 'selected' : '' ?>
                            >
                                Teacher
                            </option>

                            <option
                                value="ADMIN"
                                <?= $formData['role'] === 'ADMIN' ? 'selected' : '' ?>
                            >
                                Administrator
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="password">
                            <?= $editUser
                                ? 'New Password'
                                : 'Password'
                            ?>
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            minlength="8"
                            placeholder="<?= $editUser ? 'Leave blank to keep current password' : 'Minimum 8 characters' ?>"
                            <?= $editUser ? '' : 'required' ?>
                        >

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            placeholder="Confirm password"
                            <?= $editUser ? '' : 'required' ?>
                        >

                    </div>

                </div>


                <?php if ($editUser && $editUser['role'] === 'TEACHER'): ?>

                    <div class="form-info-box">

                        <strong>
                            Teacher Profile
                        </strong>

                        <p>
                            Employee Number:
                            <strong>
                                <?= e($editUser['employee_number'] ?? '-') ?>
                            </strong>
                        </p>

                        <p>
                            Department:
                            <?= e($editUser['department'] ?? '-') ?>
                        </p>

                        <p>
                            Phone:
                            <?= e($editUser['phone'] ?? '-') ?>
                        </p>

                        <p>
                            To manage the teacher's department, phone number,
                            and teaching assignments, use the Teacher Management page.
                        </p>

                        <a
                            href="/student-attendance-system/admin/add-teacher.php?edit=<?= (int) $editUser['teacher_id'] ?>"
                            class="dashboard-secondary-button"
                        >
                            Manage Teacher Profile
                        </a>

                    </div>

                <?php elseif (!$editUser): ?>

                    <div class="form-info-box">

                        <strong>
                            Teacher Account
                        </strong>

                        <p>
                            If you create a Teacher account, the system will
                            automatically create a teacher profile and generate
                            an employee number.
                        </p>

                        <p>
                            You can later add the teacher's phone, department,
                            classes and subjects from Teacher Management.
                        </p>

                    </div>

                <?php endif; ?>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="dashboard-primary-button"
                    >
                        <?= $editUser ? 'Update User' : 'Create User' ?>
                    </button>

                    <?php if ($editUser): ?>

                        <a
                            href="/student-attendance-system/admin/users.php"
                            class="dashboard-secondary-button"
                        >
                            Cancel
                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- Search / Filter -->

        <section class="dashboard-card">

            <div class="dashboard-card-header">

                <div>

                    <h2>
                        User Accounts
                    </h2>

                    <p>
                        Search and manage registered system users.
                    </p>

                </div>

                <span class="record-count">
                    <?= count($users) ?> records
                </span>

            </div>


            <form
                method="GET"
                action="/student-attendance-system/admin/users.php"
                class="student-filter-form"
            >

                <div class="form-group">

                    <label for="search">
                        Search
                    </label>

                    <input
                        type="search"
                        id="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Name, email, employee number..."
                    >

                </div>


                <div class="form-group">

                    <label for="role_filter">
                        Role
                    </label>

                    <select
                        id="role_filter"
                        name="role"
                    >

                        <option value="">
                            All Roles
                        </option>

                        <option
                            value="ADMIN"
                            <?= $roleFilter === 'ADMIN' ? 'selected' : '' ?>
                        >
                            Administrator
                        </option>

                        <option
                            value="TEACHER"
                            <?= $roleFilter === 'TEACHER' ? 'selected' : '' ?>
                        >
                            Teacher
                        </option>

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
                        href="/student-attendance-system/admin/users.php"
                        class="dashboard-secondary-button"
                    >
                        Reset
                    </a>

                </div>

            </form>

        </section>


        <!-- Users Table -->

        <section class="dashboard-card">

            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>
                                User
                            </th>

                            <th>
                                Role
                            </th>

                            <th>
                                Employee Number
                            </th>

                            <th>
                                Department
                            </th>

                            <th>
                                Assignments
                            </th>

                            <th>
                                Attendance
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

                        <?php if (empty($users)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="empty-table-message"
                                >
                                    No users found.
                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($users as $user): ?>

                                <tr>

                                    <td>

                                        <div class="student-table-profile">

                                            <div class="student-table-avatar">

                                                <?= e(
                                                    strtoupper(
                                                        mb_substr(
                                                            $user['name'],
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>

                                            </div>

                                            <div>

                                                <strong>
                                                    <?= e($user['name']) ?>
                                                </strong>

                                                <span class="table-secondary-text">
                                                    <?= e($user['email']) ?>
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    <td>

                                        <?php if ($user['role'] === 'ADMIN'): ?>

                                            <span class="status-badge status-active">
                                                Administrator
                                            </span>

                                        <?php else: ?>

                                            <span class="status-badge status-active">
                                                Teacher
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if ($user['role'] === 'TEACHER'): ?>

                                            <?= e(
                                                $user['employee_number'] ?? '-'
                                            ) ?>

                                        <?php else: ?>

                                            <span class="table-secondary-text">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if ($user['role'] === 'TEACHER'): ?>

                                            <?= e(
                                                $user['department'] ?? '-'
                                            ) ?>

                                        <?php else: ?>

                                            <span class="table-secondary-text">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if ($user['role'] === 'TEACHER'): ?>

                                            <?= (int) $user['assignment_count'] ?>

                                        <?php else: ?>

                                            <span class="table-secondary-text">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if ($user['role'] === 'TEACHER'): ?>

                                            <?= (int) $user['attendance_count'] ?>

                                        <?php else: ?>

                                            <span class="table-secondary-text">
                                                -
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            date(
                                                'd M Y',
                                                strtotime($user['created_at'])
                                            )
                                        ) ?>

                                    </td>


                                    <td>

                                        <div class="table-actions">

                                            <a
                                                href="/student-attendance-system/admin/users.php?edit=<?= (int) $user['id'] ?>"
                                                class="table-action-link"
                                            >
                                                Edit
                                            </a>


                                            <?php if ((int) currentUserId() !== (int) $user['id']): ?>

                                                <form
                                                    method="POST"
                                                    action="/student-attendance-system/admin/users.php"
                                                    onsubmit="return confirm('Are you sure you want to delete this user account?');"
                                                    style="display:inline;"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e(csrfToken()) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="user_id"
                                                        value="<?= (int) $user['id'] ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="table-action-link table-action-danger"
                                                    >
                                                        Delete
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span class="table-secondary-text">
                                                    Current Account
                                                </span>

                                            <?php endif; ?>

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
