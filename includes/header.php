<?php

$pageTitle = $pageTitle ?? 'Student Attendance System';

$pageType = $pageType ?? 'public';

$additionalStyles = $additionalStyles ?? [];

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars($pageTitle) ?></title>


    <?php if ($pageType === 'admin'): ?>

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/dashboard.css"
        >

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/tables.css"
        >

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/forms.css"
        >

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/responsive.css"
        >


    <?php elseif ($pageType === 'teacher'): ?>

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/dashboard.css"
        >

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/tables.css"
        >

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/forms.css"
        >

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/responsive.css"
        >


    <?php else: ?>

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/style.css"
        >

        <link
            rel="stylesheet"
            href="/student-attendance-system/assets/css/responsive.css"
        >

    <?php endif; ?>


    <?php foreach ($additionalStyles as $stylesheet): ?>

        <link
            rel="stylesheet"
            href="<?= htmlspecialchars($stylesheet) ?>"
        >

    <?php endforeach; ?>

</head>

<body>
