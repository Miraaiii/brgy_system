<?php
session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fullname = trim($_POST['fullname']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Validation
    if (empty($fullname) || empty($email) || empty($password)) {
        die("All fields are required.");
    }

    // Check if email already exists
    $check = "SELECT * FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check);

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Email already exists."
        ]);
        exit();
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $role = "Resident";

    // Insert user
    $sql = "INSERT INTO users (fullname, email, password, role)
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssss",
        $fullname,
        $email,
        $hashedPassword,
        $role
    );

    if (mysqli_stmt_execute($stmt)) {

        $_SESSION['success'] = "Account created successfully.";

        echo json_encode([
            "status" => "success",
            "message" => "Account created successfully"
        ]);
        exit();

    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Something went wrong."
        ]);
        exit();
    }
}
?>