<?php

/*
|--------------------------------------------------------------------------
| Student Attendance System
| Reset Password
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
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$success = '';

$token = trim(
    $_GET['token']
    ?? $_POST['token']
    ?? ''
);


/*
|--------------------------------------------------------------------------
| Validate Token Format
|--------------------------------------------------------------------------
*/

if (
    $token === '' ||
    !preg_match(
        '/^[a-f0-9]{64}$/',
        $token
    )
) {

    $error =
        'This password reset link is invalid.';

} else {


    /*
    |--------------------------------------------------------------------------
    | Hash Token
    |--------------------------------------------------------------------------
    */

    $tokenHash =
        hash(
            'sha256',
            $token
        );


    /*
    |--------------------------------------------------------------------------
    | Find Valid Token
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "
        SELECT
            id,
            user_id,
            expires_at,
            used_at
        FROM password_reset_tokens
        WHERE token_hash = ?
        LIMIT 1
        "
    );

    $stmt->execute([
        $tokenHash
    ]);

    $resetToken =
        $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Check Token
    |--------------------------------------------------------------------------
    */

    if (!$resetToken) {

        $error =
            'This password reset link is invalid.';

    } elseif (
        $resetToken['used_at'] !== null
    ) {

        $error =
            'This password reset link has already been used.';

    } elseif (
        strtotime(
            $resetToken['expires_at']
        ) < time()
    ) {

        $error =
            'This password reset link has expired.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | Handle Password Update
        |--------------------------------------------------------------------------
        */

        if (
            $_SERVER['REQUEST_METHOD']
            === 'POST'
        ) {

            $password =
                $_POST['password']
                ?? '';

            $confirmPassword =
                $_POST['confirm_password']
                ?? '';


            /*
            |--------------------------------------------------------------------------
            | Validate Password
            |--------------------------------------------------------------------------
            */

            if (
                $password === '' ||
                $confirmPassword === ''
            ) {

                $error =
                    'Please enter and confirm your new password.';

            } elseif (
                strlen($password) < 8
            ) {

                $error =
                    'Password must be at least 8 characters long.';

            } elseif (
                $password !== $confirmPassword
            ) {

                $error =
                    'Passwords do not match.';

            } else {


                /*
                |--------------------------------------------------------------------------
                | Hash New Password
                |--------------------------------------------------------------------------
                */

                $passwordHash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                /*
                |--------------------------------------------------------------------------
                | Start Transaction
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();

                try {


                    /*
                    |--------------------------------------------------------------------------
                    | Update Password
                    |--------------------------------------------------------------------------
                    */

                    $updateUser =
                        $pdo->prepare(
                            "
                            UPDATE users
                            SET password_hash = ?
                            WHERE id = ?
                            "
                        );

                    $updateUser->execute([
                        $passwordHash,
                        $resetToken['user_id']
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Mark Token Used
                    |--------------------------------------------------------------------------
                    */

                    $updateToken =
                        $pdo->prepare(
                            "
                            UPDATE password_reset_tokens
                            SET used_at = NOW()
                            WHERE id = ?
                            "
                        );

                    $updateToken->execute([
                        $resetToken['id']
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Delete Other Reset Tokens
                    |--------------------------------------------------------------------------
                    */

                    $deleteOtherTokens =
                        $pdo->prepare(
                            "
                            DELETE FROM password_reset_tokens
                            WHERE user_id = ?
                            AND id != ?
                            "
                        );

                    $deleteOtherTokens->execute([
                        $resetToken['user_id'],
                        $resetToken['id']
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Commit
                    |--------------------------------------------------------------------------
                    */

                    $pdo->commit();


                    /*
                    |--------------------------------------------------------------------------
                    | Success
                    |--------------------------------------------------------------------------
                    */

                    $success =
                        'Your password has been reset successfully.';


                } catch (
                    PDOException $e
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Rollback
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $pdo->inTransaction()
                    ) {

                        $pdo->rollBack();
                    }


                    $error =
                        'Unable to reset your password. Please try again.';
                }
            }
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
        Reset Password | Student Attendance System
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
                    SECURE ACCOUNT
                </span>

                <h2>
                    Create a New
                    Password.
                </h2>

                <p>
                    Choose a strong password to
                    protect your school account.
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


                <?php if ($success !== ''): ?>


                    <!-- =================================================
                         SUCCESS
                         ================================================= -->

                    <div class="auth-form-intro">

                        <span class="auth-label">
                            PASSWORD UPDATED
                        </span>

                        <h1>
                            All set
                        </h1>

                        <p>
                            Your password has been
                            updated successfully.
                        </p>

                    </div>


                    <a
                        href="login.php"
                        class="auth-submit"
                        style="
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            text-decoration: none;
                        "
                    >
                        Sign In
                    </a>


                <?php else: ?>


                    <!-- =================================================
                         INTRO
                         ================================================= -->

                    <div class="auth-form-intro">

                        <span class="auth-label">
                            PASSWORD RESET
                        </span>

                        <h1>
                            Set new password
                        </h1>

                        <p>
                            Create a new password for
                            your school account.
                        </p>

                    </div>


                    <!-- Error -->

                    <?php if ($error !== ''): ?>

                        <div class="auth-alert error">

                            <?= e($error) ?>

                        </div>

                    <?php endif; ?>


                    <!-- Form -->

                    <?php if ($error === '' && $token !== ''): ?>

                        <form
                            method="POST"
                            action="reset-password.php"
                            class="auth-form"
                        >

                            <input
                                type="hidden"
                                name="token"
                                value="<?= e($token) ?>"
                            >


                            <!-- New Password -->

                            <div class="form-group">

                                <label
                                    for="password"
                                    class="form-label"
                                >
                                    New Password
                                </label>

                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-input"
                                    placeholder="Enter new password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    required
                                >

                            </div>


                            <!-- Confirm Password -->

                            <div class="form-group">

                                <label
                                    for="confirm_password"
                                    class="form-label"
                                >
                                    Confirm New Password
                                </label>

                                <input
                                    type="password"
                                    id="confirm_password"
                                    name="confirm_password"
                                    class="form-input"
                                    placeholder="Confirm new password"
                                    autocomplete="new-password"
                                    minlength="8"
                                    required
                                >

                            </div>


                            <button
                                type="submit"
                                class="auth-submit"
                            >
                                Reset Password
                            </button>

                        </form>


                        <p class="auth-note">

                            Remember your password?

                            <a href="login.php">
                                Sign In
                            </a>

                        </p>

                    <?php endif; ?>


                <?php endif; ?>


            </div>

        </section>


    </main>

</body>

</html>