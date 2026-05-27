<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

session_start();
include 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {

        echo json_encode([
            "status" => "error",
            "message" => "All fields are required."
        ]);

        exit;
    }

    $stmt = $conn->prepare("
        SELECT user_id, email, password, role, status
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $email);

    $stmt->execute();

    $stmt->store_result();

    if ($stmt->num_rows > 0) {

        $stmt->bind_result(
            $user_id,
            $db_email,
            $db_password,
            $role,
            $status
        );

        $stmt->fetch();

        if (password_verify($password, $db_password)) {

            if ($status !== 'Active') {

                echo json_encode([
                    "status" => "error",
                    "message" => "Account is not active."
                ]);

                exit;
            }

            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $db_email;
            $_SESSION['role'] = $role;

            echo json_encode([
                "status" => "success",
                "message" => "Login successful.",
                "role" => $role
            ]);

        } else {

            echo json_encode([
                "status" => "error",
                "message" => "Invalid password."
            ]);
        }

    } else {

        echo json_encode([
            "status" => "error",
            "message" => "Email not found."
        ]);
    }
}
?>