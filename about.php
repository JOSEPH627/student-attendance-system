<?php

$pageTitle = 'About Us | Student Attendance System';

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

<section class="hero-section">

    <div class="container hero-container">


        <div class="hero-content">

            <span class="hero-label">
                About Us
            </span>


            <h1>

                Making Attendance Management

                <span>
                    Simpler and More Efficient
                </span>

            </h1>


            <p>
                The Student Attendance System helps schools
                organize student attendance and manage
                academic records more efficiently.
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
                    src="assets/images/school-classroom.jpg"
                    alt="Students in a classroom"
                >


                <div class="hero-image-label">

                    <div class="label-line">

                        <strong>
                            Simple
                        </strong>

                        <span>
                            Easy to understand
                        </span>

                    </div>


                    <div class="label-line">

                        <strong>
                            Organized
                        </strong>

                        <span>
                            Structured records
                        </span>

                    </div>


                    <div class="label-line">

                        <strong>
                            Efficient
                        </strong>

                        <span>
                            Less manual work
                        </span>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     OUR PURPOSE
========================================================= -->

<section class="information-section">

    <div class="container information-container">


        <div class="information-image">

            <img
                src="assets/images/attendance-records.jpg"
                alt="Attendance records"
            >

        </div>


        <div class="information-content">

            <span class="section-label">
                Our Purpose
            </span>


            <h2>
                A Better Way to Manage Attendance
            </h2>


            <p>
                Traditional attendance management can be
                time-consuming and difficult to organize.
                Our system provides a centralized platform
                that makes attendance management easier.
            </p>


            <p>
                Administrators can manage students, teachers,
                classes and subjects, while teachers can record
                and review attendance for their assigned classes.
            </p>


            <div class="information-list">


                <div class="information-item">

                    <span class="information-number">
                        01
                    </span>

                    <div>

                        <h3>
                            Simple
                        </h3>

                        <p>
                            Designed to be easy to understand,
                            navigate and use.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        02
                    </span>

                    <div>

                        <h3>
                            Organized
                        </h3>

                        <p>
                            Keep student and attendance
                            information structured in one place.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        03
                    </span>

                    <div>

                        <h3>
                            Efficient
                        </h3>

                        <p>
                            Reduce repetitive manual work and
                            make attendance recording faster.
                        </p>

                    </div>

                </div>


                <div class="information-item">

                    <span class="information-number">
                        04
                    </span>

                    <div>

                        <h3>
                            Secure
                        </h3>

                        <p>
                            Role-based access keeps administrator
                            and teacher functions properly separated.
                        </p>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================================================
     ABOUT DETAILS
========================================================= -->

<section class="about-section">

    <div class="container about-container">


        <div>

            <span class="section-label">
                Our Approach
            </span>


            <h2>
                Designed Around Real School Needs
            </h2>


            <p>
                The Student Attendance System is designed
                around the everyday tasks involved in managing
                attendance in schools.
            </p>


            <p>
                By bringing important records and attendance
                activities together, the system helps users
                find information quickly and work with greater
                consistency.
            </p>


            <a
                href="features.php"
                class="secondary-button"
            >
                View Features
            </a>

        </div>


        <div class="about-points">


            <div class="about-point">

                <span class="point-number">
                    01
                </span>

                <div>

                    <h3>
                        Centralized Records
                    </h3>

                    <p>
                        Store important student, class, subject
                        and attendance information in one system.
                    </p>

                </div>

            </div>


            <div class="about-point">

                <span class="point-number">
                    02
                </span>

                <div>

                    <h3>
                        Clear Responsibilities
                    </h3>

                    <p>
                        Give administrators and teachers access
                        according to their system roles.
                    </p>

                </div>

            </div>


            <div class="about-point">

                <span class="point-number">
                    03
                </span>

                <div>

                    <h3>
                        Reliable Attendance Data
                    </h3>

                    <p>
                        Maintain structured attendance records
                        that are easy to review when needed.
                    </p>

                </div>

            </div>


            <div class="about-point">

                <span class="point-number">
                    04
                </span>

                <div>

                    <h3>
                        Better Decision Making
                    </h3>

                    <p>
                        Use attendance reports and statistics to
                        understand attendance patterns.
                    </p>

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
                Start Managing Attendance Today
            </h2>


            <p>
                Access your account and continue to the
                Student Attendance System.
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
                    Easy
                </strong>

                <span>
                    Simple to use
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Organized
                </strong>

                <span>
                    Centralized records
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Efficient
                </strong>

                <span>
                    Less manual work
                </span>

            </div>


            <div class="contact-item">

                <strong>
                    Secure
                </strong>

                <span>
                    Role-based access
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