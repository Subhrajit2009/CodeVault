<?php
// email_helper.php
function sendCollaborationInvite($toEmail, $fromUser, $repoName, $inviteToken, $permission) {
    $subject = "Collaboration Invitation: $repoName";
    
    $acceptUrl = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/accept_invite.php?token=" . $inviteToken;
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #4361ee, #7209b7); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 12px 30px; background: #4361ee; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📨 CodeVault Invitation</h1>
                <p>You've been invited to collaborate!</p>
            </div>
            <div class='content'>
                <h2>Hello!</h2>
                <p><strong>$fromUser</strong> has invited you to collaborate on the repository <strong>$repoName</strong>.</p>
                
                <div style='background: white; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <p><strong>Repository:</strong> $repoName</p>
                    <p><strong>Permission Level:</strong> <span style='text-transform: capitalize;'>$permission</span></p>
                    <p><strong>Invited by:</strong> $fromUser</p>
                </div>
                
                <p>Click the button below to accept this invitation:</p>
                <div style='text-align: center; margin: 25px 0;'>
                    <a href='$acceptUrl' class='button' style='background: #4361ee;'>Accept Invitation</a>
                </div>
                
                <p style='font-size: 14px; color: #666;'>
                    Or copy and paste this link in your browser:<br>
                    <code style='background: #f1f3f4; padding: 5px 10px; border-radius: 3px;'>$acceptUrl</code>
                </p>
                
                <div class='footer'>
                    <p>This invitation will expire in 7 days.</p>
                    <p>If you didn't expect this invitation, you can safely ignore this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: CodeVault <noreply@codevault.com>" . "\r\n";
    $headers .= "Reply-To: noreply@codevault.com" . "\r\n";
    
    return mail($toEmail, $subject, $message, $headers);
}

function sendInviteAcceptedNotification($ownerEmail, $collaboratorName, $repoName) {
    $subject = "Invitation Accepted: $repoName";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4cc9f0; color: white; padding: 20px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Invitation Accepted ✅</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Great news! <strong>$collaboratorName</strong> has accepted your invitation to collaborate on <strong>$repoName</strong>.</p>
                <p>They now have access to the repository and can start collaborating with you.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: CodeVault <noreply@codevault.com>" . "\r\n";
    
    return mail($ownerEmail, $subject, $message, $headers);
}
?>