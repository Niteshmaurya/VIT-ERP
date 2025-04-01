<?php
session_start();
ob_start();
include 'db.php';

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Query to check email in 'users' table
    $sql = "SELECT id, password, role, name FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("<div class='alert alert-danger'>Query failed: " . $conn->error . "</div>");
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $hashed_password, $role, $name);
        $stmt->fetch();

        // Verify password
        if (password_verify($password, $hashed_password)) {
            $_SESSION['user_id'] = $id;
            $_SESSION['user_role'] = $role;
            $_SESSION['name'] = $name; // Store user's name in the session

            // Redirect based on role
            switch ($role) {
                case 'exam_cell':
                    header("Location: exam_cell_dashboard.php");
                    break;
                case 'student':
                    header("Location: student_dashboard.php");
                    break;
                default:
                    header("Location: admin_dashboard.php");
            }
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Invalid password.</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>No user found with this email.</div>";
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
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg p-4">
                <h2 class="text-center">Login</h2>
                <?php echo $message; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
                <p class="text-center mt-3">Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
</div>

</body>
</html>
