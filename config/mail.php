<?php
// Email Configuration
define('SMTP_HOST', 'rhu12.unified-servers.com');  // Secure SMTP server
define('SMTP_PORT', 465);                          // Secure SMTP port
define('SMTP_USERNAME', 'grade_inquiry@seait-edu.ph');  // SEAIT email
define('SMTP_PASSWORD', '020894GradeInquiry');  // Email password
define('SMTP_FROM_EMAIL', 'grade_inquiry@seait-edu.ph');  // SEAIT email
define('SMTP_FROM_NAME', 'SEAIT Grade Inquiry System');

// Function to send email
function sendResetEmail($to_email, $reset_link) {
    require 'vendor/autoload.php';  // Make sure PHPMailer is installed

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;  // Use SMTPS for port 465
        $mail->Port = SMTP_PORT;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - SEAIT Grade Inquiry System';
        
        // Email body
        $mail->Body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4e73df; color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f8f9fc; }
                    .button { 
                        display: inline-block;
                        padding: 10px 20px;
                        background: #4e73df;
                        color: white;
                        text-decoration: none;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Password Reset Request</h2>
                    </div>
                    <div class='content'>
                        <p>Hello,</p>
                        <p>We received a request to reset your password for the SEAIT Grade Inquiry System. If you didn't make this request, you can safely ignore this email.</p>
                        <p>To reset your password, click the button below:</p>
                        <p style='text-align: center;'>
                            <a href='{$reset_link}' class='button'>Reset Password</a>
                        </p>
                        <p>This link will expire in 1 hour for security reasons.</p>
                        <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                        <p style='word-break: break-all;'>{$reset_link}</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message, please do not reply to this email.</p>
                        <p>&copy; " . date('Y') . " SEAIT Grade Inquiry System. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "
            Password Reset Request
            
            We received a request to reset your password for the SEAIT Grade Inquiry System. If you didn't make this request, you can safely ignore this email.
            
            To reset your password, visit this link:
            {$reset_link}
            
            This link will expire in 1 hour for security reasons.
            
            This is an automated message, please do not reply to this email.
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
} 