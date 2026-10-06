<?php

$pageTitle = 'Student Attendance System';

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
     HERO SECTION
========================================================= -->

<section
    class="hero-section"
    id="home"
>

    <div class="container hero-container">


        <div class="hero-content">

            <span class="hero-label">
                Student Attendance Management
            </span>


            <h1>

                Manage Student Attendance

                <span>
                    Easily and Efficiently
                </span>

            </h1>


            <p>
                A simple and reliable attendance management
                system designed to help schools manage
                students, teachers, classes and attendance
                records efficiently.
            </p>


            <div class="hero-actions">

                <a
                    href="login.php"
                    class="primary-button"
                >
                    Get Started
                </a>


                <a
                    href="features.php"
                    class="secondary-button"
                >
                    Explore Features
                </a>

            </div>

        </div>


        <div class="hero-visual">

            <div class="hero-image-wrapper">

                <img
                    src="assets/images/hero-school.jpg"
                    alt="School classroom"
                >


                <div class="hero-image-label">

                    <div class="label-line">

                        <strong>
                            Simple
                        </strong>

                        <span>
                            Easy to use
                        </span>

                    </div>


                    <div class="label-line">

                        <strong>
                            Reliable
                        </strong>

                        <span>
                            Organized records
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
     FEATURES SECTION
========================================================= -->

<section
    class="features-section"
    id="features"
>

    <div class="container">


        <div class="section-intro">

            <span class="section-label">
                Features
            </span>

            <h2>
                Everything You Need
            </h2>

            <p>
                Powerful tools to make student attendance
                management easier, faster and more organized.
            </p>

        </div>


        <div class="features-grid">


            <!-- Student Management -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/student-records.jpg"
                        alt="Student records management"
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
                        information in one organized system.
                    </p>

                </div>

            </article>


            <!-- Teacher Management -->

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
                        Manage teachers and their class and
                        subject assignments efficiently.
                    </p>

                </div>

            </article>


            <!-- Class Management -->

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
                        Organize classes, subjects and students
                        in a centralized system.
                    </p>

                </div>

            </article>


            <!-- Attendance Tracking -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/attendance-records.jpg"
                        alt="Attendance records"
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
                        on permission quickly and accurately.
                    </p>

                </div>

            </article>


            <!-- Reports -->

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
                        useful reports for better decisions.
                    </p>

                </div>

            </article>


            <!-- Subjects -->

            <article class="feature-card">

                <div class="feature-image">

                    <img
                        src="assets/images/subject-management.jpg"
                        alt="Subject management"
                    >

                </div>


                <div class="feature-content">

                    <span class="feature-number">
                        06
                    </span>

                    <h3>
                        Subject Management
                    </h3>

                    <p>
                        Keep subjects organized and connect them
                        with classes and teacher assignments.
                    </p>

                </div>

            </article>

        </div>


        <div class="section-action">

            <a
                href="features.php"
                class="secondary-button"
            >
                View All Features
            </a>

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
                alt="Students in a classroom"
            >

        </div>


        <div class="information-content">

            <span class="section-label">
                Why This System
            </span>


            <h2>
                Make Attendance Management Simpler
            </h2>


            <p>
                The Student Attendance System gives schools
                a centralized way to manage students, teachers,
                classes, subjects and attendance records.
            </p>


            <div class="information-list">


                <div class="information-item">

                    <span class="information-number">
                        01
                    </span>

                    <div>

                        <h3>
                            Reduce Manual Work
                        </h3>

                        <p>
                            Record and manage attendance without
                            depending on scattered paper records.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        02
                    </span>

                    <div>

                        <h3>
                            Improve Accuracy
                        </h3>

                        <p>
                            Keep attendance information organized
                            and reduce errors in record keeping.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        03
                    </span>

                    <div>

                        <h3>
                            Access Useful Reports
                        </h3>

                        <p>
                            Review attendance statistics and
                            identify important attendance trends.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        04
                    </span>

                    <div>

                        <h3>
                            Keep Access Secure
                        </h3>

                        <p>
                            Separate administrator and teacher
                            functions using role-based access.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     ABOUT SECTION
========================================================= -->

<section
    class="about-section"
    id="about"
>

    <div class="container about-container">


        <div>

            <span class="section-label">
                About the System
            </span>


            <h2>
                A Better Way to Manage Attendance
            </h2>


            <p>
                The Student Attendance System provides schools
                with a centralized platform for managing
                students, teachers, classes, subjects and
                attendance records.
            </p>


            <p>
                Administrators can manage the system while
                teachers can record and review attendance
                for their assigned classes.
            </p>


            <a
                href="about.php"
                class="secondary-button"
            >
                Learn More
            </a>

        </div>


        <div class="about-points">


            <div class="about-point">

                <span class="point-number">
                    01
                </span>

                <div>

                    <h3>
                        Centralized Management
                    </h3>

                    <p>
                        Keep important attendance and school
                        information in one organized platform.
                    </p>

                </div>

            </div>


            <div class="about-point">

                <span class="point-number">
                    02
                </span>

                <div>

                    <h3>
                        Role-Based Access
                    </h3>

                    <p>
                        Administrators and teachers receive
                        access according to their responsibilities.
                    </p>

                </div>

            </div>


            <div class="about-point">

                <span class="point-number">
                    03
                </span>

                <div>

                    <h3>
                        Better Organization
                    </h3>

                    <p>
                        Manage students, classes, subjects and
                        attendance records more efficiently.
                    </p>

                </div>

            </div>


            <div class="about-point">

                <span class="point-number">
                    04
                </span>

                <div>

                    <h3>
                        Reliable Records
                    </h3>

                    <p>
                        Maintain accessible attendance records
                        that can support school decision-making.
                    </p>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     CONTACT SECTION
========================================================= -->

<section
    class="contact-section"
    id="contact"
>

    <div class="container contact-container">


        <div>

            <span class="section-label">
                Contact
            </span>


            <h2>
                Need Help?
            </h2>


            <p>
                Have questions about the Student Attendance
                System? We are here to help.
            </p>


            <a
                href="contact.php"
                class="primary-button"
            >
                Contact Us
            </a>

        </div>


        <div class="contact-information">


            <div class="contact-item">

                <strong>
                    Email
                </strong>

                <span>
                    admin@example.com
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Phone
                </strong>

                <span>
                    +255 XXX XXX XXX
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Location
                </strong>

                <span>
                    Tanzania
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Support
                </strong>

                <span>
                    System Administration
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