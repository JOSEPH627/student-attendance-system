<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');

$pageTitle = 'Settings';

/*
|--------------------------------------------------------------------------
| Load dashboard/settings CSS
|--------------------------------------------------------------------------
*/

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/forms.css',
    '/student-attendance-system/assets/css/tables.css'
];

$successMessage = '';
$errorMessage = '';

/*
|--------------------------------------------------------------------------
| Current administrator
|--------------------------------------------------------------------------
*/

$adminId = currentUserId();

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        password_hash
    FROM users
    WHERE id = ?
      AND role = 'ADMIN'
    LIMIT 1
");

$stmt->execute([$adminId]);

$admin = $stmt->fetch();

if (!$admin) {
    http_response_code(403);
    die('Administrator account not found.');
}


/*
|--------------------------------------------------------------------------
| Default system settings
|--------------------------------------------------------------------------
*/

$defaultSettings = [
    'system_name' => 'Student Attendance System',
    'school_name' => '',
    'academic_year' => date('Y') . '/' . (date('Y') + 1),
    'session_timeout' => '1200',
    'permission_reason_required' => '1'
];


/*
|--------------------------------------------------------------------------
| Load saved settings
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        setting_key,
        setting_value
    FROM system_settings
");

$storedSettings = [];

foreach ($stmt->fetchAll() as $setting) {
    $storedSettings[$setting['setting_key']] =
        $setting['setting_value'];
}

$settings = array_merge(
    $defaultSettings,
    $storedSettings
);


/*
|--------------------------------------------------------------------------
| Handle POST requests
|--------------------------------------------------------------------------
*/

if (isPost()) {

    /*
    |--------------------------------------------------------------------------
    | CSRF validation
    |--------------------------------------------------------------------------
    */

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {

        $errorMessage =
            'Invalid security token. Please refresh the page and try again.';

    } else {

        $action = $_POST['action'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | SAVE SYSTEM SETTINGS
        |--------------------------------------------------------------------------
        */

        if ($action === 'save_system_settings') {

            $systemName = trim(
                $_POST['system_name'] ?? ''
            );

            $schoolName = trim(
                $_POST['school_name'] ?? ''
            );

            $academicYear = trim(
                $_POST['academic_year'] ?? ''
            );


            if ($systemName === '') {

                $errorMessage =
                    'System name is required.';

            } elseif ($academicYear === '') {

                $errorMessage =
                    'Academic year is required.';

            } else {

                $newSettings = [
                    'system_name' => $systemName,
                    'school_name' => $schoolName,
                    'academic_year' => $academicYear
                ];

                try {

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings
                            (
                                setting_key,
                                setting_value
                            )
                        VALUES
                            (?, ?)
                        ON DUPLICATE KEY UPDATE
                            setting_value = VALUES(setting_value)
                    ");

                    foreach ($newSettings as $key => $value) {

                        $stmt->execute([
                            $key,
                            $value
                        ]);
                    }

                    $pdo->commit();

                    $settings = array_merge(
                        $settings,
                        $newSettings
                    );

                    $successMessage =
                        'System settings updated successfully.';

                } catch (PDOException $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    $errorMessage =
                        'Unable to save system settings.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE ATTENDANCE SETTINGS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'save_attendance_settings') {

            $permissionReasonRequired =
                isset($_POST['permission_reason_required'])
                    ? '1'
                    : '0';


            try {

                $stmt = $pdo->prepare("
                    INSERT INTO system_settings
                        (
                            setting_key,
                            setting_value
                        )
                    VALUES
                        (?, ?)
                    ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value)
                ");

                $stmt->execute([
                    'permission_reason_required',
                    $permissionReasonRequired
                ]);

                $settings['permission_reason_required'] =
                    $permissionReasonRequired;

                $successMessage =
                    'Attendance settings updated successfully.';

            } catch (PDOException $e) {

                $errorMessage =
                    'Unable to save attendance settings.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE SECURITY SETTINGS
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'save_security_settings') {

            $sessionTimeout =
                (int) ($_POST['session_timeout'] ?? 1200);


            $allowedTimeouts = [
                900,
                1200,
                1800,
                3600,
                7200
            ];


            if (!in_array(
                $sessionTimeout,
                $allowedTimeouts,
                true
            )) {

                $errorMessage =
                    'Invalid session timeout selected.';

            } else {

                try {

                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings
                            (
                                setting_key,
                                setting_value
                            )
                        VALUES
                            (?, ?)
                        ON DUPLICATE KEY UPDATE
                            setting_value = VALUES(setting_value)
                    ");

                    $stmt->execute([
                        'session_timeout',
                        (string) $sessionTimeout
                    ]);

                    $settings['session_timeout'] =
                        (string) $sessionTimeout;

                    $successMessage =
                        'Security settings updated successfully.';

                } catch (PDOException $e) {

                    $errorMessage =
                        'Unable to save security settings.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE ADMIN PROFILE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update_profile') {

            $name = trim(
                $_POST['name'] ?? ''
            );

            $email = trim(
                $_POST['email'] ?? ''
            );


            if ($name === '') {

                $errorMessage =
                    'Name is required.';

            } elseif (
                $email === '' ||
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                $errorMessage =
                    'Please enter a valid email address.';

            } else {

                /*
                | Check duplicate email
                */

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                      AND id != ?
                    LIMIT 1
                ");

                $stmt->execute([
                    $email,
                    $adminId
                ]);


                if ($stmt->fetch()) {

                    $errorMessage =
                        'Another user is already using that email address.';

                } else {

                    try {

                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET
                                name = ?,
                                email = ?
                            WHERE id = ?
                              AND role = 'ADMIN'
                        ");

                        $stmt->execute([
                            $name,
                            $email,
                            $adminId
                        ]);


                        /*
                        | Update current session
                        */

                        $_SESSION['user_name'] =
                            $name;

                        $_SESSION['user_email'] =
                            $email;


                        /*
                        | Update local admin data
                        */

                        $admin['name'] =
                            $name;

                        $admin['email'] =
                            $email;


                        $successMessage =
                            'Administrator profile updated successfully.';

                    } catch (PDOException $e) {

                        $errorMessage =
                            'Unable to update administrator profile.';
                    }
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | CHANGE PASSWORD
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'change_password') {

            $currentPassword =
                $_POST['current_password'] ?? '';

            $newPassword =
                $_POST['new_password'] ?? '';

            $confirmPassword =
                $_POST['confirm_password'] ?? '';


            if ($currentPassword === '') {

                $errorMessage =
                    'Enter your current password.';

            } elseif (
                !password_verify(
                    $currentPassword,
                    $admin['password_hash']
                )
            ) {

                $errorMessage =
                    'Current password is incorrect.';

            } elseif (
                strlen($newPassword) < 8
            ) {

                $errorMessage =
                    'New password must contain at least 8 characters.';

            } elseif (
                $newPassword !== $confirmPassword
            ) {

                $errorMessage =
                    'New password and confirmation password do not match.';

            } elseif (
                $newPassword === $currentPassword
            ) {

                $errorMessage =
                    'New password must be different from your current password.';

            } else {

                try {

                    $newPasswordHash =
                        password_hash(
                            $newPassword,
                            PASSWORD_DEFAULT
                        );


                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET password_hash = ?
                        WHERE id = ?
                          AND role = 'ADMIN'
                    ");

                    $stmt->execute([
                        $newPasswordHash,
                        $adminId
                    ]);


                    $admin['password_hash'] =
                        $newPasswordHash;


                    $successMessage =
                        'Password changed successfully.';

                } catch (PDOException $e) {

                    $errorMessage =
                        'Unable to change password.';
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Session timeout labels
|--------------------------------------------------------------------------
*/

$sessionTimeoutSeconds =
    (int) $settings['session_timeout'];


$sessionTimeoutLabels = [
    900 => '15 minutes',
    1200 => '20 minutes',
    1800 => '30 minutes',
    3600 => '1 hour',
    7200 => '2 hours'
];


$sessionTimeoutLabel =
    $sessionTimeoutLabels[
        $sessionTimeoutSeconds
    ] ?? '20 minutes';


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="dashboard-layout">

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


    <main class="dashboard-main">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="page-header">

            <div>

                <h1>Settings</h1>

                <p>
                    Manage system configuration, attendance,
                    security and administrator preferences.
                </p>

            </div>

        </div>


        <!-- =====================================================
             SUCCESS MESSAGE
        ====================================================== -->

        <?php if ($successMessage): ?>

            <div class="alert alert-success">

                <?= e($successMessage) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             ERROR MESSAGE
        ====================================================== -->

        <?php if ($errorMessage): ?>

            <div class="alert alert-error">

                <?= e($errorMessage) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             SYSTEM SETTINGS
        ====================================================== -->

        <section class="dashboard-card settings-card">


            <div class="settings-card-header">

                <div>

                    <span class="settings-section-icon">
                        ⚙
                    </span>

                    <div>

                        <h2>
                            System Settings
                        </h2>

                        <p>
                            Basic information used throughout
                            the attendance system.
                        </p>

                    </div>

                </div>

            </div>


            <form
                method="POST"
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
                    value="save_system_settings"
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label for="system_name">
                            System Name
                        </label>

                        <input
                            type="text"
                            id="system_name"
                            name="system_name"
                            value="<?= e($settings['system_name']) ?>"
                            maxlength="100"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="school_name">
                            School / Institution Name
                        </label>

                        <input
                            type="text"
                            id="school_name"
                            name="school_name"
                            value="<?= e($settings['school_name']) ?>"
                            maxlength="150"
                            placeholder="Enter school or institution name"
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
                            value="<?= e($settings['academic_year']) ?>"
                            maxlength="20"
                            placeholder="2026/2027"
                            required
                        >

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save System Settings
                    </button>

                </div>

            </form>

        </section>


        <!-- =====================================================
             ATTENDANCE SETTINGS
        ====================================================== -->

        <section class="dashboard-card settings-card">


            <div class="settings-card-header">

                <div>

                    <span class="settings-section-icon">
                        ✓
                    </span>

                    <div>

                        <h2>
                            Attendance Settings
                        </h2>

                        <p>
                            Configure how attendance records
                            are handled.
                        </p>

                    </div>

                </div>

            </div>


            <form
                method="POST"
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
                    value="save_attendance_settings"
                >


                <div class="settings-option">

                    <div>

                        <strong>
                            Require permission reason
                        </strong>

                        <p>
                            Teachers must provide a reason when
                            marking a student as Permission.
                        </p>

                    </div>


                    <label class="settings-switch">

                        <input
                            type="checkbox"
                            name="permission_reason_required"
                            value="1"
                            <?= $settings['permission_reason_required'] === '1'
                                ? 'checked'
                                : '' ?>
                        >

                        <span class="settings-slider"></span>

                    </label>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Attendance Settings
                    </button>

                </div>

            </form>

        </section>


        <!-- =====================================================
             SECURITY SETTINGS
        ====================================================== -->

        <section class="dashboard-card settings-card">


            <div class="settings-card-header">

                <div>

                    <span class="settings-section-icon">
                        🔒
                    </span>

                    <div>

                        <h2>
                            Security Settings
                        </h2>

                        <p>
                            Control basic session security
                            for system users.
                        </p>

                    </div>

                </div>

            </div>


            <form
                method="POST"
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
                    value="save_security_settings"
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label for="session_timeout">
                            Session Timeout
                        </label>

                        <select
                            id="session_timeout"
                            name="session_timeout"
                        >

                            <?php foreach (
                                $sessionTimeoutLabels
                                as $seconds => $label
                            ): ?>

                                <option
                                    value="<?= $seconds ?>"
                                    <?= $sessionTimeoutSeconds === $seconds
                                        ? 'selected'
                                        : '' ?>
                                >
                                    <?= e($label) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>


                        <small class="form-help">

                            Users will automatically be signed
                            out after this period of inactivity.

                        </small>

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Security Settings
                    </button>

                </div>

            </form>

        </section>


        <!-- =====================================================
             ADMINISTRATOR PROFILE
        ====================================================== -->

        <section class="dashboard-card settings-card">


            <div class="settings-card-header">

                <div>

                    <span class="settings-section-icon">
                        👤
                    </span>

                    <div>

                        <h2>
                            Administrator Profile
                        </h2>

                        <p>
                            Update your administrator name
                            and email address.
                        </p>

                    </div>

                </div>

            </div>


            <form
                method="POST"
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
                    value="update_profile"
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label for="name">
                            Full Name
                        </label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="<?= e($admin['name']) ?>"
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
                            value="<?= e($admin['email']) ?>"
                            maxlength="150"
                            required
                        >

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Update Profile
                    </button>

                </div>

            </form>

        </section>


        <!-- =====================================================
             CHANGE PASSWORD
        ====================================================== -->

        <section class="dashboard-card settings-card">


            <div class="settings-card-header">

                <div>

                    <span class="settings-section-icon">
                        🔑
                    </span>

                    <div>

                        <h2>
                            Change Password
                        </h2>

                        <p>
                            Change the password used to access
                            your administrator account.
                        </p>

                    </div>

                </div>

            </div>


            <form
                method="POST"
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
                    value="change_password"
                >


                <div class="form-grid">


                    <div class="form-group">

                        <label for="current_password">
                            Current Password
                        </label>

                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            autocomplete="current-password"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="new_password">
                            New Password
                        </label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <small class="form-help">
                            Minimum 8 characters.
                        </small>

                    </div>


                    <div class="form-group">

                        <label for="confirm_password">
                            Confirm New Password
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Change Password
                    </button>

                </div>

            </form>

        </section>


    </main>

</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>
