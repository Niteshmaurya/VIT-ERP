<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

$student_id = $_SESSION['user_id'];

// Fetch student's semester
$sql_semester = "SELECT semester FROM students WHERE id = ?";
$stmt_semester = $conn->prepare($sql_semester);
$stmt_semester->bind_param("i", $student_id);
$stmt_semester->execute();
$result_semester = $stmt_semester->get_result();
$row_semester = $result_semester->fetch_assoc();
$current_semester = $row_semester['semester'];

// Define semester-wise credit limits (you can adjust these values as needed)
$semester_credit_limits = [
    1 => 24, // Semester 1 max credits
    2 => 24, // Semester 2 max credits
    3 => 24, // Semester 3 max credits
    4 => 24, // Semester 4 max credits
    5 => 24, // Semester 5 max credits
    6 => 24, // Semester 6 max credits
    7 => 24, // Semester 7 max credits
    8 => 24  // Semester 8 max credits
];

$current_semester_limit = $semester_credit_limits[$current_semester] ?? 24; // Default to 24 if semester not defined

// Calculate current semester's used credits
$sql_current_credits = "SELECT SUM(subjects.subject_credits + subjects.lab_credits) AS total_credits
                       FROM student_subjects
                       JOIN subjects ON student_subjects.subject_id = subjects.id
                       JOIN students ON student_subjects.student_id = students.id
                       WHERE student_subjects.student_id = ? AND students.semester = ?";
$stmt_current_credits = $conn->prepare($sql_current_credits);
$stmt_current_credits->bind_param("ii", $student_id, $current_semester);
$stmt_current_credits->execute();
$result_current_credits = $stmt_current_credits->get_result();
$row_current_credits = $result_current_credits->fetch_assoc();
$current_credits_used = $row_current_credits['total_credits'] ?? 0;

// Handle subject selection form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['subjects'])) {
        // Add subjects logic (same as before)
        $selected_subjects = $_POST['subjects'];
        
        // Calculate total credits of selected subjects
        $selected_credits = 0;
        $subject_ids = implode(',', array_map('intval', $selected_subjects));
        $sql_credits = "SELECT SUM(subject_credits + lab_credits) AS total FROM subjects WHERE id IN ($subject_ids)";
        $result_credits = $conn->query($sql_credits);
        $row_credits = $result_credits->fetch_assoc();
        $selected_credits = $row_credits['total'] ?? 0;
        
        // Check if selection exceeds credit limit
        if (($current_credits_used + $selected_credits) > $current_semester_limit) {
            $remaining_credits = $current_semester_limit - $current_credits_used;
            echo "<script>alert('You can only select up to $remaining_credits more credits this semester.');</script>";
        } else {
            $all_subjects_added = true;
            $error_messages = [];

            // Check for dependent subjects in the selected list
            foreach ($selected_subjects as $subject_id) {
                // Get prerequisite information
                $sql_prereq = "SELECT prerequisite_id FROM subjects WHERE id = ?";
                $stmt_prereq = $conn->prepare($sql_prereq);
                $stmt_prereq->bind_param("i", $subject_id);
                $stmt_prereq->execute();
                $result_prereq = $stmt_prereq->get_result();
                $row_prereq = $result_prereq->fetch_assoc();
                $prerequisite_id = $row_prereq['prerequisite_id'];

                // Check if this subject is a prerequisite for any other selected subject
                $sql_is_prereq = "SELECT id, subject_name FROM subjects WHERE prerequisite_id = ? AND id IN ($subject_ids)";
                $stmt_is_prereq = $conn->prepare($sql_is_prereq);
                $stmt_is_prereq->bind_param("i", $subject_id);
                $stmt_is_prereq->execute();
                $result_is_prereq = $stmt_is_prereq->get_result();
                
                if ($result_is_prereq->num_rows > 0) {
                    $dependent_subjects = [];
                    while ($row = $result_is_prereq->fetch_assoc()) {
                        $dependent_subjects[] = $row['subject_name'];
                    }
                    $error_messages[] = "You cannot select " . implode(' and ', $dependent_subjects) . " together with its prerequisite in the same semester.";
                    $all_subjects_added = false;
                    break;
                }

                if ($prerequisite_id) {
                    // Check if the student has completed the prerequisite
                    $sql_completed = "SELECT COUNT(*) AS completed FROM student_subjects 
                                    WHERE student_id = ? AND subject_id = ?";
                    $stmt_completed = $conn->prepare($sql_completed);
                    $stmt_completed->bind_param("ii", $student_id, $prerequisite_id);
                    $stmt_completed->execute();
                    $result_completed = $stmt_completed->get_result();
                    $row_completed = $result_completed->fetch_assoc();

                    if ($row_completed['completed'] == 0) {
                        // Check if the prerequisite is in the current selection
                        if (in_array($prerequisite_id, $selected_subjects)) {
                            $sql_prereq_name = "SELECT subject_name FROM subjects WHERE id = ?";
                            $stmt_prereq_name = $conn->prepare($sql_prereq_name);
                            $stmt_prereq_name->bind_param("i", $prerequisite_id);
                            $stmt_prereq_name->execute();
                            $result_prereq_name = $stmt_prereq_name->get_result();
                            $row_prereq_name = $result_prereq_name->fetch_assoc();
                            $prereq_subject_name = $row_prereq_name['subject_name'];
                            
                            $sql_subject_name = "SELECT subject_name FROM subjects WHERE id = ?";
                            $stmt_subject_name = $conn->prepare($sql_subject_name);
                            $stmt_subject_name->bind_param("i", $subject_id);
                            $stmt_subject_name->execute();
                            $result_subject_name = $stmt_subject_name->get_result();
                            $row_subject_name = $result_subject_name->fetch_assoc();
                            $subject_name = $row_subject_name['subject_name'];
                            
                            $error_messages[] = "You cannot select $subject_name and its prerequisite $prereq_subject_name in the same semester.";
                            $all_subjects_added = false;
                            break;
                        } else {
                            // Prerequisite not completed and not in current selection
                            $sql_prereq_name = "SELECT subject_name FROM subjects WHERE id = ?";
                            $stmt_prereq_name = $conn->prepare($sql_prereq_name);
                            $stmt_prereq_name->bind_param("i", $prerequisite_id);
                            $stmt_prereq_name->execute();
                            $result_prereq_name = $stmt_prereq_name->get_result();
                            $row_prereq_name = $result_prereq_name->fetch_assoc();
                            $prereq_subject_name = $row_prereq_name['subject_name'];
                            
                            $error_messages[] = "You must first complete the prerequisite subject: $prereq_subject_name.";
                            $all_subjects_added = false;
                            break;
                        }
                    }
                }
            }

            if ($all_subjects_added) {
                // All checks passed, insert the subjects
                foreach ($selected_subjects as $subject_id) {
                    $sql_insert = "INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    $stmt_insert->bind_param("ii", $student_id, $subject_id);
                    $stmt_insert->execute();
                }
                echo "<script>alert('Subjects added successfully!'); window.location.href='student_dashboard.php';</script>";
            } else {
                // Show all error messages
                echo "<script>alert('" . implode("\\n", $error_messages) . "');</script>";
            }
        }
    } elseif (isset($_POST['delete_subjects'])) {
        // Handle subject deletion
        if (isset($_POST['selected_subjects_to_delete'])) {
            $subjects_to_delete = $_POST['selected_subjects_to_delete'];
            
            // Delete each selected subject
            foreach ($subjects_to_delete as $subject_id) {
                $sql_delete = "DELETE FROM student_subjects WHERE student_id = ? AND subject_id = ?";
                $stmt_delete = $conn->prepare($sql_delete);
                $stmt_delete->bind_param("ii", $student_id, $subject_id);
                $stmt_delete->execute();
            }
            
            echo "<script>alert('Selected subjects deleted successfully!'); window.location.href='student_dashboard.php';</script>";
        } else {
            echo "<script>alert('No subjects selected for deletion.');</script>";
        }
    }
}

// Fetch available subjects (excluding those already chosen)
$sql = "SELECT s1.* FROM subjects s1 
        WHERE s1.id NOT IN (SELECT subject_id FROM student_subjects WHERE student_id = ?)
        AND NOT EXISTS (
            SELECT 1 FROM subjects s2 
            WHERE s2.prerequisite_id = s1.id 
            AND s2.id IN (SELECT subject_id FROM student_subjects WHERE student_id = ?)
        )";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $student_id, $student_id);
$stmt->execute();
$result_available = $stmt->get_result();

// Fetch already chosen subjects for current semester
$sql_chosen = "SELECT subjects.* FROM subjects 
               JOIN student_subjects ON subjects.id = student_subjects.subject_id 
               JOIN students ON student_subjects.student_id = students.id
               WHERE student_subjects.student_id = ? AND students.semester = ?";
$stmt_chosen = $conn->prepare($sql_chosen);
$stmt_chosen->bind_param("ii", $student_id, $current_semester);
$stmt_chosen->execute();
$result_chosen = $stmt_chosen->get_result();
$chosen_count = $result_chosen->num_rows;

// Calculate remaining credits
$remaining_credits = $current_semester_limit - $current_credits_used;

// Get all subjects with their prerequisites for JavaScript validation
$sql_all_subjects = "SELECT id, subject_name, prerequisite_id, (subject_credits + lab_credits) as total_credits FROM subjects";
$result_all_subjects = $conn->query($sql_all_subjects);
$subjects_info = [];
while ($row = $result_all_subjects->fetch_assoc()) {
    $subjects_info[$row['id']] = $row;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
    // JavaScript to prevent selecting dependent subjects and enforce credit limits
    const subjectsInfo = <?php echo json_encode($subjects_info); ?>;
    const remainingCredits = <?php echo $remaining_credits; ?>;
    
    function validateSubjectSelection() {
        const checkboxes = document.querySelectorAll('input[name="subjects[]"]:checked');
        let selectedCredits = 0;
        const selectedIds = [];
        
        // Calculate total credits of selected subjects
        checkboxes.forEach(cb => {
            const subjectId = parseInt(cb.value);
            selectedIds.push(subjectId);
            // Convert credits to numbers before adding
            selectedCredits += parseInt(subjectsInfo[subjectId].total_credits);
        });
        
        // Check if any subjects are selected
        if (selectedIds.length === 0) {
            alert('Please select at least one subject.');
            return false;
        }
        
        // Check credit limit
        if (selectedCredits > remainingCredits) {
            alert(`You can only select up to ${remainingCredits} more credits this semester. You're trying to select ${selectedCredits} credits.`);
            return false;
        }
        
        // Check for dependent subjects
        for (let i = 0; i < selectedIds.length; i++) {
            const subjectId = selectedIds[i];
            const subject = subjectsInfo[subjectId];
            
            // Check if this subject is a prerequisite for any other selected subject
            for (let j = 0; j < selectedIds.length; j++) {
                if (i === j) continue;
                
                const otherSubject = subjectsInfo[selectedIds[j]];
                if (otherSubject.prerequisite_id == subjectId) {
                    alert(`You cannot select ${subject.subject_name} and ${otherSubject.subject_name} together in the same semester.`);
                    return false;
                }
            }
            
            // Check if this subject has a prerequisite that's also selected
            if (subject.prerequisite_id && selectedIds.includes(subject.prerequisite_id)) {
                const prereqSubject = subjectsInfo[subject.prerequisite_id];
                alert(`You cannot select ${subject.subject_name} and its prerequisite ${prereqSubject.subject_name} together in the same semester.`);
                return false;
            }
        }
        
        return true;
    }
    
    function confirmDelete() {
        const checkboxes = document.querySelectorAll('input[name="selected_subjects_to_delete[]"]:checked');
        if (checkboxes.length === 0) {
            alert('Please select at least one subject to delete.');
            return false;
        }
        return confirm('Are you sure you want to delete the selected subjects?');
    }
</script>
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, <?php echo isset($_SESSION['name']) ? $_SESSION['name'] : 'Student'; ?> (Student)</h2>
        <p>Current Semester: <strong><?php echo $current_semester; ?></strong></p>
        <p>Credits Used: <strong><?php echo $current_credits_used; ?></strong> of <strong><?php echo $current_semester_limit; ?></strong> (<?php echo $remaining_credits; ?> remaining)</p>
        <a href="login.php" class="btn btn-danger">Logout</a>
        <a href="viewMarks.php" class="btn btn-primary">View Results</a>

        <!-- Available Subjects -->
        <h3 class="mt-4">Available Subjects</h3>
        <form method="POST" onsubmit="return validateSubjectSelection()">
            <table class="table table-bordered mt-3">
                <thead class="table-dark">
                    <tr>
                        <th>Select</th>
                        <th>Subject Name</th>
                        <th>Branch</th>
                        <th>Credits (Theory + Lab)</th>
                        <th>Lab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_available->num_rows > 0) {
                        while ($row = $result_available->fetch_assoc()) {
                            $total_credits = $row['subject_credits'] + $row['lab_credits'];
                            echo "<tr>";
                            echo "<td><input type='checkbox' name='subjects[]' value='" . $row["id"] . "' data-credits='$total_credits'></td>";
                            echo "<td>" . $row["subject_name"];
                            
                            // Show prerequisite information
                            if ($row["prerequisite_id"]) {
                                $sql_prereq_name = "SELECT subject_name FROM subjects WHERE id = ?";
                                $stmt_prereq_name = $conn->prepare($sql_prereq_name);
                                $stmt_prereq_name->bind_param("i", $row["prerequisite_id"]);
                                $stmt_prereq_name->execute();
                                $result_prereq_name = $stmt_prereq_name->get_result();
                                if ($result_prereq_name->num_rows > 0) {
                                    $prereq_row = $result_prereq_name->fetch_assoc();
                                    echo "<br><small class='text-muted'>Prerequisite: " . $prereq_row['subject_name'] . "</small>";
                                }
                            }
                            
                            echo "</td>";
                            echo "<td>" . $row["branch"] . "</td>";
                            echo "<td>" . $row["subject_credits"] . " + " . $row["lab_credits"] . " = " . $total_credits . "</td>";
                            echo "<td>" . ($row["has_lab"] ? 'Yes' : 'No') . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center'>No subjects available</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php if ($remaining_credits > 0) { ?>
                <button type="submit" class="btn btn-primary">Choose Selected Subjects</button>
            <?php } else { ?>
                <p class="text-danger">You have reached the maximum credits allowed for this semester.</p>
            <?php } ?>
        </form>

        <!-- Chosen Subjects -->
        <h3 class="mt-4">Your Selected Subjects (Semester <?php echo $current_semester; ?>)</h3>
        <form method="POST" onsubmit="return confirmDelete()">
            <table class="table table-bordered mt-3">
                <thead class="table-dark">
                    <tr>
                        <th>Select</th>
                        <th>Subject Name</th>
                        <th>Branch</th>
                        <th>Credits (Theory + Lab)</th>
                        <th>Lab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_chosen->num_rows > 0) {
                        while ($row = $result_chosen->fetch_assoc()) {
                            $total_credits = $row['subject_credits'] + $row['lab_credits'];
                            echo "<tr>";
                            echo "<td><input type='checkbox' name='selected_subjects_to_delete[]' value='" . $row["id"] . "'></td>";
                            echo "<td>" . $row["subject_name"] . "</td>";
                            echo "<td>" . $row["branch"] . "</td>";
                            echo "<td>" . $row["subject_credits"] . " + " . $row["lab_credits"] . " = " . $total_credits . "</td>";
                            echo "<td>" . ($row["has_lab"] ? 'Yes' : 'No') . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center'>No subjects selected for this semester</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            
            <?php if ($result_chosen->num_rows > 0) { ?>
                <button type="submit" name="delete_subjects" class="btn btn-danger">Delete Selected Subjects</button>
            <?php } ?>
        </form>
    </div>
</body>
</html>