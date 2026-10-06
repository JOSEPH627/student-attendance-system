<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('TEACHER');

$pageTitle = 'My Classes';
$pageType = 'teacher';

$additionalStyles = [
    '/student-attendance-system/assets/css/teacher-classes.css'
];


/*
|--------------------------------------------------------------------------
| Get Current Teacher
|--------------------------------------------------------------------------
*/

$userId = currentUserId();

$stmt = $pdo->prepare("
    SELECT
        t.id,
        t.employee_number,
        t.department,
        u.name,
        u.email
    FROM teachers t
    INNER JOIN users u
        ON u.id = t.user_id
    WHERE t.user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$teacher = $stmt->fetch();

if (!$teacher) {
    http_response_code(403);
    die('Teacher profile not found.');
}

$teacherId = (int) $teacher['id'];


/*
|--------------------------------------------------------------------------
| Get Teacher Classes
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        c.id,
        c.name,
        c.code,
        c.academic_year,
        COUNT(DISTINCT s.id) AS student_count
    FROM teaching_assignments ta

    INNER JOIN classes c
        ON c.id = ta.class_id

    LEFT JOIN students s
        ON s.class_id = c.id
        AND s.status = 'ACTIVE'

    WHERE ta.teacher_id = ?

    GROUP BY
        c.id,
        c.name,
        c.code,
        c.academic_year

    ORDER BY c.name ASC
");

$stmt->execute([$teacherId]);

$classes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Subjects For Each Class
|--------------------------------------------------------------------------
*/

$classSubjects = [];

$stmt = $pdo->prepare("
    SELECT
        ta.class_id,
        s.id AS subject_id,
        s.name AS subject_name,
        s.code AS subject_code
    FROM teaching_assignments ta

    INNER JOIN subjects s
        ON s.id = ta.subject_id

    WHERE ta.teacher_id = ?

    ORDER BY s.name ASC
");

$stmt->execute([$teacherId]);

$subjects = $stmt->fetchAll();

foreach ($subjects as $subject) {

    $classId = (int) $subject['class_id'];

    if (!isset($classSubjects[$classId])) {
        $classSubjects[$classId] = [];
    }

    $classSubjects[$classId][] = [
        'id' => (int) $subject['subject_id'],
        'name' => $subject['subject_name'],
        'code' => $subject['subject_code']
    ];
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalClasses = count($classes);

$totalSubjects = count($subjects);

$totalStudents = 0;

foreach ($classes as $class) {
    $totalStudents += (int) $class['student_count'];
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<main class="dashboard-main teacher-classes-page">

    <div class="dashboard-container">

        <!-- =====================================================
             PAGE HEADER
             ===================================================== -->

        <div class="dashboard-page-header teacher-classes-page-header">

            <div class="teacher-classes-header-content">

                <span class="teacher-classes-eyebrow">
                    TEACHER PORTAL
                </span>

                <h1>
                    My Classes
                </h1>

                <p>
                    View the classes, subjects and students assigned to you.
                </p>

            </div>

            <div class="dashboard-page-actions teacher-classes-header-actions">

                <a
                    href="/student-attendance-system/teacher/attendance.php"
                    class="btn btn-primary"
                >
                    Take Attendance
                </a>

            </div>

        </div>


        <!-- =====================================================
             SUMMARY
             ===================================================== -->

        <section class="teacher-classes-summary">

            <div class="teacher-summary-card">

                <div class="teacher-summary-content">

                    <span>
                        My Classes
                    </span>

                    <strong>
                        <?= $totalClasses ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-summary-card">

                <div class="teacher-summary-content">

                    <span>
                        My Subjects
                    </span>

                    <strong>
                        <?= $totalSubjects ?>
                    </strong>

                </div>

            </div>


            <div class="teacher-summary-card">

                <div class="teacher-summary-content">

                    <span>
                        My Students
                    </span>

                    <strong>
                        <?= $totalStudents ?>
                    </strong>

                </div>

            </div>

        </section>


        <!-- =====================================================
             ASSIGNED CLASSES
             ===================================================== -->

        <section class="dashboard-card teacher-classes-section">

            <div class="dashboard-card-header teacher-classes-section-header">

                <div>

                    <h2>
                        Assigned Classes
                    </h2>

                    <p>
                        These are the classes currently assigned to you.
                    </p>

                </div>

            </div>


            <?php if (empty($classes)): ?>

                <div class="teacher-empty-state">

                    <div class="teacher-empty-icon">
                        CL
                    </div>

                    <h3>
                        No Classes Assigned
                    </h3>

                    <p>
                        You currently have no teaching classes assigned.
                    </p>

                </div>

            <?php else: ?>

                <div class="teacher-classes-grid">

                    <?php foreach ($classes as $class): ?>

                        <?php

                        $classId = (int) $class['id'];

                        $classSubjectsList =
                            $classSubjects[$classId] ?? [];

                        ?>

                        <article class="teacher-class-panel">

                            <!-- =================================================
                                 CLASS HEADER
                                 ================================================= -->

                            <div class="teacher-class-panel-header">

                                <div class="teacher-class-title">

                                    <div class="teacher-class-icon">
                                        <?= strtoupper(
                                            substr(
                                                $class['name'],
                                                0,
                                                1
                                            )
                                        ) ?>
                                    </div>

                                    <div class="teacher-class-heading">

                                        <h3>
                                            <?= e($class['name']) ?>
                                        </h3>

                                        <span class="teacher-class-code">
                                            <?= e($class['code']) ?>
                                        </span>

                                    </div>

                                </div>

                                <span class="teacher-class-year">
                                    <?= e($class['academic_year']) ?>
                                </span>

                            </div>


                            <!-- =================================================
                                 CLASS STATS
                                 ================================================= -->

                            <div class="teacher-class-stats">

                                <div class="teacher-class-stat">

                                    <span>
                                        Students
                                    </span>

                                    <strong>
                                        <?= (int) $class['student_count'] ?>
                                    </strong>

                                </div>


                                <div class="teacher-class-stat">

                                    <span>
                                        Subjects
                                    </span>

                                    <strong>
                                        <?= count($classSubjectsList) ?>
                                    </strong>

                                </div>

                            </div>


                            <!-- =================================================
                                 SUBJECTS
                                 ================================================= -->

                            <div class="teacher-class-subject-area">

                                <h4>
                                    Assigned Subjects
                                </h4>


                                <?php if (empty($classSubjectsList)): ?>

                                    <p class="no-subjects">
                                        No subjects assigned.
                                    </p>

                                <?php else: ?>

                                    <div class="teacher-subject-list">

                                        <?php foreach (
                                            $classSubjectsList
                                            as $subject
                                        ): ?>

                                            <div class="teacher-subject-item">

                                                <div class="teacher-subject-info">

                                                    <strong>
                                                        <?= e(
                                                            $subject['name']
                                                        ) ?>
                                                    </strong>

                                                    <span>
                                                        <?= e(
                                                            $subject['code']
                                                        ) ?>
                                                    </span>

                                                </div>

                                                <span class="subject-check">
                                                    ✓
                                                </span>

                                            </div>

                                        <?php endforeach; ?>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- =================================================
                                 ACTIONS
                                 ================================================= -->

                            <div class="teacher-class-panel-actions">

                                <a
                                    href="/student-attendance-system/teacher/my-students.php?class_id=<?= $classId ?>"
                                    class="teacher-secondary-action"
                                >
                                    View Students
                                </a>

                                <a
                                    href="/student-attendance-system/teacher/attendance.php?class_id=<?= $classId ?>"
                                    class="teacher-primary-action"
                                >
                                    Take Attendance
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

<?php

require_once __DIR__ . '/../includes/footer.php';

?>