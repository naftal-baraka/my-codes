<?php
session_start();
require_once 'db.php';

$error = '';
$success = '';

// Rate limiting configuration
$maxAttempts = 3; // Maximum allowed attempts
$timeWindow = 60; // Time window in seconds (e.g., 1 minute)
$blockDuration = 60; // Block duration in seconds (e.g., 1 minute)

// Predefined security questions
$securityQuestions = [
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "What city were you born in?",
    "What is the name of your favorite teacher?",
    "What is your favorite movie?",
    "What do you love the most?"
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Initialize rate limiting variables in session
    if (!isset($_SESSION['attempts'])) {
        $_SESSION['attempts'] = 0;
        $_SESSION['last_attempt_time'] = time();
    }

    // Check if the user is blocked
    if (isset($_SESSION['blocked_until']) && time() < $_SESSION['blocked_until']) {
        $error = "Too many attempts. Please try again after " . ($_SESSION['blocked_until'] - time()) . " seconds.";
    } else {
        // Reset block if the block duration has passed
        if (isset($_SESSION['blocked_until']) && time() >= $_SESSION['blocked_until']) {
            unset($_SESSION['blocked_until']);
            $_SESSION['attempts'] = 0;
            $_SESSION['last_attempt_time'] = time();
        }

        // Step 1: Validate Username
        if (empty($_SESSION['reset_user_id'])) {
            if (isset($_POST['username']) && !empty($_POST['username'])) {
                $username = trim($_POST['username']);

                // Check if the username exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $user_id = $user['user_id'];

                    // Fetch the security question for the user
                    $stmt = $conn->prepare("SELECT question FROM security_questions WHERE user_id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $question = $result->fetch_assoc();

                    if ($question) {
                        $_SESSION['reset_user_id'] = $user_id;
                        $_SESSION['security_question'] = $question['question'];
                    } else {
                        $error = "No security question found for this user.";
                    }
                } else {
                    $error = "Username not found.";
                }
            }
        }

        // Step 2: Validate Security Question
        if (empty($error) && !isset($_SESSION['question_verified'])) {
            if (isset($_POST['answer']) && !empty($_POST['answer'])) {
                $user_answer = trim($_POST['answer']);

                if (!empty($_SESSION['reset_user_id']) && !empty($_SESSION['security_question'])) {
                    $user_id = $_SESSION['reset_user_id'];
                    $security_question = $_SESSION['security_question'];

                    // Fetch the stored answer for the security question
                    $stmt = $conn->prepare("SELECT answer FROM security_questions WHERE user_id = ? AND question = ?");
                    $stmt->bind_param("is", $user_id, $security_question);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $stored_answer = $result->fetch_assoc()['answer'];

                    // Verify the answer
                    if (password_verify($user_answer, $stored_answer)) {
                        $_SESSION['question_verified'] = true;
                    } else {
                        $error = "Incorrect answer. Please try again.";
                        // Increment attempts
                        $_SESSION['attempts']++;
                        $_SESSION['last_attempt_time'] = time();

                        // Block the user if they exceed the maximum attempts
                        if ($_SESSION['attempts'] >= $maxAttempts) {
                            $_SESSION['blocked_until'] = time() + $blockDuration;
                            $error = "Too many attempts. Please try again after $blockDuration seconds.";
                        }
                    }
                } else {
                    $error = "Session expired. Please try again.";
                }
            }
        }

        // Step 3: Reset Password
        if (empty($error) && isset($_SESSION['question_verified']) && $_SESSION['question_verified']) {
            if (isset($_POST['new_password']) && !empty($_POST['new_password'])) {
                $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $user_id = $_SESSION['reset_user_id'];

                // Update the password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param("si", $new_password, $user_id);
                $stmt->execute();

                $success = "Password reset successfully. Redirecting to login...";
                echo "<script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 3000);
                      </script>";
                session_destroy();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style>
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

        .container {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
            text-align: center;
            width: 400px;
            animation: fadeIn 0.8s ease-in-out;
        }

        h2 {
            margin-bottom: 20px;
            color: #333;
        }

        input, select, button {
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

        button {
            background: linear-gradient(to right, #4ca1af, #2c3e50);
            color: white;
            border: none;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
        }

        button:hover {
            background: linear-gradient(to right, #2c3e50, #1e3c72);
            transform: scale(1.05);
        }

        .success-message {
            color: #4CAF50;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .error-message {
            color: #FF0000;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
        }

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
    <?php if (!empty($success)) : ?>
        <p class="success-message"><?php echo $success; ?></p>
    <?php endif; ?>

    <?php if (!empty($error)) : ?>
        <p class="error-message"><?php echo $error; ?></p>
    <?php endif; ?>

    <!-- Forgot Password Form -->
    <?php if (empty($success) || $success !== "Password reset successfully. Redirecting to login...") : ?>
        <div class="container">
            <h2>Forgot Password</h2>
            <form method="POST" action="forgot_password.php">
                <!-- Username Field -->
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required>

                <!-- Security Question Dropdown -->
                <label for="security_question">Security Question:</label>
                <select id="security_question" name="security_question" required>
                    <?php foreach ($securityQuestions as $question) : ?>
                        <option value="<?php echo $question; ?>"><?php echo $question; ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Answer Field -->
                <label for="answer">Your Answer:</label>
                <input type="text" id="answer" name="answer" placeholder="Enter your answer" required>

                <!-- New Password Field -->
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" placeholder="Enter a new password" required>

                <!-- Submit Button -->
                <button type="submit">Reset Password</button>
            </form>
        </div>
    <?php endif; ?>

</body>
</html>