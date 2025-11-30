<?php

$password_to_hash = 'your_password';

// Generate the password hash using the default algorithm (bcrypt as of PHP 5.5.0)
$hashed_password = password_hash($password_to_hash, PASSWORD_DEFAULT);

echo "Original string: " . htmlspecialchars($password_to_hash) . "<br>";
echo "Generated hash: " . htmlspecialchars($hashed_password) . "<br>";

// Example of how to verify the hash later (e.g., during login)
$input_password = 'solankidhruv394'; // The user input during login

if (password_verify($input_password, $hashed_password)) {
    echo "Password successfully verified. It matches the hash. <br>";
} else {
    echo "Password verification failed. It does not match the hash. <br>";
}

// Note: In a real application, you would store the $hashed_password in your database
// and use password_verify() to check user input against the stored hash.
?>
