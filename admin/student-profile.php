<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Student Profile | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css'
];


$studentId = (int) ($_GET['id'] ?? 0);

if ($studentId <= 0) {

    redirect(
        '/student-attendance-system/admin/students.php'
    );
}


/*
|--------------------------------------------------------------------------
| Load Student
|--------------------------------------------------------------------------
*/

$studentStmt = $pdo->prepare(
    "
    SELECT
        s.id,
        s.student_reference,
        s.admission_number,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.gender,
        s.date_of_birth,
        s.photo_path,
        s.status,
        s.created_at,
        s.updated_at,

        c.id AS class_id,
        c.name AS class_name,
        c.code AS class_code,
        c.academic_year

    FROM students s

    INNER JOIN classes c
        ON c.id = s.class_id

    WHERE s.id = ?

    LIMIT 1
    "
);

$studentStmt->execute([
    $studentId
]);

$student = $studentStmt->fetch();


if (!$student) {

    http_response_code(404);

    die('Student not found.');
}


/*
|--------------------------------------------------------------------------
| Attendance Summary
|--------------------------------------------------------------------------
*/

$attendanceStmt = $pdo->prepare(
    "
    SELECT
        COUNT(*) AS total_records,

        SUM(
            CASE
                WHEN status = 'PRESENT'
                THEN 1
                ELSE 0
            END
        ) AS present_count,

        SUM(
            CASE
                WHEN status = 'ABSENT'
                THEN 1
                ELSE 0
            END
        ) AS absent_count,

        SUM(
            CASE
                WHEN status = 'PERMISSION'
                THEN 1
                ELSE 0
            END
        ) AS permission_count

    FROM attendance

    WHERE student_id = ?
    "
);

$attendanceStmt->execute([
    $studentId
]);

$attendance = $attendanceStmt->fetch();


$totalAttendance =
    (int) ($attendance['total_records'] ?? 0);

$presentCount =
    (int) ($attendance['present_count'] ?? 0);

$absentCount =
    (int) ($attendance['absent_count'] ?? 0);

$permissionCount =
    (int) ($attendance['permission_count'] ?? 0);


/*
|--------------------------------------------------------------------------
| Attendance Percentage
|--------------------------------------------------------------------------
*/

$attendancePercentage = 0;

if ($totalAttendance > 0) {

    $attendancePercentage =
        round(
            (
                $presentCount /
                $totalAttendance
            ) * 100,
            1
        );
}


/*
|--------------------------------------------------------------------------
| Recent Attendance
|--------------------------------------------------------------------------
*/

$recentAttendanceStmt = $pdo->prepare(
    "
    SELECT
        a.attendance_date,
        a.status,
        a.permission_reason,

        sub.name AS subject_name,
        sub.code AS subject_code,

        u.name AS teacher_name

    FROM attendance a

    INNER JOIN subjects sub
        ON sub.id = a.subject_id

    INNER JOIN teachers t
        ON t.id = a.teacher_id

    INNER JOIN users u
        ON u.id = t.user_id

    WHERE a.student_id = ?

    ORDER BY
        a.attendance_date DESC,
        a.id DESC

    LIMIT 10
    "
);

$recentAttendanceStmt->execute([
    $studentId
]);

$recentAttendance =
    $recentAttendanceStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Helper For Full Student Name
|--------------------------------------------------------------------------
*/

$studentFullName =
    trim(
        $student['first_name'] .
        ' ' .
        ($student['middle_name'] ?? '') .
        ' ' .
        $student['last_name']
    );


/*
|--------------------------------------------------------------------------
| Photo
|--------------------------------------------------------------------------
*/

$photoUrl = null;

if (!empty($student['photo_path'])) {

    $photoUrl =
        '/student-attendance-system/' .
        $student['photo_path'];
}


require_once __DIR__ . '/../includes/header.php';

?>


<div class="dashboard-layout">


    <?php
    require_once __DIR__ . '/../includes/sidebar.php';
    ?>


    <main class="dashboard-main">


        <?php
        require_once __DIR__ . '/../includes/navbar.php';
        ?>


        <div class="dashboard-content">


            <!-- =================================================
                 PAGE HEADER
                 ================================================= -->

            <div class="page-header">

                <div>

                    <span class="page-header-label">
                        STUDENT MANAGEMENT
                    </span>

                    <h1>
                        Student Profile
                    </h1>

                    <p>
                        View student information and attendance summary.
                    </p>

                </div>


                <div class="page-header-actions">

                    <a
                        href="edit-student.php?id=<?= $studentId ?>"
                        class="dashboard-primary-button"
                    >
                        Edit Student
                    </a>

                    <a
                        href="students.php"
                        class="dashboard-secondary-button"
                    >
                        Back to Students
                    </a>

                </div>

            </div>


            <!-- =================================================
                 PROFILE HEADER
                 ================================================= -->

            <div class="table-panel student-profile-header">


                <div class="student-profile-main">


                    <?php if ($photoUrl !== null): ?>

                        <img
                            src="<?= e($photoUrl) ?>"
                            alt="<?= e($studentFullName) ?>"
                            class="student-profile-photo"
                        >

                    <?php else: ?>

                        <div class="student-profile-avatar">

                            <?= e(
                                strtoupper(
                                    substr(
                                        $student['first_name'],
                                        0,
                                        1
                                    ) .
                                    substr(
                                        $student['last_name'],
                                        0,
                                        1
                                    )
                                )
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <div class="student-profile-heading">


                        <span class="page-header-label">
                            STUDENT
                        </span>


                        <h2>
                            <?= e($studentFullName) ?>
                        </h2>


                        <p>
                            <?= e(
                                $student['student_reference']
                            ) ?>

                            ·

                            <?= e(
                                $student['admission_number']
                            ) ?>
                        </p>


                    </div>


                </div>


                <div class="student-profile-status">


                    <?php if (
                        $student['status'] === 'ACTIVE'
                    ): ?>

                        <span
                            class="status-badge status-active"
                        >
                            Active
                        </span>

                    <?php else: ?>

                        <span
                            class="status-badge status-inactive"
                        >
                            Inactive
                        </span>

                    <?php endif; ?>


                </div>


            </div>


            <!-- =================================================
                 ATTENDANCE STATISTICS
                 ================================================= -->

            <div class="dashboard-stats-grid">


                <div class="stat-card">

                    <div class="stat-card-icon">

                        <span>
                            T
                        </span>

                    </div>


                    <div class="stat-card-content">

                        <span class="stat-card-label">
                            Attendance Records
                        </span>

                        <strong>
                            <?= $totalAttendance ?>
                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-card-icon">

                        <span>
                            P
                        </span>

                    </div>


                    <div class="stat-card-content">

                        <span class="stat-card-label">
                            Present
                        </span>

                        <strong>
                            <?= $presentCount ?>
                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-card-icon">

                        <span>
                            A
                        </span>

                    </div>


                    <div class="stat-card-content">

                        <span class="stat-card-label">
                            Absent
                        </span>

                        <strong>
                            <?= $absentCount ?>
                        </strong>

                    </div>

                </div>


                <div class="stat-card">

                    <div class="stat-card-icon">

                        <span>
                            %
                        </span>

                    </div>


                    <div class="stat-card-content">

                        <span class="stat-card-label">
                            Attendance Rate
                        </span>

                        <strong>
                            <?= e(
                                (string)
                                $attendancePercentage
                            ) ?>%
                        </strong>

                    </div>

                </div>


            </div>


            <!-- =================================================
                 STUDENT INFORMATION
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">

                    <div>

                        <span class="page-header-label">
                            PERSONAL INFORMATION
                        </span>

                        <h2>
                            Student Details
                        </h2>

                    </div>

                </div>


                <div class="profile-details-grid">


                    <div class="profile-detail-item">

                        <span>
                            Student Reference
                        </span>

                        <strong>
                            <?= e(
                                $student['student_reference']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Admission Number
                        </span>

                        <strong>
                            <?= e(
                                $student['admission_number']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            First Name
                        </span>

                        <strong>
                            <?= e(
                                $student['first_name']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Middle Name
                        </span>

                        <strong>
                            <?= $student['middle_name']
                                ? e(
                                    $student['middle_name']
                                )
                                : '—'
                            ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Last Name
                        </span>

                        <strong>
                            <?= e(
                                $student['last_name']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Gender
                        </span>

                        <strong>
                            <?= $student['gender'] === 'MALE'
                                ? 'Male'
                                : 'Female'
                            ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Date of Birth
                        </span>

                        <strong>

                            <?= !empty(
                                $student['date_of_birth']
                            )
                                ? e(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $student[
                                                'date_of_birth'
                                            ]
                                        )
                                    )
                                )
                                : '—'
                            ?>

                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Status
                        </span>

                        <strong>

                            <?= $student['status'] === 'ACTIVE'
                                ? 'Active'
                                : 'Inactive'
                            ?>

                        </strong>

                    </div>


                </div>


            </div>


            <!-- =================================================
                 CLASS INFORMATION
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">

                    <div>

                        <span class="page-header-label">
                            CLASS INFORMATION
                        </span>

                        <h2>
                            Current Class
                        </h2>

                    </div>

                </div>


                <div class="profile-details-grid">


                    <div class="profile-detail-item">

                        <span>
                            Class Name
                        </span>

                        <strong>
                            <?= e(
                                $student['class_name']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Class Code
                        </span>

                        <strong>
                            <?= e(
                                $student['class_code']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Academic Year
                        </span>

                        <strong>
                            <?= e(
                                $student['academic_year']
                            ) ?>
                        </strong>

                    </div>


                    <div class="profile-detail-item">

                        <span>
                            Student Since
                        </span>

                        <strong>
                            <?= e(
                                date(
                                    'd M Y',
                                    strtotime(
                                        $student[
                                            'created_at'
                                        ]
                                    )
                                )
                            ) ?>
                        </strong>

                    </div>


                </div>


            </div>


            <!-- =================================================
                 RECENT ATTENDANCE
                 ================================================= -->

            <div class="table-panel">


                <div class="table-panel-header">

                    <div>

                        <span class="page-header-label">
                            ATTENDANCE
                        </span>

                        <h2>
                            Recent Attendance
                        </h2>

                    </div>


                    <span class="record-count">

                        <?= count(
                            $recentAttendance
                        ) ?>

                        record<?= count(
                            $recentAttendance
                        ) === 1
                            ? ''
                            : 's'
                        ?>

                    </span>

                </div>


                <?php if (
                    empty($recentAttendance)
                ): ?>


                    <div class="table-empty-state">

                        <div class="empty-state-icon">
                            AT
                        </div>


                        <h3>
                            No attendance records
                        </h3>


                        <p>
                            Attendance has not been recorded
                            for this student yet.
                        </p>


                    </div>


                <?php else: ?>


                    <div class="table-wrapper">


                        <table class="data-table">


                            <thead>

                                <tr>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Teacher
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Reason
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $recentAttendance
                                    as $record
                                ): ?>


                                    <tr>


                                        <td>

                                            <?= e(
                                                date(
                                                    'd M Y',
                                                    strtotime(
                                                        $record[
                                                            'attendance_date'
                                                        ]
                                                    )
                                                )
                                            ) ?>

                                        </td>


                                        <td>

                                            <div class="table-primary-text">

                                                <?= e(
                                                    $record[
                                                        'subject_name'
                                                    ]
                                                ) ?>

                                            </div>


                                            <span
                                                class="table-secondary-text"
                                            >

                                                <?= e(
                                                    $record[
                                                        'subject_code'
                                                    ]
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= e(
                                                $record[
                                                    'teacher_name'
                                                ]
                                            ) ?>

                                        </td>


                                        <td>


                                            <?php if (
                                                $record['status'] ===
                                                'PRESENT'
                                            ): ?>

                                                <span
                                                    class="status-badge status-active"
                                                >
                                                    Present
                                                </span>


                                            <?php elseif (
                                                $record['status'] ===
                                                'ABSENT'
                                            ): ?>

                                                <span
                                                    class="status-badge status-inactive"
                                                >
                                                    Absent
                                                </span>


                                            <?php else: ?>

                                                <span
                                                    class="status-badge status-permission"
                                                >
                                                    Permission
                                                </span>

                                            <?php endif; ?>


                                        </td>


                                        <td>


                                            <?php if (
                                                $record['status'] ===
                                                'PERMISSION' &&
                                                !empty(
                                                    $record[
                                                        'permission_reason'
                                                    ]
                                                )
                                            ): ?>

                                                <details>

                                                    <summary>
                                                        View Reason
                                                    </summary>

                                                    <p class="permission-reason">
                                                        <?= e(
                                                            $record[
                                                                'permission_reason'
                                                            ]
                                                        ) ?>
                                                    </p>

                                                </details>


                                            <?php else: ?>

                                                —

                                            <?php endif; ?>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php endif; ?>


            </div>


        </div>


    </main>


</div>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>