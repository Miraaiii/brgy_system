<?php

include 'src/backend/connection.php';

$email = "admin@gmail.com";
$password = password_hash("admin123", PASSWORD_DEFAULT);

$sql = "UPDATE users SET password = '$password' WHERE email = '$email'";

if(mysqli_query($conn, $sql)){
    echo "Password updated successfully";
} else {
    echo "Error";
}
?>