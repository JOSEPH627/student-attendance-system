<?php

$pageTitle = 'Features | Student Attendance System';

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
        <?= htmlspecialchars($pageTitle) ?>
    </title>

    <link
        rel="stylesheet"
        href="assets/css/style.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/responsive.css"
    >

</head>

<body>


<!-- =========================================================
     HEADER
========================================================= -->

<header class="site-header">

    <div class="container header-container">

        <a
            href="index.php"
            class="brand"
        >

            <span class="brand-icon">
                SA
            </span>

            <span class="brand-name">
                Student Attendance System
            </span>

        </a>


        <button
            type="button"
            class="mobile-nav-toggle"
            id="mobileNavToggle"
            aria-label="Open navigation menu"
            aria-expanded="false"
        >

            <span></span>
            <span></span>
            <span></span>

        </button>


        <nav
            class="main-navigation"
            id="mainNavigation"
            aria-label="Main navigation"
        >

            <a href="index.php">
                Home
            </a>

            <a href="features.php">
                Features
            </a>

            <a href="about.php">
                About
            </a>

            <a href="contact.php">
                Contact
            </a>

            <a
                href="login.php"
                class="login-button"
            >
                Sign In
            </a>

        </nav>

    </div>

</header>


<!-- =========================================================
     PAGE HERO
========================================================= -->

<section class="hero-section">

    <div class="container hero-container">

        <div class="hero-content">

            <span class="hero-label">
                Powerful Features
            </span>


            <h1>

                Everything You Need to
                <span>
                    Manage Attendance
                </span>

            </h1>


            <p>
                A simple and reliable attendance management
                system designed to make student attendance
                easier, faster and more organized.
            </p>


            <div class="hero-actions">

                <a
                    href="login.php"
                    class="primary-button"
                >
                    Get Started
                </a>


                <a
                    href="contact.php"
                    class="secondary-button"
                >
                    Contact Us
                </a>

            </div>

        </div>


        <div class="hero-visual">

            <div class="hero-image-wrapper">

                <img
                    src="assets/images/hero-school.jpg"
                    alt="School attendance management"
                >


                <div class="hero-image-label">

                    <div class="label-line">

                        <strong>
                            Organized
                        </strong>

                        <span>
                            Centralized records
                        </span>

                    </div>


                    <div class="label-line">

                        <strong>
                            Efficient
                        </strong>

                        <span>
                            Faster attendance
                        </span>

                    </div>


                    <div class="label-line">

                        <strong>
                            Secure
                        </strong>

                        <span>
                            Role-based access
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     FEATURES
========================================================= -->

<section class="features-section">

    <div class="container">


        <div class="section-intro">

            <span class="section-label">
                Our Features
            </span>

            <h2>
                Built for Schools and Teachers
            </h2>

            <p>
                Manage students, teachers, classes and
                attendance from one centralized system.
            </p>

        </div>


        <div class="features-grid">


            <!-- STUDENT MANAGEMENT -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/student-records.jpg"
                        alt="Student management"
                    >

                </div>


                <div class="feature-content">

                    <span class="feature-number">
                        01
                    </span>

                    <h3>
                        Student Management
                    </h3>

                    <p>
                        Add, update, search and manage student
                        information in one organized location.
                    </p>

                </div>

            </article>


            <!-- TEACHER MANAGEMENT -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/teacher-management.jpg"
                        alt="Teacher management"
                    >

                </div>


                <div class="feature-content">

                    <span class="feature-number">
                        02
                    </span>

                    <h3>
                        Teacher Management
                    </h3>

                    <p>
                        Manage teachers and their teaching
                        assignments efficiently.
                    </p>

                </div>

            </article>


            <!-- CLASS MANAGEMENT -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/class-management.jpg"
                        alt="Class management"
                    >

                </div>


                <div class="feature-content">

                    <span class="feature-number">
                        03
                    </span>

                    <h3>
                        Class Management
                    </h3>

                    <p>
                        Create classes and connect students,
                        teachers and subjects easily.
                    </p>

                </div>

            </article>


            <!-- ATTENDANCE TRACKING -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/attendance-records.jpg"
                        alt="Attendance tracking"
                    >

                </div>


                <div class="feature-content">

                    <span class="feature-number">
                        04
                    </span>

                    <h3>
                        Attendance Tracking
                    </h3>

                    <p>
                        Record students as present, absent or
                        on permission with a clear attendance
                        workflow.
                    </p>

                </div>

            </article>


            <!-- REPORTS -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/attendance-reports.jpg"
                        alt="Attendance reports"
                    >

                </div>


                <div class="feature-content">

                    <span class="feature-number">
                        05
                    </span>

                    <h3>
                        Reports
                    </h3>

                    <p>
                        View attendance statistics and generate
                        useful reports for better decision making.
                    </p>

                </div>

            </article>


            <!-- SECURE ACCESS -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/subject-management.jpg"
                        alt="Secure system access"
                    >

                </div>


                <div class="feature-content">

                    <span class="feature-number">
                        06
                    </span>

                    <h3>
                        Secure Access
                    </h3>

                    <p>
                        Role-based access helps keep administrative
                        and teacher functions properly separated.
                    </p>

                </div>

            </article>

        </div>

    </div>

</section>


<!-- =========================================================
     INFORMATION SECTION
========================================================= -->

<section class="information-section">

    <div class="container information-container">


        <div class="information-image">

            <img
                src="assets/images/school-classroom.jpg"
                alt="School classroom"
            >

        </div>


        <div class="information-content">

            <span class="section-label">
                Simple and Organized
            </span>


            <h2>
                Everything Connected in One System
            </h2>


            <p>
                The system brings important school attendance
                activities together so administrators and teachers
                can work more efficiently.
            </p>


            <div class="information-list">


                <div class="information-item">

                    <span class="information-number">
                        01
                    </span>

                    <div>

                        <h3>
                            Manage Students
                        </h3>

                        <p>
                            Keep student records organized and
                            easily accessible.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        02
                    </span>

                    <div>

                        <h3>
                            Organize Classes
                        </h3>

                        <p>
                            Connect classes, subjects and
                            students in one place.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        03
                    </span>

                    <div>

                        <h3>
                            Record Attendance
                        </h3>

                        <p>
                            Quickly record and review daily
                            attendance information.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        04
                    </span>

                    <div>

                        <h3>
                            Review Reports
                        </h3>

                        <p>
                            Use attendance information to support
                            better school decisions.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     CALL TO ACTION
========================================================= -->

<section class="contact-section">

    <div class="container contact-container">


        <div>

            <span class="section-label">
                Get Started
            </span>


            <h2>
                Ready to Manage Attendance Better?
            </h2>


            <p>
                Sign in and start using the Student
                Attendance System.
            </p>


            <a
                href="login.php"
                class="primary-button"
            >
                Sign In
            </a>

        </div>


        <div class="contact-information">


            <div class="contact-item">

                <strong>
                    Students
                </strong>

                <span>
                    Organized records
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Teachers
                </strong>

                <span>
                    Manage assignments
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Attendance
                </strong>

                <span>
                    Present, absent & permission
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Reports
                </strong>

                <span>
                    Useful attendance insights
                </span>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="site-footer">

    <div class="container footer-top">


        <div class="footer-brand">

            <a
                href="index.php"
                class="brand"
            >

                <span class="brand-icon">
                    SA
                </span>

                <span class="brand-name">
                    Student Attendance System
                </span>

            </a>


            <p>
                A simple and reliable system for managing
                student attendance, students, teachers,
                classes and reports.
            </p>

        </div>


        <div class="footer-column">

            <h3>
                Quick Links
            </h3>

            <a href="index.php">
                Home
            </a>

            <a href="features.php">
                Features
            </a>

            <a href="about.php">
                About
            </a>

            <a href="contact.php">
                Contact
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Account
            </h3>

            <a href="login.php">
                Sign In
            </a>

            <a href="forgot-password.php">
                Forgot Password
            </a>

        </div>

    </div>


    <div class="container footer-bottom">

        <p>
            &copy; <?= date('Y') ?>
            Student Attendance System.
            All rights reserved.
        </p>

    </div>

</footer>


<!-- =========================================================
     MOBILE NAVIGATION
========================================================= -->

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const mobileNavToggle =
            document.getElementById(
                'mobileNavToggle'
            );

        const mainNavigation =
            document.getElementById(
                'mainNavigation'
            );


        if (
            !mobileNavToggle ||
            !mainNavigation
        ) {
            return;
        }


        function closeNavigation() {

            mainNavigation.classList.remove(
                'mobile-nav-open'
            );

            mobileNavToggle.classList.remove(
                'mobile-nav-active'
            );

            mobileNavToggle.setAttribute(
                'aria-expanded',
                'false'
            );

            mobileNavToggle.setAttribute(
                'aria-label',
                'Open navigation menu'
            );

        }


        mobileNavToggle.addEventListener(
            'click',
            function () {

                const isOpen =
                    mainNavigation.classList.toggle(
                        'mobile-nav-open'
                    );


                mobileNavToggle.classList.toggle(
                    'mobile-nav-active',
                    isOpen
                );


                mobileNavToggle.setAttribute(
                    'aria-expanded',
                    isOpen
                        ? 'true'
                        : 'false'
                );


                mobileNavToggle.setAttribute(
                    'aria-label',
                    isOpen
                        ? 'Close navigation menu'
                        : 'Open navigation menu'
                );

            }
        );


        const navigationLinks =
            mainNavigation.querySelectorAll(
                'a'
            );


        navigationLinks.forEach(
            function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        closeNavigation();

                    }
                );

            }
        );


        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth > 768
                ) {

                    closeNavigation();

                }

            }
        );


        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key === 'Escape'
                ) {

                    closeNavigation();

                }

            }
        );

    }
);

</script>


</body>

</html>