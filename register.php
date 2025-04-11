<?php
// Include the database connection
require_once 'db.php';

$successMessage = "";
$errorMessage = "";
$formVisible = true; // Controls form visibility

// Role mapping array (for better error messages)
$roleNames = [
    1 => "Investigator",
    2 => "Forensic Examiner",
    3 => "Lab Personnel",
    4 => "System Admin"
];

// Security questions array
$securityQuestions = [
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "What city were you born in?",
    "What is the name of your favorite teacher?",
    "What is your favorite movie?",
    "What do you love the most?"
];

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the form inputs
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $security_question = mysqli_real_escape_string($conn, $_POST['security_question']);
    $security_answer = mysqli_real_escape_string($conn, $_POST['security_answer']);
    $fingerprint_data = mysqli_real_escape_string($conn, $_POST['fingerprint_data']); // Fingerprint data

    // Check if the username already exists
    $checkUserSql = "SELECT role_id FROM users WHERE username = ?";
    $stmt = $conn->prepare($checkUserSql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($existingRole);
    
    if ($stmt->fetch()) {
        // User already exists, check if they are trying to register with a different role
        $registeredRoleName = isset($roleNames[$existingRole]) ? $roleNames[$existingRole] : "Unknown Role";
        $errorMessage = "❌ Error: You are already registered as a <strong>$registeredRoleName</strong>!";
    } else {
        // User does not exist, proceed with registration
        $stmt->close();
        
        // Hash the password and security answer before storing them
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_answer = password_hash($security_answer, PASSWORD_DEFAULT);

        // Insert the new user
        $insertSql = "INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insertSql);
        $stmt->bind_param("ssi", $username, $hashed_password, $role);

        if ($stmt->execute()) {
            $user_id = $stmt->insert_id; // Get the ID of the newly inserted user

            // Insert security question and answer
            $insertQuestionSql = "INSERT INTO security_questions (user_id, question, answer) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insertQuestionSql);
            $stmt->bind_param("iss", $user_id, $security_question, $hashed_answer);

            if ($stmt->execute()) {
                // Insert fingerprint data
                $insertFingerprintSql = "INSERT INTO fingerprints (user_id, fingerprint_data) VALUES (?, ?)";
                $stmt = $conn->prepare($insertFingerprintSql);
                $stmt->bind_param("is", $user_id, $fingerprint_data);

                if ($stmt->execute()) {
                    $successMessage = "✅ New user registered successfully! Redirecting to login...";
                    $formVisible = false; // Hide form
                    echo "<script>
                            setTimeout(function() {
                                window.location.href = 'login.php';
                            }, 3000);
                          </script>";
                } else {
                    $errorMessage = "❌ Error: Failed to save fingerprint data. Please try again.";
                }
            } else {
                $errorMessage = "❌ Error: Failed to save security question. Please try again.";
            }
        } else {
            $errorMessage = "❌ Error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chain of Custody - Register</title>
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #1e3c72, #2a5298);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            flex-direction: column;
        }

        /* Success & Error Messages */
        .success-message {
            color: #b3f0ff;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.8s ease-in-out;
        }

        .error-message {
            color: #ffb3b3;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.8s ease-in-out;
        }

        /* Registration Container */
        .register-container {
            background: #ffffff; /* Changed to white */
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
            text-align: center;
            width: 400px;
            animation: fadeIn 0.8s ease-in-out;
        }

        h2 {
            margin-bottom: 20px;
            color: #333; /* Changed to dark gray for better contrast */
        }

        /* Input Fields */
        input, select {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            transition: border 0.3s;
        }

        input:focus, select:focus {
            border: 1px solid #4ca1af;
            outline: none;
        }

        /* Register Button */
        .register-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #4ca1af, #2c3e50);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            font-size: 16px;
            font-weight: bold;
        }

        .register-btn:hover {
            background: linear-gradient(to right, #2c3e50, #1e3c72);
            transform: scale(1.05);
        }

        /* Fingerprint Button */
        .fingerprint-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #4ca1af, #2c3e50);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }

        .fingerprint-btn:hover {
            background: linear-gradient(to right, #2c3e50, #1e3c72);
            transform: scale(1.05);
        }

        /* Fade-in Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
</head>
<body>

    <!-- Display Messages -->
    <?php if (!empty($successMessage)) : ?>
        <p class="success-message"><?php echo $successMessage; ?></p>
    <?php endif; ?>

    <?php if (!empty($errorMessage)) : ?>
        <p class="error-message"><?php echo $errorMessage; ?></p>
    <?php endif; ?>

    <!-- Form Visible Only If No Success Message -->
    <?php if ($formVisible) : ?>
        <div class="register-container">
            <h2>Register New User</h2>
            <form method="POST" action="register.php" id="registerForm">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Enter username" required>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>

                <label for="role">Role:</label>
                <select id="role" name="role" required>
                    <option value="1">Investigator</option>
                    <option value="2">Forensic Examiner</option>
                    <option value="3">Lab Personnel</option>
                    <option value="4">System Admin</option>
                </select>

                <!-- Security Question Dropdown -->
                <label for="security_question">Security Question:</label>
                <select id="security_question" name="security_question" required>
                    <?php foreach ($securityQuestions as $question) : ?>
                        <option value="<?php echo $question; ?>"><?php echo $question; ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="security_answer">Answer:</label>
                <input type="text" id="security_answer" name="security_answer" placeholder="Enter your answer" required>

                <!-- Fingerprint Enrollment -->
                <button type="button" class="fingerprint-btn" id="enrollFingerprint">Enroll Fingerprint</button>
                <input type="hidden" id="fingerprint_data" name="fingerprint_data">

                <button type="submit" class="register-btn">Register</button>
            </form>
        </div>
    <?php endif; ?>

    <script>
        // WebAuthn Fingerprint Enrollment
        document.getElementById('enrollFingerprint').addEventListener('click', async () => {
            try {
                const publicKeyCredentialCreationOptions = {
                    challenge: new Uint8Array(32),
                    rp: {
                        name: "Chain of Custody",
                    },
                    user: {
                        id: new Uint8Array(16),
                        name: document.getElementById('username').value,
                        displayName: document.getElementById('username').value,
                    },
                    pubKeyCredParams: [
                        {
                            type: "public-key",
                            alg: -7, // ES256
                        },
                    ],
                    authenticatorSelection: {
                        authenticatorAttachment: "platform",
                        userVerification: "required",
                    },
                    timeout: 60000,
                    attestation: "direct",
                };

                const credential = await navigator.credentials.create({
                    publicKey: publicKeyCredentialCreationOptions,
                });

                // Convert the credential to a base64 string
                const credentialArray = new Uint8Array(credential.response.attestationObject);
                const credentialBase64 = btoa(String.fromCharCode.apply(null, credentialArray));

                // Store the credential in the hidden input field
                document.getElementById('fingerprint_data').value = credentialBase64;
                alert("Fingerprint enrolled successfully!");
            } catch (error) {
                console.error("Fingerprint enrollment failed:", error);
                alert("Fingerprint enrollment failed. Please try again.");
            }
        });
    </script>
</body>
</html>