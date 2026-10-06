<?php

/*
|--------------------------------------------------------------------------
| General Helper Functions
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Escape HTML Output
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

function redirect(string $url): never
{
    header("Location: $url");

    exit;
}


/*
|--------------------------------------------------------------------------
| Check POST Request
|--------------------------------------------------------------------------
*/

function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}


/*
|--------------------------------------------------------------------------
| Check GET Request
|--------------------------------------------------------------------------
*/

function isGet(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}


/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/

function setFlashMessage(
    string $type,
    string $message
): void {

    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}


/*
|--------------------------------------------------------------------------
| Get Flash Message
|--------------------------------------------------------------------------
*/

function getFlashMessage(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];

    unset($_SESSION['flash']);

    return $flash;
}


/*
|--------------------------------------------------------------------------
| Generate CSRF Token
|--------------------------------------------------------------------------
*/

function csrfToken(): string
{
    if (
        empty($_SESSION['csrf_token'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}


/*
|--------------------------------------------------------------------------
| Verify CSRF Token
|--------------------------------------------------------------------------
*/

function verifyCsrfToken(
    ?string $token
): bool {

    if (
        empty($token) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}


/*
|--------------------------------------------------------------------------
| Generate Student Reference
|--------------------------------------------------------------------------
*/

function generateStudentReference(): string
{
    return 'STU-' .
        strtoupper(
            bin2hex(random_bytes(4))
        );
}