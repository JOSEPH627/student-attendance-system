<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';


/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$email = '';


/*
|--------------------------------------------------------------------------
| Session Timeout Message
|--------------------------------------------------------------------------
*/

if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {

    $error =
        'Your session has expired. Please sign in again.';
}


/*
|--------------------------------------------------------------------------
| Handle Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($email === '' || $password === '') {

        $error =
            'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error =
            'Please enter a valid email address.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | Find User
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            "
            SELECT
                id,
                name,
                email,
                password_hash,
                role
            FROM users
            WHERE email = ?
            LIMIT 1
            "
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Verify Credentials
        |--------------------------------------------------------------------------
        */

        if (
            $user &&
            password_verify(
                $password,
                $user['password_hash']
            )
        ) {


            /*
            |--------------------------------------------------------------------------
            | Regenerate Session ID
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);


            /*
            |--------------------------------------------------------------------------
            | Store User Information
            |--------------------------------------------------------------------------
            */

            $_SESSION['user_id'] =
                (int) $user['id'];

            $_SESSION['user_name'] =
                $user['name'];

            $_SESSION['user_email'] =
                $user['email'];

            $_SESSION['user_role'] =
                $user['role'];


            /*
            |--------------------------------------------------------------------------
            | Start Activity Timer
            |--------------------------------------------------------------------------
            */

            $_SESSION['last_activity'] =
                time();


            /*
            |--------------------------------------------------------------------------
            | Redirect According to Role
            |--------------------------------------------------------------------------
            */

            if ($user['role'] === 'ADMIN') {

                redirect(
                    '/student-attendance-system/admin/dashboard.php'
                );
            }


            if ($user['role'] === 'TEACHER') {

                redirect(
                    '/student-attendance-system/teacher/dashboard.php'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Unknown Role
            |--------------------------------------------------------------------------
            */

            $error =
                'Your account role is not recognized.';


            $_SESSION = [];

            session_destroy();

        } else {

            $error =
                'Invalid email or password.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Sign In | Student Attendance System
    </title>

    <link
        rel="stylesheet"
        href="assets/css/auth.css"
    >

</head>


<body class="auth-page">


    <main class="auth-container">


        <!-- =====================================================
             LEFT VISUAL
             ===================================================== -->

        <section class="auth-visual">

            <img
                src="assets/images/hero-school.jpg"
                alt="Teacher and students in a classroom"
            >

            <div class="auth-visual-content">

                <span class="auth-visual-label">
                    SCHOOL ATTENDANCE
                </span>

                <h2>
                    Organized Records.
                    Better School Management.
                </h2>

                <p>
                    Access the attendance management system
                    using your authorized school account.
                </p>

            </div>

        </section>


        <!-- =====================================================
             LOGIN FORM
             ===================================================== -->

        <section class="auth-form-section">

            <div class="auth-form-wrapper">


                <!-- =================================================
                     BRAND
                     ================================================= -->

                <a
                    href="index.php"
                    class="auth-brand"
                >

                    <span class="auth-brand-icon">
                        SA
                    </span>

                    <span class="auth-brand-name">
                        Student Attendance System
                    </span>

                </a>


                <!-- =================================================
                     INTRODUCTION
                     ================================================= -->

                <div class="auth-form-intro">

                    <span class="auth-label">
                        SECURE ACCESS
                    </span>

                    <h1>
                        Welcome back
                    </h1>

                    <p>
                        Sign in to access your school
                        attendance workspace.
                    </p>

                </div>


                <!-- =================================================
                     ERROR MESSAGE
                     ================================================= -->

                <?php if ($error !== ''): ?>

                    <div class="auth-alert error">

                        <?= e($error) ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                     LOGIN FORM
                     ================================================= -->

                <form
                    method="POST"
                    action="login.php"
                    class="auth-form"
                >


                    <!-- Email -->

                    <div class="form-group">

                        <label
                            for="email"
                            class="form-label"
                        >
                            Email Address
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="Enter your email"
                            value="<?= e($email) ?>"
                            autocomplete="email"
                            required
                        >

                    </div>


                    <!-- Password -->

                    <div class="form-group">

                        <div class="password-row">

                            <label
                                for="password"
                                class="form-label"
                            >
                                Password
                            </label>

                            <a href="forgot-password.php">
                                Forgot password?
                            </a>

                        </div>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                    </div>


                    <!-- Submit -->

                    <button
                        type="submit"
                        class="auth-submit"
                    >
                        Sign In
                    </button>

                </form>


                <!-- =================================================
                     SECURITY NOTE
                     ================================================= -->

                <p class="auth-note">

                    This system is intended for authorized
                    school administrators and teachers.

                </p>


            </div>

        </section>


    </main>

</body>

</html>