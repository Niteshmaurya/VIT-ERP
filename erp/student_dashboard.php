<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include 'db.php'; // Database connection

$student_id = $_SESSION['user_id'];

// Handle subject selection form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['subjects'])) {
    // Count existing subjects
    $sql = "SELECT COUNT(*) AS subject_count FROM student_subjects WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $chosen_count = $row['subject_count'];

    $selected_subjects = $_POST['subjects'];
    $remaining_slots = 7 - $chosen_count;

    if (count($selected_subjects) > $remaining_slots) {
        echo "<script>alert('You can only choose " . $remaining_slots . " more subjects.');</script>";
    } else {
        foreach ($selected_subjects as $subject_id) {
            $sql = "INSERT INTO student_subjects (student_id, subject_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $student_id, $subject_id);
            $stmt->execute();
        }
        echo "<script>alert('Subjects added successfully!'); window.location.href='student_dashboard.php';</script>";
    }
}

// Fetch available subjects (excluding those already chosen)
$sql = "SELECT * FROM subjects WHERE id NOT IN 
        (SELECT subject_id FROM student_subjects WHERE student_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result_available = $stmt->get_result();

// Fetch already chosen subjects
$sql = "SELECT subjects.* FROM subjects 
        JOIN student_subjects ON subjects.id = student_subjects.subject_id 
        WHERE student_subjects.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result_chosen = $stmt->get_result();
$chosen_count = $result_chosen->num_rows;

?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, <?php echo $_SESSION['user_name']; ?> (Student)</h2>
        <a href="logout.php" class="btn btn-danger">Logout</a>

        <!-- Available Subjects -->
        <h3 class="mt-4">Available Subjects</h3>
        <form method="POST">
            <table class="table table-bordered mt-3">
                <thead class="table-dark">
                    <tr>
                        <th>Select</th>
                        <th>Subject Name</th>
                        <th>Branch</th>
                        <th>Credits</th>
                        <th>Lab</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result_available->num_rows > 0) {
                        while ($row = $result_available->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td><input type='checkbox' name='subjects[]' value='" . $row["id"] . "'></td>";
                            echo "<td>" . $row["subject_name"] . "</td>";
                            echo "<td>" . $row["branch"] . "</td>";
                            echo "<td>" . ($row["subject_credits"] + $row["lab_credits"]) . "</td>";
                            echo "<td>" . ($row["has_lab"] ? 'Yes' : 'No') . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='text-center'>No subjects available</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php if ($chosen_count < 7) { ?>
                <button type="submit" class="btn btn-primary">Choose Selected Subjects</button>
            <?php } else { ?>
                <p class="text-danger">You have already selected the maximum 7 subjects.</p>
            <?php } ?>
        </form>

        <!-- Chosen Subjects -->
        <h3 class="mt-4">Your Selected Subjects</h3>
        <table class="table table-bordered mt-3">
            <thead class="table-dark">
                <tr>
                    <th>Subject Name</th>
                    <th>Branch</th>
                    <th>Credits</th>
                    <th>Lab</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result_chosen->num_rows > 0) {
                    while ($row = $result_chosen->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row["subject_name"] . "</td>";
                        echo "<td>" . $row["branch"] . "</td>";
                        echo "<td>" . ($row["subject_credits"] + $row["lab_credits"]) . "</td>";
                        echo "<td>" . ($row["has_lab"] ? 'Yes' : 'No') . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4' class='text-center'>No subjects chosen yet</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
