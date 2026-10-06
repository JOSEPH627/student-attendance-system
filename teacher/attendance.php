<?php 

require_once __DIR__ . '/../includes/auth.php'; 
require_once __DIR__ . '/../includes/functions.php'; 
 
requireRole('TEACHER'); 
 
$teacherId = null; 
$teacherName = currentUserName(); 
 
$error = ''; 
$success = ''; 
 
/* 
|--------------------------------------------------------------------------
| Get Teacher Profile 
|--------------------------------------------------------------------------
*/ 
 
$stmt = $pdo->prepare(" 
    SELECT 
        t.id, 
        u.name 
    FROM teachers t 
    INNER JOIN users u ON u.id = t.user_id 
    WHERE t.user_id = ? 
    LIMIT 1 
"); 
 
$stmt->execute([currentUserId()]); 
$teacher = $stmt->fetch(); 
 
if (!$teacher) { 
    die('Teacher profile not found.'); 
} 
 
$teacherId = (int) $teacher['id']; 
$teacherName = $teacher['name']; 
 
 
/* 
|--------------------------------------------------------------------------
| Selected Filters 
|--------------------------------------------------------------------------
*/ 
 
$selectedClassId = isset($_GET['class_id']) 
    ? (int) $_GET['class_id'] 
    : (isset($_POST['class_id']) ? (int) $_POST['class_id'] : 0); 
 
$selectedSubjectId = isset($_GET['subject_id']) 
    ? (int) $_GET['subject_id'] 
    : (isset($_POST['subject_id']) ? (int) $_POST['subject_id'] : 0); 
 
$selectedDate = $_GET['attendance_date'] 
    ?? $_POST['attendance_date'] 
    ?? date('Y-m-d'); 
 
 
/* 
|--------------------------------------------------------------------------
| Validate Date 
|--------------------------------------------------------------------------
*/ 
 
$dateObject = DateTime::createFromFormat('Y-m-d', $selectedDate); 
 
if (!$dateObject || $dateObject->format('Y-m-d') !== $selectedDate) { 
    $selectedDate = date('Y-m-d'); 
} 
 
 
/* 
|--------------------------------------------------------------------------
| Save Attendance 
|--------------------------------------------------------------------------
*/ 
 
if (isPost() && isset($_POST['save_attendance'])) { 
 
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) { 
        $error = 'Invalid security token. Please refresh the page and try again.'; 
    } else { 
 
        $selectedClassId = (int) ($_POST['class_id'] ?? 0); 
        $selectedSubjectId = (int) ($_POST['subject_id'] ?? 0); 
        $selectedDate = $_POST['attendance_date'] ?? date('Y-m-d'); 
 
        /* 
        |--------------------------------------------------------------------------
        | Validate Teacher Assignment 
        |--------------------------------------------------------------------------
        */ 
 
        $assignmentStmt = $pdo->prepare(" 
            SELECT id 
            FROM teaching_assignments 
            WHERE teacher_id = ? 
              AND class_id = ? 
              AND subject_id = ? 
            LIMIT 1 
        "); 
 
        $assignmentStmt->execute([ 
            $teacherId, 
            $selectedClassId, 
            $selectedSubjectId 
        ]); 
 
        $assignment = $assignmentStmt->fetch(); 
 
        if (!$assignment) { 
            $error = 'You are not assigned to this class and subject.'; 
        } else { 
 
            $statuses = $_POST['status'] ?? []; 
            $permissionReasons = $_POST['permission_reason'] ?? []; 
 
            try { 
 
                $pdo->beginTransaction(); 
 
                /* 
                |--------------------------------------------------------------------------
                | Get Students Assigned To Class 
                |--------------------------------------------------------------------------
                */ 
 
                $studentStmt = $pdo->prepare(" 
                    SELECT id 
                    FROM students 
                    WHERE class_id = ? 
                      AND status = 'ACTIVE' 
                    ORDER BY id 
                "); 
 
                $studentStmt->execute([$selectedClassId]); 
 
                $students = $studentStmt->fetchAll(); 
 
                /* 
                |--------------------------------------------------------------------------
                | Insert / Update Attendance 
                |--------------------------------------------------------------------------
                */ 
 
                $attendanceStmt = $pdo->prepare(" 
                    INSERT INTO attendance ( 
                        student_id, 
                        class_id, 
                        subject_id, 
                        teacher_id, 
                        attendance_date, 
                        status, 
                        permission_reason 
                    ) 
                    VALUES (?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        status = VALUES(status), 
                        permission_reason = VALUES(permission_reason), 
                        teacher_id = VALUES(teacher_id), 
                        updated_at = CURRENT_TIMESTAMP 
                "); 
 
                foreach ($students as $student) { 
 
                    $studentId = (int) $student['id']; 
 
                    $status = strtoupper( 
                        trim($statuses[$studentId] ?? 'PRESENT') 
                    ); 
 
                    $allowedStatuses = [ 
                        'PRESENT', 
                        'ABSENT', 
                        'PERMISSION' 
                    ]; 
 
                    if (!in_array($status, $allowedStatuses, true)) { 
                        $status = 'PRESENT'; 
                    } 
 
                    $permissionReason = null; 
 
                    if ($status === 'PERMISSION') { 
 
                        $permissionReason = trim( 
                            $permissionReasons[$studentId] ?? '' 
                        ); 
 
                        if ($permissionReason === '') { 
                            throw new Exception( 
                                'Permission reason is required for every student marked as Permission.' 
                            ); 
                        } 
 
                        if (strlen($permissionReason) > 255) { 
                            throw new Exception( 
                                'Permission reason cannot exceed 255 characters.' 
                            ); 
                        } 
                    } 
 
                    $attendanceStmt->execute([ 
                        $studentId, 
                        $selectedClassId, 
                        $selectedSubjectId, 
                        $teacherId, 
                        $selectedDate, 
                        $status, 
                        $permissionReason 
                    ]); 
                } 
 
                $pdo->commit(); 
 
                $success = 'Attendance saved successfully.'; 
 
            } catch (Throwable $e) { 
 
                if ($pdo->inTransaction()) { 
                    $pdo->rollBack(); 
                } 
 
                $error = $e->getMessage(); 
            } 
        } 
    } 
} 
 
 
/* 
|--------------------------------------------------------------------------
| Get Teacher Classes 
|--------------------------------------------------------------------------
*/ 
 
$classStmt = $pdo->prepare(" 
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
 
$classStmt->execute([$teacherId]); 
 
$teacherClasses = $classStmt->fetchAll(); 
 
 
/* 
|--------------------------------------------------------------------------
| Get Subjects For Selected Class 
|--------------------------------------------------------------------------
*/ 
 
$teacherSubjects = []; 
 
if ($selectedClassId > 0) { 
 
    $subjectStmt = $pdo->prepare(" 
        SELECT DISTINCT 
            s.id, 
            s.name, 
            s.code 
        FROM teaching_assignments ta 
        INNER JOIN subjects s 
            ON s.id = ta.subject_id 
        WHERE ta.teacher_id = ? 
          AND ta.class_id = ? 
        ORDER BY s.name ASC 
    "); 
 
    $subjectStmt->execute([ 
        $teacherId, 
        $selectedClassId 
    ]); 
 
    $teacherSubjects = $subjectStmt->fetchAll(); 
} 
 
 
/* 
|--------------------------------------------------------------------------
| Validate Selected Subject 
|--------------------------------------------------------------------------
*/ 
 
$validSubject = false; 
 
foreach ($teacherSubjects as $subject) { 
    if ((int) $subject['id'] === $selectedSubjectId) { 
        $validSubject = true; 
        break; 
    } 
} 
 
if (!$validSubject) { 
    $selectedSubjectId = 0; 
} 
 
 
/* 
|--------------------------------------------------------------------------
| Get Students And Existing Attendance 
|--------------------------------------------------------------------------
*/ 
 
$students = []; 
 
if ($selectedClassId > 0 && $selectedSubjectId > 0) { 
 
    $studentStmt = $pdo->prepare(" 
        SELECT 
            s.id, 
            s.student_reference, 
            s.admission_number, 
            s.first_name, 
            s.middle_name, 
            s.last_name, 
            s.gender, 
            s.photo_path, 
            a.status AS attendance_status, 
            a.permission_reason 
        FROM students s 
 
        INNER JOIN teaching_assignments ta 
            ON ta.class_id = s.class_id 
 
        LEFT JOIN attendance a 
            ON a.student_id = s.id 
            AND a.class_id = ? 
            AND a.subject_id = ? 
            AND a.teacher_id = ? 
            AND a.attendance_date = ? 
 
        WHERE s.class_id = ? 
          AND ta.teacher_id = ? 
          AND ta.subject_id = ? 
          AND s.status = 'ACTIVE' 
 
        GROUP BY 
            s.id, 
            s.student_reference, 
            s.admission_number, 
            s.first_name, 
            s.middle_name, 
            s.last_name, 
            s.gender, 
            s.photo_path, 
            a.status, 
            a.permission_reason 
 
        ORDER BY 
            s.first_name ASC, 
            s.last_name ASC 
    "); 
 
    $studentStmt->execute([ 
        $selectedClassId, 
        $selectedSubjectId, 
        $teacherId, 
        $selectedDate, 
        $selectedClassId, 
        $teacherId, 
        $selectedSubjectId 
    ]); 
 
    $students = $studentStmt->fetchAll(); 
} 
 
 
/* 
|--------------------------------------------------------------------------
| Attendance Statistics 
|--------------------------------------------------------------------------
*/ 
 
$totalStudents = count($students); 
$presentCount = 0; 
$absentCount = 0; 
$permissionCount = 0; 
$markedCount = 0; 
 
foreach ($students as $student) { 
 
    $status = $student['attendance_status'] ?? ''; 
 
    if ($status === 'PRESENT') { 
        $presentCount++; 
        $markedCount++; 
    } elseif ($status === 'ABSENT') { 
        $absentCount++; 
        $markedCount++; 
    } elseif ($status === 'PERMISSION') { 
        $permissionCount++; 
        $markedCount++; 
    } 
} 
 
 
/* 
|--------------------------------------------------------------------------
| Page Configuration
|--------------------------------------------------------------------------
| UI ONLY - page-specific Teacher CSS
|--------------------------------------------------------------------------
*/ 
 
$pageTitle = 'Take Attendance'; 
$pageType = 'teacher';

$additionalStyles = [ 
    '/student-attendance-system/assets/css/teacher-attendance.css'
]; 
 
 
/* 
|--------------------------------------------------------------------------
| Header / Navbar
|--------------------------------------------------------------------------
*/ 
 
require_once __DIR__ . '/../includes/header.php'; 
require_once __DIR__ . '/../includes/navbar.php'; 
?> 
 
<div class="dashboard-layout"> 
 
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?> 
 
    <main class="dashboard-main"> 
 
        <div class="page-header"> 
 
            <div> 
 
                <span class="page-eyebrow"> 
                    TEACHER PORTAL 
                </span> 
 
                <h1> 
                    Take Attendance 
                </h1> 
 
                <p> 
                    Record attendance for your assigned class and subject. 
                </p> 
 
            </div> 
 
        </div> 
 
 
        <?php if ($error): ?> 
 
            <div class="alert alert-error"> 
                <?= e($error) ?> 
            </div> 
 
        <?php endif; ?> 
 
 
        <?php if ($success): ?> 
 
            <div class="alert alert-success"> 
                <?= e($success) ?> 
            </div> 
 
        <?php endif; ?> 
 
 
        <!-- FILTER CARD --> 
 
        <section class="teacher-attendance-filter-card"> 
 
            <div class="teacher-attendance-filter-header"> 
 
                <div> 
 
                    <h2> 
                        Attendance Setup 
                    </h2> 
 
                    <p> 
                        Select the class, subject and attendance date. 
                    </p> 
 
                </div> 
 
                <div class="teacher-attendance-date-badge"> 
                    <?= e(date('D, d M Y', strtotime($selectedDate))) ?> 
                </div> 
 
            </div> 
 
 
            <form 
                method="GET" 
                class="teacher-attendance-filter-form"
            > 
 
                <div class="form-group"> 
 
                    <label for="class_id"> 
                        Class 
                    </label> 
 
                    <select 
                        name="class_id" 
                        id="class_id" 
                        required 
                        onchange="this.form.submit()" 
                    > 
 
                        <option value=""> 
                            Select class 
                        </option> 
 
                        <?php foreach ($teacherClasses as $class): ?> 
 
                            <option 
                                value="<?= (int) $class['id'] ?>" 
                                <?= $selectedClassId === (int) $class['id'] ? 'selected' : '' ?> 
                            > 
                                <?= e($class['name']) ?> 
                                — <?= e($class['code']) ?> 
                            </option> 
 
                        <?php endforeach; ?> 
 
                    </select> 
 
                </div> 
 
 
                <div class="form-group"> 
 
                    <label for="subject_id"> 
                        Subject 
                    </label> 
 
                    <select 
                        name="subject_id" 
                        id="subject_id" 
                        required 
                        <?= empty($teacherSubjects) ? 'disabled' : '' ?> 
                    > 
 
                        <option value=""> 
                            Select subject 
                        </option> 
 
                        <?php foreach ($teacherSubjects as $subject): ?> 
 
                            <option 
                                value="<?= (int) $subject['id'] ?>" 
                                <?= $selectedSubjectId === (int) $subject['id'] ? 'selected' : '' ?> 
                            > 
                                <?= e($subject['name']) ?> 
                                — <?= e($subject['code']) ?> 
                            </option> 
 
                        <?php endforeach; ?> 
 
                    </select> 
 
                </div> 
 
 
                <div class="form-group"> 
 
                    <label for="attendance_date"> 
                        Date 
                    </label> 
 
                    <input 
                        type="date" 
                        name="attendance_date" 
                        id="attendance_date" 
                        value="<?= e($selectedDate) ?>" 
                        required 
                    > 
 
                </div> 
 
 
                <div class="teacher-attendance-filter-action"> 
 
                    <button 
                        type="submit" 
                        class="btn btn-primary" 
                        <?= empty($teacherSubjects) ? 'disabled' : '' ?> 
                    > 
                        Load Students 
                    </button> 
 
                </div> 
 
            </form> 
 
        </section> 
 
 
        <?php if ($selectedClassId > 0 && $selectedSubjectId > 0): ?> 
 
 
            <!-- STATISTICS --> 
 
            <section class="teacher-attendance-stats"> 
 
                <div class="teacher-attendance-stat-card"> 
 
                    <div class="attendance-stat-icon"> 
                        👥 
                    </div> 
 
                    <div> 
 
                        <span> 
                            Total Students 
                        </span> 
 
                        <strong> 
                            <?= $totalStudents ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
 
                <div class="teacher-attendance-stat-card present"> 
 
                    <div class="attendance-stat-icon"> 
                        ✓ 
                    </div> 
 
                    <div> 
 
                        <span> 
                            Present 
                        </span> 
 
                        <strong> 
                            <?= $presentCount ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
 
                <div class="teacher-attendance-stat-card absent"> 
 
                    <div class="attendance-stat-icon"> 
                        × 
                    </div> 
 
                    <div> 
 
                        <span> 
                            Absent 
                        </span> 
 
                        <strong> 
                            <?= $absentCount ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
 
                <div class="teacher-attendance-stat-card permission"> 
 
                    <div class="attendance-stat-icon"> 
                        ! 
                    </div> 
 
                    <div> 
 
                        <span> 
                            Permission 
                        </span> 
 
                        <strong> 
                            <?= $permissionCount ?> 
                        </strong> 
 
                    </div> 
 
                </div> 
 
            </section> 
 
 
            <!-- ATTENDANCE FORM --> 
 
            <section class="teacher-attendance-card"> 
 
                <div class="teacher-attendance-card-header"> 
 
                    <div> 
 
                        <span class="page-eyebrow"> 
                            ATTENDANCE RECORD 
                        </span> 
 
                        <h2> 
                            <?= $totalStudents ?> Students 
                        </h2> 
 
                        <p> 
                            Mark each student's attendance status. 
                        </p> 
 
                    </div> 
 
 
                    <div class="attendance-marked-summary"> 
 
                        <strong> 
                            <?= $markedCount ?> 
                        </strong> 
 
                        <span> 
                            marked 
                        </span> 
 
                    </div> 
 
                </div> 
 
 
                <?php if (!empty($students)): ?> 
 
                    <form 
                        method="POST" 
                        id="attendanceForm" 
                    > 
 
                        <input 
                            type="hidden" 
                            name="csrf_token" 
                            value="<?= e(csrfToken()) ?>" 
                        > 
 
                        <input 
                            type="hidden" 
                            name="class_id" 
                            value="<?= $selectedClassId ?>" 
                        > 
 
                        <input 
                            type="hidden" 
                            name="subject_id" 
                            value="<?= $selectedSubjectId ?>" 
                        > 
 
                        <input 
                            type="hidden" 
                            name="attendance_date" 
                            value="<?= e($selectedDate) ?>" 
                        > 
 
 
                        <div class="attendance-table-wrapper"> 
 
                            <table class="attendance-entry-table"> 
 
                                <thead> 
 
                                    <tr> 
 
                                        <th>#</th> 
 
                                        <th>Student</th> 
 
                                        <th>Admission No.</th> 
 
                                        <th>Gender</th> 
 
                                        <th>Attendance</th> 
 
                                        <th>Permission Reason</th> 
 
                                    </tr> 
 
                                </thead> 
 
 
                                <tbody> 
 
                                    <?php foreach ($students as $index => $student): ?> 
 
                                        <?php 
 
                                        $studentId = (int) $student['id']; 
 
                                        $currentStatus = 
                                            $student['attendance_status'] 
                                            ?: 'PRESENT'; 
 
                                        ?> 
 
                                        <tr> 
 
                                            <td> 
                                                <?= $index + 1 ?> 
                                            </td> 
 
 
                                            <td> 
 
                                                <div class="attendance-student-cell"> 
 
                                                    <?php if (!empty($student['photo_path'])): ?> 
 
                                                        <img 
                                                            src="/student-attendance-system/<?= e($student['photo_path']) ?>" 
                                                            alt="<?= e($student['first_name']) ?>" 
                                                            class="attendance-student-avatar" 
                                                        > 
 
                                                    <?php else: ?> 
 
                                                        <div class="attendance-student-avatar attendance-student-placeholder"> 
                                                            <?= strtoupper(substr($student['first_name'], 0, 1)) ?> 
                                                        </div> 
 
                                                    <?php endif; ?> 
 
 
                                                    <div> 
 
                                                        <strong> 
 
                                                            <?= e( 
                                                                trim( 
                                                                    $student['first_name'] 
                                                                    . ' ' 
                                                                    . ($student['middle_name'] ?? '') 
                                                                    . ' ' 
                                                                    . $student['last_name'] 
                                                                ) 
                                                            ) ?> 
 
                                                        </strong> 
 
                                                        <span> 
                                                            <?= e($student['student_reference']) ?> 
                                                        </span> 
 
                                                    </div> 
 
                                                </div> 
 
                                            </td> 
 
 
                                            <td> 
 
                                                <span class="attendance-admission-number"> 
 
                                                    <?= e($student['admission_number']) ?> 
 
                                                </span> 
 
                                            </td> 
 
 
                                            <td> 
 
                                                <span 
                                                    class="attendance-gender-badge <?= strtolower($student['gender']) ?>" 
                                                > 
                                                    <?= e($student['gender']) ?> 
                                                </span> 
 
                                            </td> 
 
 
                                            <td> 
 
                                                <div class="attendance-status-options"> 
 
                                                    <label class="attendance-radio present-option"> 
 
                                                        <input 
                                                            type="radio" 
                                                            name="status[<?= $studentId ?>]" 
                                                            value="PRESENT" 
                                                            <?= $currentStatus === 'PRESENT' ? 'checked' : '' ?> 
                                                        > 
 
                                                        <span> 
                                                            Present 
                                                        </span> 
 
                                                    </label> 
 
 
                                                    <label class="attendance-radio absent-option"> 
 
                                                        <input 
                                                            type="radio" 
                                                            name="status[<?= $studentId ?>]" 
                                                            value="ABSENT" 
                                                            <?= $currentStatus === 'ABSENT' ? 'checked' : '' ?> 
                                                        > 
 
                                                        <span> 
                                                            Absent 
                                                        </span> 
 
                                                    </label> 
 
 
                                                    <label class="attendance-radio permission-option"> 
 
                                                        <input 
                                                            type="radio" 
                                                            name="status[<?= $studentId ?>]" 
                                                            value="PERMISSION" 
                                                            <?= $currentStatus === 'PERMISSION' ? 'checked' : '' ?> 
                                                        > 
 
                                                        <span> 
                                                            Permission 
                                                        </span> 
 
                                                    </label> 
 
                                                </div> 
 
                                            </td> 
 
 
                                            <td> 
 
                                                <input 
                                                    type="text" 
                                                    name="permission_reason[<?= $studentId ?>]" 
                                                    value="<?= e($student['permission_reason']) ?>" 
                                                    placeholder="Enter reason" 
                                                    maxlength="255" 
                                                    class="permission-reason-input" 
                                                    <?= $currentStatus !== 'PERMISSION' ? 'disabled' : '' ?> 
                                                > 
 
                                            </td> 
 
                                        </tr> 
 
                                    <?php endforeach; ?> 
 
                                </tbody> 
 
                            </table> 
 
                        </div> 
 
 
                        <div class="teacher-attendance-form-footer"> 
 
                            <div class="attendance-form-note"> 
 
                                <strong>Note:</strong> 
 
                                Permission requires a reason. 
 
                            </div> 
 
 
                            <button 
                                type="submit" 
                                name="save_attendance" 
                                value="1" 
                                class="btn btn-primary attendance-save-button" 
                            > 
                                Save Attendance 
                            </button> 
 
                        </div> 
 
                    </form> 
 
                <?php else: ?> 
 
                    <div class="teacher-attendance-empty"> 
 
                        <div class="teacher-attendance-empty-icon"> 
                            👥 
                        </div> 
 
                        <h3> 
                            No students found 
                        </h3> 
 
                        <p> 
                            There are currently no active students in this class. 
                        </p> 
 
                    </div> 
 
                <?php endif; ?> 
 
            </section> 
 
 
        <?php else: ?> 
 
 
            <!-- INITIAL STATE --> 
 
            <section class="teacher-attendance-empty teacher-attendance-start"> 
 
                <div class="teacher-attendance-empty-icon"> 
                    ✓ 
                </div> 
 
                <h2> 
                    Ready to Take Attendance 
                </h2> 
 
                <p> 
                    Select a class, subject and date above to load the students. 
                </p> 
 
            </section> 
 
        <?php endif; ?> 
 
    </main> 
 
</div> 
 
 
<script> 
 
document.addEventListener('DOMContentLoaded', function () { 
 
    const attendanceForm = document.getElementById('attendanceForm'); 
 
    if (!attendanceForm) { 
        return; 
    } 
 
    const rows = attendanceForm.querySelectorAll('tbody tr'); 
 
    rows.forEach(function (row) { 
 
        const radios = row.querySelectorAll( 
            'input[type="radio"][name^="status"]' 
        ); 
 
        const reasonInput = row.querySelector( 
            '.permission-reason-input' 
        ); 
 
        function updatePermissionField() { 
 
            const selected = row.querySelector( 
                'input[type="radio"]:checked' 
            ); 
 
            if (!selected || !reasonInput) { 
                return; 
            } 
 
            if (selected.value === 'PERMISSION') { 
 
                reasonInput.disabled = false; 
                reasonInput.required = true; 
 
            } else { 
 
                reasonInput.disabled = true; 
                reasonInput.required = false; 
                reasonInput.value = ''; 
 
            } 
        } 
 
        radios.forEach(function (radio) { 
 
            radio.addEventListener( 
                'change', 
                updatePermissionField 
            ); 
 
        }); 
 
        updatePermissionField(); 
 
    }); 
 
}); 
 
</script> 
 
 
<?php require_once __DIR__ . '/../includes/footer.php'; ?>