<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Session Timeout
|--------------------------------------------------------------------------
| Read timeout from system_settings.
| Default: 1200 seconds (20 minutes)
|--------------------------------------------------------------------------
*/

$sessionTimeout = 1200;

try {

    $stmt = $pdo->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = 'session_timeout'
        LIMIT 1
    ");

    $stmt->execute();

    $configuredTimeout = $stmt->fetchColumn();

    if ($configuredTimeout !== false) {

        $configuredTimeout = (int) $configuredTimeout;

        $allowedTimeouts = [
            900,
            1200,
            1800,
            3600,
            7200
        ];

        if (in_array(
            $configuredTimeout,
            $allowedTimeouts,
            true
        )) {

            $sessionTimeout = $configuredTimeout;
        }
    }

} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Keep default timeout if settings table/query fails
    |--------------------------------------------------------------------------
    */

    $sessionTimeout = 1200;
}


/*
|--------------------------------------------------------------------------
| Check Session Activity
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    $currentTime = time();

    $lastActivity =
        $_SESSION['last_activity']
        ?? $currentTime;


    /*
    |--------------------------------------------------------------------------
    | Session Expired
    |--------------------------------------------------------------------------
    */

    if (($currentTime - $lastActivity) > $sessionTimeout) {

        $_SESSION = [];


        /*
        |--------------------------------------------------------------------------
        | Remove Session Cookie
        |--------------------------------------------------------------------------
        */

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }


        session_destroy();


        header(
            'Location: /student-attendance-system/login.php?timeout=1'
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Update Last Activity
    |--------------------------------------------------------------------------
    */

    $_SESSION['last_activity'] =
        $currentTime;
}


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {

        header(
            'Location: /student-attendance-system/login.php'
        );

        exit;
    }
}


/*
|--------------------------------------------------------------------------
| Require Specific Role
|--------------------------------------------------------------------------
*/

function requireRole(string $requiredRole): void
{
    requireLogin();


    if (
        !isset($_SESSION['user_role']) ||
        $_SESSION['user_role'] !== $requiredRole
    ) {

        http_response_code(403);

        die('Access denied.');
    }
}


/*
|--------------------------------------------------------------------------
| Current User ID
|--------------------------------------------------------------------------
*/

function currentUserId(): ?int
{
    return isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;
}


/*
|--------------------------------------------------------------------------
| Current User Role
|--------------------------------------------------------------------------
*/

function currentUserRole(): ?string
{
    return $_SESSION['user_role'] ?? null;
}


/*
|--------------------------------------------------------------------------
| Current User Name
|--------------------------------------------------------------------------
*/

function currentUserName(): ?string
{
    return $_SESSION['user_name'] ?? null;
}
