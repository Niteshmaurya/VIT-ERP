<?php
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    // Insert into users table
    $sql = "INSERT INTO users (email, password, role) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("sss", $email, $password, $role);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id; // Get last inserted user ID

        // Insert role-specific data
        if ($role == "admin") {
            $mobile_no = $_POST['mobile_no'];
            $dob = $_POST['dob'];
            $sql = "INSERT INTO admin (user_id, name, mobile_no, dob) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $user_id, $name, $mobile_no, $dob);
        } elseif ($role == "student") {
            $roll_no = $_POST['roll_no'];
            $branch = $_POST['branch'];
            $sql = "INSERT INTO students (user_id, name, roll_no, branch) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $user_id, $name, $roll_no, $branch);
        } elseif ($role == "exam_cell") {
            $mobile_no = $_POST['mobile_no'];
            $sql = "INSERT INTO exam_cell (user_id, name, mobile_no) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $user_id, $name, $mobile_no);
        }

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>User registered successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }

    $stmt->close();
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function toggleFields() {
            let role = document.getElementById("role").value;
            document.getElementById("admin_fields").style.display = (role === "admin") ? "block" : "none";
            document.getElementById("student_fields").style.display = (role === "student") ? "block" : "none";
            document.getElementById("exam_cell_fields").style.display = (role === "exam_cell") ? "block" : "none";
        }
    </script>
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg p-4">
                <h2 class="text-center">Register</h2>
                <?php echo $message; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="role" class="form-control" required onchange="toggleFields()">
                            <option value="admin">Admin</option>
                            <option value="student">Student</option>
                            <option value="exam_cell">Exam Cell</option>
                        </select>
                    </div>

                    <!-- Admin Fields -->
                    <div id="admin_fields" style="display: block;">
                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="mobile_no" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control">
                        </div>
                    </div>

                    <!-- Student Fields -->
                    <div id="student_fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Roll Number</label>
                            <input type="text" name="roll_no" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Branch</label>
                            <select name="branch" class="form-control">
                                <option value="CSE">Computer Science Engineering</option>
                                <option value="ECE">Electronics & Communication</option>
                                <option value="ME">Mechanical Engineering</option>
                                <option value="EE">Electrical Engineering</option>
                                <option value="CE">Civil Engineering</option>
                            </select>
                        </div>
                    </div>

                    <!-- Exam Cell Fields -->
                    <div id="exam_cell_fields" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" name="mobile_no" class="form-control">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Register</button>
                </form>
                <p class="text-center mt-3">Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
