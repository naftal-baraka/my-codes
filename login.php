<?php 
// Include the database connection
require_once 'db.php';
session_start();

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = ""; // Default error message

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['username']) && !empty($_POST['password'])) {
        // Get the form inputs
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $password = $_POST['password']; // Don't escape passwords

        // Prepare SQL to fetch the user
        $sql = "SELECT user_id, username, password, role_id FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if the user exists
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Verify the password
            if (password_verify($password, $user['password'])) {
                // Store user details in session
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role_id'] = $user['role_id'];

                // ✅ Log the login event
                $log_action = "User '{$user['username']}' logged in";
                $sql_log = "INSERT INTO system_logs (user_id, action) VALUES (?, ?)";
                $stmt_log = $conn->prepare($sql_log);
                $stmt_log->bind_param("is", $user['user_id'], $log_action);
                $stmt_log->execute();
                $stmt_log->close();

                // Redirect to the dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "❌ Invalid password.";
                
                // ❌ Log the failed login attempt
                $log_action = "Failed login attempt for username '{$username}'";
                $sql_log = "INSERT INTO system_logs (user_id, action) VALUES (0, ?)";
                $stmt_log = $conn->prepare($sql_log);
                $stmt_log->bind_param("s", $log_action);
                $stmt_log->execute();
                $stmt_log->close();
            }
        } else {
            $error_message = "❌ Username not found.";
        }
        $stmt->close();
    } else {
        $error_message = "❌ Please enter both username and password.";
    }
}
?>

<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chain of Custody - Login</title>
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            background: url("images/logo.jpg") no-repeat center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        /* Login Container */
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
            text-align: center;
            width: 380px;
            animation: fadeIn 0.8s ease-in-out;
            transition: transform 0.3s;
        }

        .login-container:hover {
            transform: scale(1.02);
        }

        /* Logo */
        .logo {
            width: 100px;
            margin-bottom: 12px;
        }

        /* Input Fields */
        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            transition: border 0.3s;
        }

        input:focus {
            border: 1px solid #007bff;
            outline: none;
        }

        /* Login Button */
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to right, #007bff, #0056b3);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            font-size: 16px;
            font-weight: bold;
        }

        .login-btn:hover {
            background: linear-gradient(to right, #0056b3, #003d80);
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

        /* Error Message */
        .error-message {
            color: red;
            font-size: 14px;
            margin-top: 10px;
            font-weight: bold;
        }

        /* Login Actions */
        .login-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        .login-actions a {
            color: #007bff;
            text-decoration: none;
            font-size: 15px;
            font-weight: bold;
            transition: color 0.3s;
            cursor: pointer;
        }

        .login-actions a:hover {
            text-decoration: underline;
            color: #00ffcc;
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

    <div class="login-container">
        <img src="images/logo.jpg" alt="Chain of Custody Logo" class="logo">
        <h2>Login</h2>
        <form method="POST" action="login.php" id="loginForm">
            <input type="text" id="username" name="username" placeholder="Username" required>
            <input type="password" id="password" name="password" placeholder="Password" required>
            <button type="button" class="fingerprint-btn" id="authenticateFingerprint">Login with Fingerprint</button>
            <button type="submit" class="login-btn">Login</button>
            <p class="error-message"><?php echo isset($error_message) ? $error_message : ''; ?></p>
        </form>

        <div class="login-actions">
            <a id="forgot_password">Forgot Password?</a>
            <a id="create-account">Create Account</a>
        </div>
    </div>

    <script>
        // WebAuthn Fingerprint Authentication
        document.getElementById('authenticateFingerprint').addEventListener('click', async () => {
            try {
                const publicKeyCredentialRequestOptions = {
                    challenge: new Uint8Array(32),
                    allowCredentials: [],
                    userVerification: "required",
                    timeout: 60000,
                };

                const credential = await navigator.credentials.get({
                    publicKey: publicKeyCredentialRequestOptions,
                });

                // Convert the credential to a base64 string
                const credentialArray = new Uint8Array(credential.response.authenticatorData);
                const credentialBase64 = btoa(String.fromCharCode.apply(null, credentialArray));

                // Send the credential to the server for verification
                const response = await fetch('verify_fingerprint.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ credential: credentialBase64 }),
                });

                const result = await response.json();
                if (result.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    alert("Fingerprint authentication failed. Please try again.");
                }
            } catch (error) {
                console.error("Fingerprint authentication failed:", error);
                alert("Fingerprint authentication failed. Please try again.");
            }
        });

        document.getElementById('forgot_password').addEventListener('click', function() {
            window.location.href = 'forgot_password.php';
        });

        document.getElementById('create-account').addEventListener('click', function() {
            window.location.href = 'register.php';
        });
    </script>

</body>
</html>