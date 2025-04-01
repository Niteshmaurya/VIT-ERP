<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'exam_cell') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

// Fetch all students with their details
$sql_students = "SELECT id, name, roll_no FROM students";
$stmt_students = $conn->prepare($sql_students);
$stmt_students->execute();
$result_students = $stmt_students->get_result();

$students = [];
while ($row = $result_students->fetch_assoc()) {
    $students[$row['id']] = [
        'name' => $row['name'],
        'roll_no' => $row['roll_no'],
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
            'credits' => $row['subject_credits'] + $row['lab_credits'],
            'has_lab' => $row['has_lab'] ? 'Yes' : 'No'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Cell Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?> (Exam Cell)</h2>
        <a href="logout.php" class="btn btn-danger">Logout</a>

        <h3 class="mt-4">Students' Selected Subjects</h3>
        <table class="table table-bordered mt-3">
            <thead class="table-dark">
                <tr>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Roll Number</th>
                    <th>Subject ID</th>
                    <th>Subjects</th>
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
                                <?php echo htmlspecialchars($student['name']); ?>
                            </td>
                            <td rowspan="<?php echo max(1, count($student['subjects'])); ?>">
                                <?php echo htmlspecialchars($student['roll_no']); ?>
                            </td>
                            <?php if (!empty($student['subjects'])) {
                                foreach ($student['subjects'] as $index => $subject) { ?>
                                    <?php if ($index > 0) echo "<tr>"; ?>
                                    <td><?php echo htmlspecialchars($subject['subject_id']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['branch'] . ', ' . $subject['credits'] . ' credits, Lab: ' . $subject['has_lab'] . ')'); ?>
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
