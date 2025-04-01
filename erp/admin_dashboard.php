<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = $_POST['subject'];
    $branch = $_POST['branch'];
    $has_lab = isset($_POST['has_lab']) ? 1 : 0;
    $subject_credits = $_POST['subject_credits'];
    $lab_credits = $has_lab ? $_POST['lab_credits'] : 0;

    $sql = "INSERT INTO subjects (subject_name, branch, has_lab, subject_credits, lab_credits) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiis", $subject, $branch, $has_lab, $subject_credits, $lab_credits);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Subject added successfully.</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function toggleLabCredits() {
            let labCheckbox = document.getElementById("has_lab");
            let labCreditsDiv = document.getElementById("lab_credits_div");
            labCreditsDiv.style.display = labCheckbox.checked ? "block" : "none";
        }
    </script>
</head>
<body>
    <div class="container mt-5">
        <h2>Welcome, <?php echo $_SESSION['name']; ?> (Admin)</h2>
        <a href="login.php" class="btn btn-danger">Logout</a>
        
        <h3 class="mt-4">Add Subject</h3>
        
        <?php echo $message; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Branch</label>
                <select name="branch" class="form-control" required>
                    <option value="CSE">Computer Science Engineering</option>
                    <option value="ECE">Electronics and Communication Engineering</option>
                    <option value="ME">Mechanical Engineering</option>
                    <option value="EE">Electrical Engineering</option>
                    <option value="CE">Civil Engineering</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Subject Name</label>
                <select name="subject" class="form-control" required>
                    <option value="Programming in C">Programming in C</option>
                    <option value="Data Structures">Data Structures</option>
                    <option value="Computer Networks">Computer Networks</option>
                    <option value="Operating Systems">Operating Systems</option>
                    <option value="Database Management Systems">Database Management Systems</option>
                    <option value="Algorithms">Algorithms</option>
                    <option value="Artificial Intelligence">Artificial Intelligence</option>
                    <option value="Machine Learning">Machine Learning</option>
                    <option value="Deep Learning">Deep Learning</option>
                    <option value="Computer Vision">Computer Vision</option>
                    <option value="Natural Language Processing">Natural Language Processing</option>
                    <option value="Big Data Analytics">Big Data Analytics</option>
                    <option value="Cloud Computing">Cloud Computing</option>
                    <option value="Internet of Things (IoT)">Internet of Things (IoT)</option>
                    <option value="Blockchain Technology">Blockchain Technology</option>
                    <option value="Cyber Security">Cyber Security</option>
                    <option value="Software Engineering">Software Engineering</option>
                    <option value="Full Stack Development">Full Stack Development</option>
                    <option value="Web Technologies">Web Technologies</option>
                    <option value="Mobile App Development">Mobile App Development</option>
                    <option value="Compiler Design">Compiler Design</option>
                    <option value="Theory of Computation">Theory of Computation</option>
                    <option value="Parallel Computing">Parallel Computing</option>
                    <option value="Distributed Systems">Distributed Systems</option>
                    <option value="Cloud Security">Cloud Security</option>
                    <option value="Human-Computer Interaction">Human-Computer Interaction</option>
                    <option value="Augmented & Virtual Reality">Augmented & Virtual Reality</option>
                    <option value="Quantum Computing">Quantum Computing</option>
                    <option value="Robotics">Robotics</option>
                    <option value="Game Development">Game Development</option>
                    <option value="Embedded Systems">Embedded Systems</option>
                    <option value="Digital Signal Processing">Digital Signal Processing</option>
                    <option value="Software Testing">Software Testing</option>
                    <option value="Microprocessors & Microcontrollers">Microprocessors & Microcontrollers</option>
                    <option value="Cryptography & Network Security">Cryptography & Network Security</option>
                    <option value="DevOps">DevOps</option>
                    <option value="Ethical Hacking">Ethical Hacking</option>
                    <option value="Software Project Management">Software Project Management</option>
                    <option value="Artificial Neural Networks">Artificial Neural Networks</option>
                    <option value="Software Quality Assurance">Software Quality Assurance</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Subject Credits</label>
                <input type="number" name="subject_credits" class="form-control" min="1" max="5" required>
            </div>

            <div class="mb-3">
                <input type="checkbox" name="has_lab" id="has_lab" onclick="toggleLabCredits()">
                <label for="has_lab">Includes Lab</label>
            </div>

            <div class="mb-3" id="lab_credits_div" style="display: none;">
                <label class="form-label">Lab Credits</label>
                <input type="number" name="lab_credits" class="form-control" min="1" max="2">
            </div>

            <button type="submit" class="btn btn-primary">Add Subject</button>
        </form>
    </div>
</body>
</html>
