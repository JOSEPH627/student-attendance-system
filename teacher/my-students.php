<?php 
 
require_once '../includes/auth.php'; 
require_once '../includes/functions.php'; 
 
requireRole('TEACHER'); 
 
$pageTitle = 'My Students'; 
$pageType = 'teacher';

$additionalStyles = [
    '/student-attendance-system/assets/css/teacher-students.css'
];
 
$teacher = null; 
$students = []; 
$classes = []; 
 
$selectedClassId = isset($_GET['class_id']) 
    ? (int) $_GET['class_id'] 
    : 0; 
 
$search = trim($_GET['search'] ?? ''); 
 
$totalStudents = 0; 
$totalMale = 0; 
$totalFemale = 0; 
$totalActive = 0; 
 
$pageError = null; 
 
 
/* ========================================================= 
   LOAD TEACHER DATA 
========================================================= */ 
 
try { 
 
    /* ===================================================== 
       GET TEACHER 
    ===================================================== */ 
 
    $stmt = $pdo->prepare(" 
        SELECT 
            t.id, 
            t.user_id, 
            u.name, 
            u.email 
        FROM teachers t 
        INNER JOIN users u 
            ON u.id = t.user_id 
        WHERE t.user_id = ? 
        LIMIT 1 
    "); 
 
    $stmt->execute([ 
        $_SESSION['user_id'] 
    ]); 
 
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC); 
 
    if (!$teacher) { 
        throw new Exception('Teacher profile not found.'); 
    } 
 
 
    /* ===================================================== 
       GET ASSIGNED CLASSES 
    ===================================================== */ 
 
    $stmt = $pdo->prepare(" 
        SELECT DISTINCT 
            c.id, 
            c.name, 
            c.code, 
            c.academic_year 
        FROM teaching_assignments ta 
        INNER JOIN classes c 
            ON c.id = ta.class_id 
        WHERE ta.teacher_id = ? 
        ORDER BY c.name ASC 
    "); 
 
    $stmt->execute([ 
        $teacher['id'] 
    ]); 
 
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC); 
 
 
    /* ===================================================== 
       VALIDATE SELECTED CLASS 
    ===================================================== */ 
 
    if ($selectedClassId > 0) { 
 
        $classExists = false; 
 
        foreach ($classes as $class) { 
 
            if ((int) $class['id'] === $selectedClassId) { 
                $classExists = true; 
                break; 
            } 
        } 
 
        if (!$classExists) { 
            $selectedClassId = 0; 
        } 
    } 
 
 
    /* ===================================================== 
       GET STUDENTS 
    ===================================================== */ 
 
    $sql = " 
        SELECT DISTINCT 
            s.id, 
            s.student_reference, 
            s.admission_number, 
            s.first_name, 
            s.middle_name, 
            s.last_name, 
            s.gender, 
            s.status, 
            s.photo_path, 
            c.id AS class_id, 
            c.name AS class_name, 
            c.code AS class_code 
        FROM students s 
        INNER JOIN classes c 
            ON c.id = s.class_id 
        INNER JOIN teaching_assignments ta 
            ON ta.class_id = c.id 
        WHERE ta.teacher_id = ? 
    "; 
 
    $params = [ 
        $teacher['id'] 
    ]; 
 
 
    /* ===================================================== 
       CLASS FILTER 
    ===================================================== */ 
 
    if ($selectedClassId > 0) { 
 
        $sql .= " 
            AND c.id = ? 
        "; 
 
        $params[] = $selectedClassId; 
    } 
 
 
    /* ===================================================== 
       SEARCH 
    ===================================================== */ 
 
    if ($search !== '') { 
 
        $sql .= " 
            AND ( 
                s.first_name LIKE ? 
                OR s.middle_name LIKE ? 
                OR s.last_name LIKE ? 
                OR s.student_reference LIKE ? 
                OR s.admission_number LIKE ? 
                OR CONCAT( 
                    s.first_name, 
                    ' ', 
                    COALESCE(s.middle_name, ''), 
                    ' ', 
                    s.last_name 
                ) LIKE ? 
            ) 
        "; 
 
        $searchTerm = '%' . $search . '%'; 
 
        $params[] = $searchTerm; 
        $params[] = $searchTerm; 
        $params[] = $searchTerm; 
        $params[] = $searchTerm; 
        $params[] = $searchTerm; 
        $params[] = $searchTerm; 
    } 
 
 
    /* ===================================================== 
       ORDER 
    ===================================================== */ 
 
    $sql .= " 
        ORDER BY 
            c.name ASC, 
            s.first_name ASC, 
            s.last_name ASC 
    "; 
 
 
    $stmt = $pdo->prepare($sql); 
 
    $stmt->execute($params); 
 
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC); 
 
 
    /* ===================================================== 
       STATISTICS 
    ===================================================== */ 
 
    foreach ($students as $student) { 
 
        $totalStudents++; 
 
        if (strtoupper($student['gender'] ?? '') === 'MALE') { 
            $totalMale++; 
        } 
 
        if (strtoupper($student['gender'] ?? '') === 'FEMALE') { 
            $totalFemale++; 
        } 
 
        if (strtoupper($student['status'] ?? '') === 'ACTIVE') { 
            $totalActive++; 
        } 
    } 
 
 
} catch (Throwable $e) { 
 
    $pageError = $e->getMessage(); 
} 
 
 
/* ========================================================= 
   HELPER FUNCTIONS 
========================================================= */ 
 
function teacherStudentStatusClass(string $status): string 
{ 
    return match (strtoupper($status)) { 
 
        'ACTIVE' => 'teacher-status-active', 
 
        'INACTIVE' => 'teacher-status-inactive', 
 
        default => 'teacher-status-default' 
    }; 
} 
 
 
function teacherStudentStatusLabel(string $status): string 
{ 
    return match (strtoupper($status)) { 
 
        'ACTIVE' => 'Active', 
 
        'INACTIVE' => 'Inactive', 
 
        default => ucfirst(strtolower($status)) 
    }; 
} 
 
 
function teacherStudentGenderClass(string $gender): string 
{ 
    return match (strtoupper($gender)) { 
 
        'MALE' => 'teacher-gender-male', 
 
        'FEMALE' => 'teacher-gender-female', 
 
        default => 'teacher-gender-default' 
    }; 
} 
 
 
function teacherStudentFullName(array $student): string 
{ 
    $parts = array_filter([ 
        trim($student['first_name'] ?? ''), 
        trim($student['middle_name'] ?? ''), 
        trim($student['last_name'] ?? '') 
    ]); 
 
    return implode(' ', $parts); 
} 
 
?> 
 
<?php require_once '../includes/header.php'; ?> 
 
<?php require_once '../includes/navbar.php'; ?> 
 
 
<div class="dashboard-layout teacher-page"> 
 
 
    <?php require_once '../includes/sidebar.php'; ?> 
 
 
    <main class="dashboard-main teacher-main"> 
 
 
        <div class="dashboard-container teacher-students-page"> 
 
 
            <!-- ================================================= 
                 PAGE HEADER 
            ================================================== --> 
 
            <div class="teacher-students-header"> 
 
 
                <div class="teacher-students-heading"> 
 
                    <span class="teacher-page-eyebrow"> 
                        Teacher Portal 
                    </span> 
 
                    <h1> 
                        My Students 
                    </h1> 
 
                    <p> 
                        View students from your assigned classes. 
                    </p> 
 
                </div> 
 
 
                <div class="teacher-students-header-action"> 
 
                    <a 
                        href="/student-attendance-system/teacher/attendance.php" 
                        class="teacher-page-action" 
                    > 
 
                        <svg 
                            viewBox="0 0 24 24" 
                            fill="none" 
                            stroke="currentColor" 
                            stroke-width="2" 
                            stroke-linecap="round" 
                            stroke-linejoin="round" 
                        > 
 
                            <path d="M9 11l3 3L22 4"></path> 
 
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path> 
 
                        </svg> 
 
                        Take Attendance 
 
                    </a> 
 
                </div> 
 
 
            </div> 
 
 
            <!-- ================================================= 
                 ERROR 
            ================================================== --> 
 
            <?php if ($pageError): ?> 
 
                <div class="teacher-students-alert"> 
 
                    <div> 
 
                        <strong> 
                            Unable to load students 
                        </strong> 
 
                        <p> 
                            <?= htmlspecialchars($pageError) ?> 
                        </p> 
 
                    </div> 
 
                </div> 
 
            <?php endif; ?> 
 
 
            <!-- ================================================= 
                 SUMMARY 
            ================================================== --> 
 
            <section class="teacher-students-summary"> 
 
 
                <div class="teacher-summary-card"> 
 
                    <div class="teacher-summary-card-content"> 
 
                        <span> 
                            Total Students 
                        </span> 
 
                        <strong> 
                            <?= number_format($totalStudents) ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
 
                <div class="teacher-summary-card"> 
 
                    <div class="teacher-summary-card-content"> 
 
                        <span> 
                            Male Students 
                        </span> 
 
                        <strong> 
                            <?= number_format($totalMale) ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
 
                <div class="teacher-summary-card"> 
 
                    <div class="teacher-summary-card-content"> 
 
                        <span> 
                            Female Students 
                        </span> 
 
                        <strong> 
                            <?= number_format($totalFemale) ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
 
                <div class="teacher-summary-card"> 
 
                    <div class="teacher-summary-card-content"> 
 
                        <span> 
                            Active Students 
                        </span> 
 
                        <strong> 
                            <?= number_format($totalActive) ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
 
            </section> 
 
 
            <!-- ================================================= 
                 FILTER 
            ================================================== --> 
 
            <section class="dashboard-card teacher-students-filter-card"> 
 
 
                <div class="teacher-students-filter-header"> 
 
                    <div> 
 
                        <h2> 
                            Find Students 
                        </h2> 
 
                        <p> 
                            Filter students by class or search by name, reference or admission number. 
                        </p> 
 
                    </div> 
 
                </div> 
 
 
                <form 
                    method="GET" 
                    action="" 
                    class="teacher-students-filter-form" 
                > 
 
 
                    <!-- CLASS --> 
 
                    <div class="teacher-students-field"> 
 
                        <label for="class_id"> 
                            Class 
                        </label> 
 
                        <select 
                            id="class_id" 
                            name="class_id" 
                        > 
 
                            <option value="0"> 
                                All My Classes 
                            </option> 
 
 
                            <?php foreach ($classes as $class): ?> 
 
                                <option 
                                    value="<?= (int) $class['id'] ?>" 
                                    <?= $selectedClassId === (int) $class['id'] ? 'selected' : '' ?> 
                                > 
 
                                    <?= htmlspecialchars($class['name']) ?> 
 
                                    — 
 
                                    <?= htmlspecialchars($class['code']) ?> 
 
                                </option> 
 
                            <?php endforeach; ?> 
 
 
                        </select> 
 
                    </div> 
 
 
                    <!-- SEARCH --> 
 
                    <div class="teacher-students-field"> 
 
                        <label for="search"> 
                            Search 
                        </label> 
 
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            value="<?= htmlspecialchars($search) ?>" 
                            placeholder="Student name, reference or admission number..." 
                        > 
 
                    </div> 
 
 
                    <!-- ACTIONS --> 
 
                    <div class="teacher-students-filter-actions"> 
 
 
                        <button 
                            type="submit" 
                            class="teacher-primary-button" 
                        > 
                            Search 
                        </button> 
 
 
                        <?php if ($selectedClassId > 0 || $search !== ''): ?> 
 
                            <a 
                                href="/student-attendance-system/teacher/my-students.php" 
                                class="teacher-secondary-button" 
                            > 
                                Clear 
                            </a> 
 
                        <?php endif; ?> 
 
 
                    </div> 
 
 
                </form> 
 
 
            </section> 
 
 
            <!-- ================================================= 
                 STUDENTS LIST 
            ================================================== --> 
 
            <section class="dashboard-card teacher-students-section"> 
 
 
                <!-- SECTION HEADER --> 
 
                <div class="teacher-students-section-header"> 
 
 
                    <div> 
 
                        <h2> 
                            Students 
                        </h2> 
 
 
                        <p> 
 
                            <?php if ($selectedClassId > 0): ?> 
 
                                Students from the selected class. 
 
                            <?php elseif ($search !== ''): ?> 
 
                                Students matching your search. 
 
                            <?php else: ?> 
 
                                Students from all your assigned classes. 
 
                            <?php endif; ?> 
 
                        </p> 
 
                    </div> 
 
 
                    <div class="teacher-students-count"> 
 
                        <?= number_format($totalStudents) ?> 
 
                        <?= $totalStudents === 1 ? 'Student' : 'Students' ?> 
 
                    </div> 
 
 
                </div> 
 
 
                <!-- ================================================= 
                     TABLE 
                ================================================== --> 
 
                <?php if (!empty($students)): ?> 
 
 
                    <div class="teacher-students-table-wrapper"> 
 
 
                        <table class="teacher-students-table"> 
 
 
                            <thead> 
 
                                <tr> 
 
                                    <th> 
                                        Student 
                                    </th> 
 
                                    <th> 
                                        Reference 
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
 
                                    <th> 
                                        Action 
                                    </th> 
 
                                </tr> 
 
                            </thead> 
 
 
                            <tbody> 
 
 
                                <?php foreach ($students as $student): ?> 
 
 
                                    <?php 
 
                                    $studentName = 
                                        teacherStudentFullName($student); 
 
                                    if ($studentName === '') { 
                                        $studentName = 'Unknown Student'; 
                                    } 
 
                                    $initial = 
                                        strtoupper( 
                                            substr( 
                                                $student['first_name'] ?? 'S', 
                                                0, 
                                                1 
                                            ) 
                                        ); 
 
                                    $gender = 
                                        strtoupper( 
                                            $student['gender'] ?? '' 
                                        ); 
 
                                    $status = 
                                        strtoupper( 
                                            $student['status'] ?? '' 
                                        ); 
 
                                    ?> 
 
 
                                    <tr> 
 
 
                                        <!-- STUDENT --> 
 
                                        <td> 
 
                                            <div class="teacher-student-info"> 
 
 
                                                <div class="teacher-student-avatar"> 
 
 
                                                    <?php if (!empty($student['photo_path'])): ?> 
 
                                                        <img 
                                                            src="<?= htmlspecialchars($student['photo_path']) ?>" 
                                                            alt="<?= htmlspecialchars($studentName) ?>" 
                                                        > 
 
                                                    <?php else: ?> 
 
                                                        <?= htmlspecialchars($initial) ?> 
 
                                                    <?php endif; ?> 
 
 
                                                </div> 
 
 
                                                <div> 
 
                                                    <strong> 
                                                        <?= htmlspecialchars($studentName) ?> 
                                                    </strong> 
 
                                                    <span> 
                                                        Student ID #<?= (int) $student['id'] ?> 
                                                    </span> 
 
                                                </div> 
 
 
                                            </div> 
 
                                        </td> 
 
 
                                        <!-- REFERENCE --> 
 
                                        <td> 
 
                                            <span class="teacher-student-reference"> 
 
                                                <?= htmlspecialchars( 
                                                    $student['student_reference'] ?? '—' 
                                                ) ?> 
 
                                            </span> 
 
                                        </td> 
 
 
                                        <!-- ADMISSION --> 
 
                                        <td> 
 
                                            <span class="teacher-student-reference"> 
 
                                                <?= htmlspecialchars( 
                                                    $student['admission_number'] ?? '—' 
                                                ) ?> 
 
                                            </span> 
 
                                        </td> 
 
 
                                        <!-- CLASS --> 
 
                                        <td> 
 
                                            <div class="teacher-student-class"> 
 
                                                <strong> 
                                                    <?= htmlspecialchars( 
                                                        $student['class_name'] 
                                                    ) ?> 
                                                </strong> 
 
                                                <span> 
                                                    <?= htmlspecialchars( 
                                                        $student['class_code'] 
                                                    ) ?> 
                                                </span> 
 
                                            </div> 
 
                                        </td> 
 
 
                                        <!-- GENDER --> 
 
                                        <td> 
 
                                            <span 
                                                class="teacher-student-badge <?= teacherStudentGenderClass($gender) ?>" 
                                            > 
 
                                                <?= htmlspecialchars( 
                                                    ucfirst( 
                                                        strtolower( 
                                                            $gender ?: 'Unknown' 
                                                        ) 
                                                    ) 
                                                ) ?> 
 
                                            </span> 
 
                                        </td> 
 
 
                                        <!-- STATUS --> 
 
                                        <td> 
 
                                            <span 
                                                class="teacher-student-badge <?= teacherStudentStatusClass($status) ?>" 
                                            > 
 
                                                <?= htmlspecialchars( 
                                                    teacherStudentStatusLabel($status) 
                                                ) ?> 
 
                                            </span> 
 
                                        </td> 
 
 
                                        <!-- ACTION --> 
 
                                        <td> 
 
                                            <a 
                                                href="/student-attendance-system/teacher/attendance.php?class_id=<?= (int) $student['class_id'] ?>" 
                                                class="teacher-student-action" 
                                                title="Take attendance" 
                                            > 
 
                                                <svg 
                                                    viewBox="0 0 24 24" 
                                                    fill="none" 
                                                    stroke="currentColor" 
                                                    stroke-width="2" 
                                                    stroke-linecap="round" 
                                                    stroke-linejoin="round" 
                                                > 
 
                                                    <path d="M9 11l3 3L22 4"></path> 
 
                                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path> 
 
                                                </svg> 
 
                                                Attendance 
 
                                            </a> 
 
                                        </td> 
 
 
                                    </tr> 
 
 
                                <?php endforeach; ?> 
 
 
                            </tbody> 
 
 
                        </table> 
 
 
                    </div> 
 
 
                <?php else: ?> 
 
 
                    <!-- ================================================= 
                         EMPTY STATE 
                    ================================================== --> 
 
                    <div class="teacher-students-empty"> 
 
 
                        <div class="teacher-students-empty-symbol"> 
 
                            <svg 
                                viewBox="0 0 24 24" 
                                fill="none" 
                                stroke="currentColor" 
                                stroke-width="1.8" 
                                stroke-linecap="round" 
                                stroke-linejoin="round" 
                            > 
 
                                <circle 
                                    cx="9" 
                                    cy="7" 
                                    r="4" 
                                ></circle> 
 
                                <path 
                                    d="M3 21v-2a6 6 0 0 1 12 0v2" 
                                ></path> 
 
                                <path 
                                    d="M16 3.13a4 4 0 0 1 0 7.75" 
                                ></path> 
 
                                <path 
                                    d="M21 21v-2a6 6 0 0 0-3-5.19" 
                                ></path> 
 
                            </svg> 
 
                        </div> 
 
 
                        <h3> 
                            No Students Found 
                        </h3> 
 
 
                        <?php if ($selectedClassId > 0 || $search !== ''): ?> 
 
                            <p> 
                                No students match the selected filters. 
                            </p> 
 
 
                            <a 
                                href="/student-attendance-system/teacher/my-students.php" 
                                class="teacher-secondary-button" 
                            > 
                                View All Students 
                            </a> 
 
 
                        <?php else: ?> 
 
                            <p> 
                                You currently don't have any students assigned to your classes. 
                            </p> 
 
                        <?php endif; ?> 
 
 
                    </div> 
 
 
                <?php endif; ?> 
 
 
            </section> 
 
 
        </div> 
 
 
    </main> 
 
 
</div> 
 
 
<?php require_once '../includes/footer.php'; ?>