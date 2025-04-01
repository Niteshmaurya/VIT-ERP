<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

// Fetch student's marks and subjects including lab marks if available
$sql_marks = "SELECT 
                subjects.subject_name, 
                subjects.branch, 
                subjects.subject_credits, 
                subjects.lab_credits,
                subjects.has_lab,
                student_marks.marks as theory_marks,
                student_marks.lab_marks
              FROM student_marks
              JOIN subjects ON student_marks.subject_id = subjects.id
              WHERE student_marks.student_id = ?";
$stmt_marks = $conn->prepare($sql_marks);
$stmt_marks->bind_param("i", $_SESSION['user_id']);
$stmt_marks->execute();
$result_marks = $stmt_marks->get_result();

$marks = [];
$total_credits_earned = 0;
$total_weighted_points = 0;
$total_possible_credits = 0;

while ($row = $result_marks->fetch_assoc()) {
    $subject_data = $row;
    
    // Calculate total marks (theory + lab if exists)
    $theory_marks = $row['theory_marks'];
    $lab_marks = $row['lab_marks'] ?? 0;
    $has_lab = $row['has_lab'];
    
    // Calculate percentage for theory (out of 100)
    $theory_percentage = $theory_marks;
    
    // Calculate percentage for lab (out of 50, scaled to 100 for calculation)
    $lab_percentage = $has_lab ? ($lab_marks * 2) : 0; // Scale 50 to 100
    
    // Combined percentage (weighted average if lab exists)
    if ($has_lab) {
        // Assuming theory is 70% and lab is 30% of total marks
        $combined_percentage = ($theory_percentage * 0.7) + ($lab_percentage * 0.3);
        // 90 *0.3 + 23*0.7
    } else {
        $combined_percentage = $theory_percentage;
    }
    
    // Determine if passed (minimum 40% in both components)
    $theory_passed = ($theory_percentage >= 40);
    $lab_passed = $has_lab ? ($lab_marks >= 20) : true; // 20 out of 50 is 40%
    $subject_passed = $theory_passed && $lab_passed;
    
    // Calculate credits earned (only if passed)
    $credits_earned = $subject_passed ? ($row['subject_credits'] + ($has_lab ? $row['lab_credits'] : 0)) : 0;
    
    // Get grade points based on combined percentage
    if ($subject_passed) {
        if ($combined_percentage >= 90) {
            $grade_points = 4.0;
        } elseif ($combined_percentage >= 80) {
            $grade_points = 3.5;
        } elseif ($combined_percentage >= 70) {
            $grade_points = 3.0;
        } elseif ($combined_percentage >= 60) {
            $grade_points = 2.5;
        } elseif ($combined_percentage >= 50) {
            $grade_points = 2.0;
        } else {
            $grade_points = 1.0; // Minimum passing grade
        }
    } else {
        $grade_points = 0.0;
    }

    // Calculate total weighted points (only for passed subjects)
    $total_weighted_points += ($grade_points * $credits_earned);
    
    // Calculate total credits earned (only for passed subjects)
    $total_credits_earned += $credits_earned;
    
    // Calculate total possible credits (for all subjects)
    $total_possible_credits += ($row['subject_credits'] + ($has_lab ? $row['lab_credits'] : 0));
    
    // Add additional calculated fields to the subject data
    $subject_data['theory_passed'] = $theory_passed;
    $subject_data['lab_passed'] = $lab_passed;
    $subject_data['subject_passed'] = $subject_passed;
    $subject_data['credits_earned'] = $credits_earned;
    $subject_data['combined_percentage'] = $combined_percentage;
    $subject_data['grade_points'] = $grade_points;
    
    $marks[] = $subject_data;
}

// Calculate CGPA (only considering passed subjects)
if ($total_credits_earned > 0) {
    $cgpa = $total_weighted_points / $total_credits_earned;
} else {
    $cgpa = 0;
}

// Update CGPA in the database
$sql_update_cgpa = "UPDATE students SET cgpa = ? WHERE id = ?";
$stmt_update_cgpa = $conn->prepare($sql_update_cgpa);
$stmt_update_cgpa->bind_param("di", $cgpa, $_SESSION['user_id']);
$stmt_update_cgpa->execute();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Marks</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .passed { color: green; }
        .failed { color: red; }
        .progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            margin-top: 5px;
        }
        .progress-bar {
            height: 20px;
            border-radius: 5px;
            background-color: #4CAF50;
            text-align: center;
            line-height: 20px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Hi, <?php echo htmlspecialchars($_SESSION['name']); ?> (Student)</h2>
        <a href="login.php" class="btn btn-danger">Logout</a>
        <a href="student_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>

        <h3 class="mt-4">Your Marks</h3>
        <table class="table table-bordered mt-3">
            <thead class="table-dark">
                <tr>
                    <th>Subject Name</th>
                    <th>Theory Marks<br>(Out of 100)</th>
                    <th>Lab Marks<br>(Out of 50)</th>
                    <th>Total Credits<br>(Earned/Possible)</th>
                    <th>Status</th>
                    <th>Grade Points</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($marks)) {
                    foreach ($marks as $mark) { 
                        $has_lab = $mark['has_lab'];
                        ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($mark['subject_name']); ?><br>
                                <small><?php echo htmlspecialchars($mark['branch']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($mark['theory_marks']); ?>
                                <div class="progress-bar-container">
                                    <div class="progress-bar" style="width: <?php echo $mark['theory_marks']; ?>%">
                                        <?php echo $mark['theory_marks']; ?>%
                                    </div>
                                </div>
                                <span class="<?php echo $mark['theory_passed'] ? 'passed' : 'failed'; ?>">
                                    <?php echo $mark['theory_passed'] ? 'Passed' : 'Failed'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($has_lab) { 
                                    echo htmlspecialchars($mark['lab_marks'] ?? 'N/A'); ?>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?php echo ($mark['lab_marks'] / 50 * 100); ?>%">
                                            <?php echo round($mark['lab_marks'] / 50 * 100); ?>%
                                        </div>
                                    </div>
                                    <span class="<?php echo $mark['lab_passed'] ? 'passed' : 'failed'; ?>">
                                        <?php echo $mark['lab_passed'] ? 'Passed' : 'Failed'; ?>
                                    </span>
                                <?php } else { 
                                    echo 'N/A'; 
                                } ?>
                            </td>
                            <td>
                                <?php echo $mark['credits_earned']; ?> / 
                                <?php echo ($mark['subject_credits'] + ($has_lab ? $mark['lab_credits'] : 0)); ?>
                            </td>
                            <td class="<?php echo $mark['subject_passed'] ? 'passed' : 'failed'; ?>">
                                <?php echo $mark['subject_passed'] ? 'Passed' : 'Failed'; ?>
                            </td>
                            <td>
                                <?php echo number_format($mark['grade_points'], 2); ?>
                            </td>
                        </tr>
                    <?php }
                } else { ?>
                    <tr><td colspan="6" class="text-center">No marks available</td></tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="card mt-4">
            <div class="card-header">
                <h4>Academic Summary</h4>
            </div>
            <div class="card-body">
                <p><strong>Total Credits Earned:</strong> <?php echo $total_credits_earned; ?> out of <?php echo $total_possible_credits; ?></p>
                <p><strong>Your CGPA:</strong> <?php echo number_format($cgpa, 2); ?></p>
                <p class="text-muted">* CGPA is calculated only for passed subjects with minimum 40% marks in each component</p>
            </div>
        </div>
    </div>
</body>
</html>