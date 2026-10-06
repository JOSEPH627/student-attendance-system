<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';


/*
|--------------------------------------------------------------------------
| Admin Authentication
|--------------------------------------------------------------------------
*/

requireRole('ADMIN');


/*
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
*/

$pageTitle = 'Students | Student Attendance System';

$additionalStyles = [
    '/student-attendance-system/assets/css/dashboard.css',
    '/student-attendance-system/assets/css/tables.css'
];


/*
|--------------------------------------------------------------------------
| Search and Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');

$classId = isset($_GET['class_id'])
    ? (int) $_GET['class_id']
    : 0;

$status = $_GET['status'] ?? '';


/*
|--------------------------------------------------------------------------
| Get Classes
|--------------------------------------------------------------------------
*/

$classStmt = $pdo->query(
    "
    SELECT
        id,
        name,
        code
    FROM classes
    ORDER BY name ASC
    "
);

$classes = $classStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Build Student Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        students.id,
        students.student_reference,
        students.admission_number,
        students.first_name,
        students.middle_name,
        students.last_name,
        students.gender,
        students.date_of_birth,
        students.photo_path,
        students.status,
        classes.name AS class_name,
        classes.code AS class_code
    FROM students
    INNER JOIN classes
        ON classes.id = students.class_id
    WHERE 1 = 1
";

$params = [];


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            students.student_reference LIKE ?
            OR students.admission_number LIKE ?
            OR students.first_name LIKE ?
            OR students.middle_name LIKE ?
            OR students.last_name LIKE ?
        )
    ";

    $searchValue = '%' . $search . '%';

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| Class Filter
|--------------------------------------------------------------------------
*/

if ($classId > 0) {

    $sql .= "
        AND students.class_id = ?
    ";

    $params[] = $classId;
}


/*
|--------------------------------------------------------------------------
| Status Filter
|--------------------------------------------------------------------------
*/

if (
    $status === 'ACTIVE' ||
    $status === 'INACTIVE'
) {

    $sql .= "
        AND students.status = ?
    ";

    $params[] = $status;
}


/*
|--------------------------------------------------------------------------
| Ordering
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY
        students.first_name ASC,
        students.last_name ASC
";


/*
|--------------------------------------------------------------------------
| Execute Student Query
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$students = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Load Header
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

?>


<div class="dashboard-layout">


    <!-- =========================================================
         SIDEBAR
         ========================================================= -->

    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>


    <!-- =========================================================
         MAIN CONTENT
         ========================================================= -->

    <main class="dashboard-main">


        <!-- =====================================================
             TOP NAVIGATION
             ===================================================== -->

        <?php require_once __DIR__ . '/../includes/navbar.php'; ?>


        <!-- =====================================================
             PAGE CONTENT
             ===================================================== -->

        <div class="dashboard-content students-page">


            <!-- =================================================
                 PAGE HEADER
                 ================================================= -->

            <section class="dashboard-page-header">

                <div>

                    <span class="dashboard-eyebrow">
                        STUDENT MANAGEMENT
                    </span>

                    <h1>
                        Students
                    </h1>

                    <p>
                        Manage student records, classes and
                        student information.
                    </p>

                </div>


                <div class="page-header-actions">

                    <a
                        href="/student-attendance-system/admin/add-student.php"
                        class="dashboard-primary-button"
                    >
                        Add Student
                    </a>

                </div>

            </section>


            <!-- =================================================
                 SEARCH AND FILTERS
                 ================================================= -->

            <section class="dashboard-panel students-filter-panel">

                <div class="dashboard-panel-header">

                    <div>

                        <span class="dashboard-eyebrow">
                            SEARCH & FILTER
                        </span>

                        <h2>
                            Find Students
                        </h2>

                    </div>

                </div>


                <form
                    method="GET"
                    action="students.php"
                    class="student-filter-form"
                >


                    <!-- Search -->

                    <div class="form-group student-filter-search">

                        <label
                            for="search"
                            class="form-label"
                        >
                            Search
                        </label>

                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="form-input"
                            placeholder="Name, admission number or reference"
                            value="<?= e($search) ?>"
                        >

                    </div>


                    <!-- Class -->

                    <div class="form-group">

                        <label
                            for="class_id"
                            class="form-label"
                        >
                            Class
                        </label>

                        <select
                            id="class_id"
                            name="class_id"
                            class="form-input"
                        >

                            <option value="">
                                All Classes
                            </option>

                            <?php foreach ($classes as $class): ?>

                                <option
                                    value="<?= (int) $class['id'] ?>"
                                    <?= $classId === (int) $class['id']
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= e($class['name']) ?>
                                    <?php if (!empty($class['code'])): ?>
                                        (<?= e($class['code']) ?>)
                                    <?php endif; ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- Status -->

                    <div class="form-group">

                        <label
                            for="status"
                            class="form-label"
                        >
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-input"
                        >

                            <option value="">
                                All Status
                            </option>

                            <option
                                value="ACTIVE"
                                <?= $status === 'ACTIVE'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Active
                            </option>

                            <option
                                value="INACTIVE"
                                <?= $status === 'INACTIVE'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Inactive
                            </option>

                        </select>

                    </div>


                    <!-- Buttons -->

                    <div class="filter-actions">

                        <button
                            type="submit"
                            class="dashboard-primary-button"
                        >
                            Search
                        </button>

                        <a
                            href="students.php"
                            class="dashboard-secondary-button"
                        >
                            Clear
                        </a>

                    </div>


                </form>

            </section>


            <!-- =================================================
                 STUDENT TABLE
                 ================================================= -->

            <section class="dashboard-panel students-table-panel">


                <div class="dashboard-panel-header">

                    <div>

                        <span class="dashboard-eyebrow">
                            STUDENT RECORDS
                        </span>

                        <h2>
                            Student List
                        </h2>

                    </div>


                    <span class="record-count">

                        <?= count($students) ?>

                        student(s)

                    </span>

                </div>


                <?php if (count($students) > 0): ?>


                    <div class="table-wrapper">

                        <table class="data-table students-data-table">

                            <thead>

                                <tr>

                                    <th>
                                        Student
                                    </th>

                                    <th>
                                        Admission No.
                                    </th>

                                    <th>
                                        Class
                                    </th>

                                    <th>
                                        Gender
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th class="actions-column">
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($students as $student): ?>


                                    <?php

                                    $fullName =
                                        $student['first_name'];

                                    if (
                                        !empty(
                                            $student['middle_name']
                                        )
                                    ) {

                                        $fullName .=
                                            ' ' .
                                            $student['middle_name'];
                                    }

                                    $fullName .=
                                        ' ' .
                                        $student['last_name'];

                                    ?>


                                    <tr>


                                        <!-- Student -->

                                        <td>

                                            <div class="student-table-profile">


                                                <?php if (
                                                    !empty(
                                                        $student['photo_path']
                                                    )
                                                ): ?>

                                                    <div
                                                        class="student-table-photo-wrapper"
                                                    >

                                                        <img
                                                            src="/student-attendance-system/<?= e(
                                                                $student['photo_path']
                                                            ) ?>"
                                                            alt="<?= e(
                                                                $fullName
                                                            ) ?>"
                                                            class="student-table-photo"
                                                        >

                                                    </div>

                                                <?php else: ?>

                                                    <div
                                                        class="student-table-avatar"
                                                    >

                                                        <?= e(
                                                            strtoupper(
                                                                substr(
                                                                    $student['first_name'],
                                                                    0,
                                                                    1
                                                                )
                                                            )
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                                <div class="student-table-details">

                                                    <strong>
                                                        <?= e(
                                                            $fullName
                                                        ) ?>
                                                    </strong>

                                                    <small>
                                                        <?= e(
                                                            $student[
                                                                'student_reference'
                                                            ]
                                                        ) ?>
                                                    </small>

                                                </div>


                                            </div>

                                        </td>


                                        <!-- Admission -->

                                        <td>

                                            <span class="table-primary-text">
                                                <?= e(
                                                    $student[
                                                        'admission_number'
                                                    ]
                                                ) ?>
                                            </span>

                                        </td>


                                        <!-- Class -->

                                        <td>

                                            <div class="table-class-info">

                                                <strong>
                                                    <?= e(
                                                        $student[
                                                            'class_name'
                                                        ]
                                                    ) ?>
                                                </strong>

                                                <small>
                                                    <?= e(
                                                        $student[
                                                            'class_code'
                                                        ]
                                                    ) ?>
                                                </small>

                                            </div>

                                        </td>


                                        <!-- Gender -->

                                        <td>

                                            <span class="gender-text">
                                                <?= e(
                                                    ucfirst(
                                                        strtolower(
                                                            $student[
                                                                'gender'
                                                            ]
                                                        )
                                                    )
                                                ) ?>
                                            </span>

                                        </td>


                                        <!-- Status -->

                                        <td>

                                            <?php if (
                                                $student['status']
                                                === 'ACTIVE'
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

                                        </td>


                                        <!-- Actions -->

                                        <td class="actions-column">

                                            <div class="table-actions">

                                                <a
                                                    href="/student-attendance-system/admin/student-profile.php?id=<?= (int) $student['id'] ?>"
                                                    class="table-action-link"
                                                    title="View student"
                                                >
                                                    View
                                                </a>

                                                <a
                                                    href="/student-attendance-system/admin/edit-student.php?id=<?= (int) $student['id'] ?>"
                                                    class="table-action-link"
                                                    title="Edit student"
                                                >
                                                    Edit
                                                </a>

                                            </div>

                                        </td>


                                    </tr>


                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                <?php else: ?>


                    <!-- =================================================
                         EMPTY STATE
                         ================================================= -->

                    <div class="dashboard-empty-state">

                        <div class="dashboard-empty-icon">
                            ST
                        </div>

                        <h3>
                            No students found
                        </h3>

                        <p>

                            <?php if (
                                $search !== '' ||
                                $classId > 0 ||
                                $status !== ''
                            ): ?>

                                No student records match
                                your current search or filters.

                            <?php else: ?>

                                There are no student records
                                in the system yet.

                            <?php endif; ?>

                        </p>


                        <?php if (
                            $search !== '' ||
                            $classId > 0 ||
                            $status !== ''
                        ): ?>

                            <a
                                href="students.php"
                                class="dashboard-secondary-button"
                            >
                                Clear Filters
                            </a>

                        <?php else: ?>

                            <a
                                href="/student-attendance-system/admin/add-student.php"
                                class="dashboard-primary-button"
                            >
                                Add First Student
                            </a>

                        <?php endif; ?>


                    </div>


                <?php endif; ?>


            </section>


        </div>


    </main>


</div>


<?php

/*
|--------------------------------------------------------------------------
| Footer
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/footer.php';

?>
