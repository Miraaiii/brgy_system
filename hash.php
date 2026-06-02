<?php
// hash.php

$password = "1234"; // Palitan ng password na gusto mong i-hash

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "Original Password: " . $password . "<br>";
echo "Hashed Password: " . $hashedPassword;
?>