<?php

/*
|--------------------------------------------------------------------------
| Student Attendance System
| Forgot Password
|--------------------------------------------------------------------------
*/

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
| Redirect Logged-in Users
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    if ($_SESSION['user_role'] === 'ADMIN') {

        redirect(
            '/student-attendance-system/admin/dashboard.php'
        );
    }

    if ($_SESSION['user_role'] === 'TEACHER') {

        redirect(
            '/student-attendance-system/teacher/dashboard.php'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';

$email = '';


/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim(
        $_POST['email'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Email
    |--------------------------------------------------------------------------
    */

    if ($email === '') {

        $error =
            'Please enter your email address.';

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

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
                email
            FROM users
            WHERE email = ?
            LIMIT 1
            "
        );

        $stmt->execute([$email]);

        $user = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Always Show Generic Message
        |--------------------------------------------------------------------------
        |
        | We do not reveal whether an email exists.
        | This prevents account enumeration.
        |
        */

        $success =
            'If an account exists with this email address, '
            . 'a password reset link has been prepared.';


        /*
        |--------------------------------------------------------------------------
        | Generate Reset Token
        |--------------------------------------------------------------------------
        */

        if ($user) {

            /*
            |--------------------------------------------------------------------------
            | Remove Previous Unused Tokens
            |--------------------------------------------------------------------------
            */

            $deleteStmt = $pdo->prepare(
                "
                DELETE FROM password_reset_tokens
                WHERE user_id = ?
                AND used_at IS NULL
                "
            );

            $deleteStmt->execute([
                $user['id']
            ]);


            /*
            |--------------------------------------------------------------------------
            | Generate Secure Token
            |--------------------------------------------------------------------------
            */

            $plainToken =
                bin2hex(
                    random_bytes(32)
                );


            /*
            |--------------------------------------------------------------------------
            | Hash Token Before Database Storage
            |--------------------------------------------------------------------------
            */

            $tokenHash =
                hash(
                    'sha256',
                    $plainToken
                );


            /*
            |--------------------------------------------------------------------------
            | Token Expiry
            |--------------------------------------------------------------------------
            |
            | Token remains valid for 60 minutes.
            |
            */

            $expiresAt =
                date(
                    'Y-m-d H:i:s',
                    time() + 3600
                );


            /*
            |--------------------------------------------------------------------------
            | Store Token
            |--------------------------------------------------------------------------
            */

            $insertStmt = $pdo->prepare(
                "
                INSERT INTO password_reset_tokens
                (
                    user_id,
                    token_hash,
                    expires_at
                )
                VALUES
                (
                    ?,
                    ?,
                    ?
                )
                "
            );

            $insertStmt->execute([
                $user['id'],
                $tokenHash,
                $expiresAt
            ]);


            /*
            |--------------------------------------------------------------------------
            | Reset URL
            |--------------------------------------------------------------------------
            */

            $resetUrl =
                'http://localhost/'
                . 'student-attendance-system/'
                . 'reset-password.php?token='
                . urlencode($plainToken);


            /*
            |--------------------------------------------------------------------------
            | Development Mode
            |--------------------------------------------------------------------------
            |
            | For local development only.
            |
            | Later, this will be replaced with a real
            | email service.
            |
            */

            $_SESSION['password_reset_link'] =
                $resetUrl;
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
        Forgot Password | Student Attendance System
    </title>

    <link
        rel="stylesheet"
        href="assets/css/auth.css"
    >

</head>

<body class="auth-page">


    <main class="auth-container">


        <!-- =================================================
             LEFT VISUAL
             ================================================= -->

        <section class="auth-visual">

            <img
                src="assets/images/hero-school.jpg"
                alt="Teacher and students in a classroom"
            >

            <div class="auth-visual-content">

                <span class="auth-visual-label">
                    ACCOUNT RECOVERY
                </span>

                <h2>
                    Regain Access
                    Securely.
                </h2>

                <p>
                    Recover access to your school
                    attendance account using your
                    registered email address.
                </p>

            </div>

        </section>


        <!-- =================================================
             FORM SIDE
             ================================================= -->

        <section class="auth-form-section">

            <div class="auth-form-wrapper">


                <!-- Brand -->

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


                <!-- Introduction -->

                <div class="auth-form-intro">

                    <span class="auth-label">
                        ACCOUNT RECOVERY
                    </span>

                    <h1>
                        Forgot password?
                    </h1>

                    <p>
                        Enter the email address associated
                        with your school account.
                    </p>

                </div>


                <!-- Error -->

                <?php if ($error !== ''): ?>

                    <div class="auth-alert error">

                        <?= e($error) ?>

                    </div>

                <?php endif; ?>


                <!-- Success -->

                <?php if ($success !== ''): ?>

                    <div class="auth-alert success">

                        <?= e($success) ?>

                    </div>


                    <?php if (
                        isset(
                            $_SESSION[
                                'password_reset_link'
                            ]
                        )
                    ): ?>

                        <div class="auth-reset-preview">

                            <strong>
                                Development Reset Link
                            </strong>

                            <p>
                                For local development,
                                use the link below.
                            </p>

                            <a
                                href="<?= e(
                                    $_SESSION[
                                        'password_reset_link'
                                    ]
                                ) ?>"
                            >
                                Open Password Reset
                            </a>

                        </div>

                        <?php
                        unset(
                            $_SESSION[
                                'password_reset_link'
                            ]
                        );
                        ?>

                    <?php endif; ?>

                <?php endif; ?>


                <!-- Form -->

                <form
                    method="POST"
                    action="forgot-password.php"
                    class="auth-form"
                >


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


                    <button
                        type="submit"
                        class="auth-submit"
                    >
                        Send Reset Link
                    </button>

                </form>


                <!-- Back to Login -->

                <p class="auth-note">

                    Remember your password?

                    <a
                        href="login.php"
                    >
                        Sign In
                    </a>

                </p>


            </div>

        </section>


    </main>

</body>

</html>