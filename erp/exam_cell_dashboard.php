<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'exam_cell') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

// Fetch all students with their details from 'users' table
$sql_students = "SELECT id, email FROM users WHERE role = 'student'";
$stmt_students = $conn->prepare($sql_students);
$stmt_students->execute();
$result_students = $stmt_students->get_result();

$students = [];
while ($row = $result_students->fetch_assoc()) {
    $students[$row['id']] = [
        'email' => $row['email'],
        'subjects' => []
    ];
}

// Fetch subjects selected by students
$sql_subjects = "SELECT 
    student_subjects.student_id,
    subjects.id AS subject_id,
    subjects.subject_name,
    subjects.branch,
    subjects.subject_credits,
    subjects.lab_credits,
    subjects.has_lab
FROM student_subjects
JOIN subjects ON student_subjects.subject_id = subjects.id
ORDER BY student_subjects.student_id, subjects.id";
$stmt_subjects = $conn->prepare($sql_subjects);
$stmt_subjects->execute();
$result_subjects = $stmt_subjects->get_result();

while ($row = $result_subjects->fetch_assoc()) {
    $student_id = $row['student_id'];

    if (isset($students[$student_id])) {
        $students[$student_id]['subjects'][] = [
            'subject_id' => $row['subject_id'],
            'subject_name' => $row['subject_name'],
            'branch' => $row['branch'],
            'subject_credits' => $row['subject_credits'],
            'lab_credits' => $row['lab_credits'],
            'has_lab' => $row['has_lab']
        ];
    }
}

// Handle adding or updating marks
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['student_id'], $_POST['subject_id'], $_POST['theory_marks'])) {
        $student_id = $_POST['student_id'];
        $subject_id = $_POST['subject_id'];
        $theory_marks = $_POST['theory_marks'];
        $lab_marks = isset($_POST['lab_marks']) ? $_POST['lab_marks'] : null;

        // Validate marks
        $errors = [];
        if ($theory_marks < 0 || $theory_marks > 100) {
            $errors[] = "Theory marks must be between 0 and 100";
        }
        if ($lab_marks !== null && ($lab_marks < 0 || $lab_marks > 50)) {
            $errors[] = "Lab marks must be between 0 and 50";
        }

        if (empty($errors)) {
            // Check if marks already exist for the student and subject
            $sql_check_marks = "SELECT id, lab_marks FROM student_marks WHERE student_id = ? AND subject_id = ?";
            $stmt_check_marks = $conn->prepare($sql_check_marks);
            $stmt_check_marks->bind_param("ii", $student_id, $subject_id);
            $stmt_check_marks->execute();
            $result_check_marks = $stmt_check_marks->get_result();

            if ($result_check_marks->num_rows > 0) {
                // If marks exist, update the marks
                $row = $result_check_marks->fetch_assoc();
                $mark_id = $row['id'];
                
                if ($row['lab_marks'] !== null || $lab_marks !== null) {
                    $sql_update_marks = "UPDATE student_marks SET marks = ?, lab_marks = ? WHERE id = ?";
                    $stmt_update_marks = $conn->prepare($sql_update_marks);
                    $stmt_update_marks->bind_param("iii", $theory_marks, $lab_marks, $mark_id);
                } else {
                    $sql_update_marks = "UPDATE student_marks SET marks = ? WHERE id = ?";
                    $stmt_update_marks = $conn->prepare($sql_update_marks);
                    $stmt_update_marks->bind_param("ii", $theory_marks, $mark_id);
                }
                $stmt_update_marks->execute();
                $stmt_update_marks->close();
                $success_message = "Marks updated successfully!";
            } else {
                // If marks do not exist, insert the new marks
                if ($lab_marks !== null) {
                    $sql_insert_marks = "INSERT INTO student_marks (student_id, subject_id, marks, lab_marks) VALUES (?, ?, ?, ?)";
                    $stmt_insert_marks = $conn->prepare($sql_insert_marks);
                    $stmt_insert_marks->bind_param("iiii", $student_id, $subject_id, $theory_marks, $lab_marks);
                } else {
                    $sql_insert_marks = "INSERT INTO student_marks (student_id, subject_id, marks) VALUES (?, ?, ?)";
                    $stmt_insert_marks = $conn->prepare($sql_insert_marks);
                    $stmt_insert_marks->bind_param("iii", $student_id, $subject_id, $theory_marks);
                }
                $stmt_insert_marks->execute();
                $stmt_insert_marks->close();
                $success_message = "Marks added successfully!";
            }
        }
    }
}

// First, we need to modify the student_marks table to add lab_marks column if it doesn't exist
$sql_check_column = "SHOW COLUMNS FROM student_marks LIKE 'lab_marks'";
$result_check_column = $conn->query($sql_check_column);
if ($result_check_column->num_rows == 0) {
    $sql_add_column = "ALTER TABLE student_marks ADD COLUMN lab_marks INT NULL AFTER marks";
    $conn->query($sql_add_column);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Cell Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .marks-container {
            display: flex;
            gap: 10px;
        }
        .marks-input {
            flex: 1;
        }
        .max-marks {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?> (Exam Cell)</h2>
        <a href="login.php" class="btn btn-danger">Logout</a>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger mt-3">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success mt-3">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <h3 class="mt-4">Students' Selected Subjects</h3>
        <table class="table table-bordered mt-3">
            <thead class="table-dark">
                <tr>
                    <th>Student ID</th>
                    <th>Email</th>
                    <th>Subject ID</th>
                    <th>Subject Details</th>
                    <th>Marks</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)) {
                    foreach ($students as $id => $student) { ?>
                        <tr>
                            <td rowspan="<?php echo max(1, count($student['subjects'])); ?>">
                                <?php echo htmlspecialchars($id); ?>
                            </td>
                            <td rowspan="<?php echo max(1, count($student['subjects'])); ?>">
                                <?php echo htmlspecialchars($student['email']); ?>
                            </td>
                            <?php if (!empty($student['subjects'])) {
                                foreach ($student['subjects'] as $index => $subject) { ?>
                                    <?php if ($index > 0) echo "<tr>"; ?>
                                    <td><?php echo htmlspecialchars($subject['subject_id']); ?></td>
                                    <td>
                                        <?php 
                                        echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['branch'] . ')');
                                        echo '<br><small class="text-muted">';
                                        echo 'Theory: ' . $subject['subject_credits'] . ' credits';
                                        if ($subject['has_lab']) {
                                            echo ', Lab: ' . $subject['lab_credits'] . ' credits';
                                        }
                                        echo '</small>';
                                        ?>
                                    </td>
                                    <td>
                                        <form method="POST">
                                            <input type="hidden" name="student_id" value="<?php echo $id; ?>">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['subject_id']; ?>">

                                            <?php
                                            // Fetch existing marks for the subject
                                            $sql_existing_marks = "SELECT marks, lab_marks FROM student_marks WHERE student_id = ? AND subject_id = ?";
                                            $stmt_existing_marks = $conn->prepare($sql_existing_marks);
                                            $stmt_existing_marks->bind_param("ii", $id, $subject['subject_id']);
                                            $stmt_existing_marks->execute();
                                            $result_existing_marks = $stmt_existing_marks->get_result();
                                            $existing_marks = $result_existing_marks->fetch_assoc();
                                            ?>

                                            <div class="marks-container">
                                                <div class="marks-input">
                                                    <label class="form-label">Theory</label>
                                                    <input type="number" name="theory_marks" min="0" max="100" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($existing_marks['marks'] ?? ''); ?>" 
                                                           required>
                                                    <div class="max-marks">(Out of 100)</div>
                                                </div>
                                                <?php if ($subject['has_lab']) { ?>
                                                <div class="marks-input">
                                                    <label class="form-label">Lab</label>
                                                    <input type="number" name="lab_marks" min="0" max="50" 
                                                           class="form-control" 
                                                           value="<?php echo htmlspecialchars($existing_marks['lab_marks'] ?? ''); ?>">
                                                    <div class="max-marks">(Out of 50)</div>
                                                </div>
                                                <?php } ?>
                                            </div>
                                            <button type="submit" class="btn btn-primary mt-2">Submit Marks</button>
                                        </form>
                                    </td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <td colspan="2" class="text-center">No subjects selected</td>
                            </tr>
                            <?php }
                    } 
                } else { ?>
                    <tr><td colspan="5" class="text-center">No students found</td></tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>
</html>