<?php
require_once 'PHPMailer/PHPMailerAutoload.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';

    // Validate required fields
    if (empty($name) || empty($subject) || empty($email) || empty($message)) {
        echo "missing_fields";
        exit;
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "invalid_format";
        exit;
    }

    // Database connection
    $conn = mysqli_connect('localhost', 'root', '', 'sendmail');
    
    if (!$conn) {
        echo "db_error: " . mysqli_connect_error();
        exit;
    }

    // Store in database
    $insert_stmt = mysqli_prepare($conn, "INSERT INTO mails (name, subject, email, message) VALUES (?, ?, ?, ?)");
    if ($insert_stmt) {
        mysqli_stmt_bind_param($insert_stmt, "ssss", $name, $subject, $email, $message);
        if (!mysqli_stmt_execute($insert_stmt)) {
            echo "db_error: " . mysqli_stmt_error($insert_stmt);
            exit;
        }
        mysqli_stmt_close($insert_stmt);
    } else {
        echo "db_error: " . mysqli_error($conn);
        exit;
    }
    mysqli_close($conn);

    // Send email
    $mailResult = smtp_mailer($email, $name, $subject, $message);
    echo $mailResult;
    exit;
} else {
    echo "invalid_request";
    exit;
}

function smtp_mailer($fromEmail, $fromName, $subject, $msg) {
    $mail = new PHPMailer(); 
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $fromEmail;
        $mail->Password = 'jexo irlv vzxx ifvj';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        
        // Debugging
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = 'html';

        // Recipients
        $mail->setFrom($fromEmail);
        $mail->addAddress('dru.dino9@gmail.com'); // Destination email
        $mail->addReplyTo($fromEmail, $fromName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Help Request: $subject";
        
        // Email template
        $emailTemplate = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .email-container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4a90e2; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { padding: 20px; background: #f9f9f9; border: 1px solid #ddd; }
                .message-box { background: white; padding: 15px; border-radius: 5px; margin-top: 15px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 0.9em; }
                .info-label { color: #4a90e2; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h2>New Help Request - CodeVault</h2>
                </div>
                <div class='content'>
                    <p><span class='info-label'>From:</span> $fromName</p>
                    <p><span class='info-label'>Email:</span> $fromEmail</p>
                    <p><span class='info-label'>Subject:</span> $subject</p>
                    <div class='message-box'>
                        <p class='info-label'>Message:</p>
                        " . nl2br(htmlspecialchars($msg)) . "
                    </div>
                </div>
                <div class='footer'>
                    <p>This message was sent from CodeVault Help Center</p>
                    <p>© " . date('Y') . " CodeVault. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->Body = $emailTemplate;
        $mail->AltBody = "Name: $fromName\nEmail: $fromEmail\nSubject: $subject\nMessage: $msg";

        // Send email
        if($mail->send()) {
            return 'Sent';
        } else {
            return 'mail_error: ' . $mail->ErrorInfo;
        }
        
    } catch (Exception $e) {
        return 'mail_exception: ' . $e->getMessage();
    }
}
?>