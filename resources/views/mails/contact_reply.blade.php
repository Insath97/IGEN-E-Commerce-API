<!DOCTYPE html>
<html>
<head>
    <title>Reply to your Inquiry</title>
</head>
<body>
    <p>Dear {{ $name }},</p>
    
    <p>Thank you for contacting us. Regarding your inquiry:</p>
    
    <blockquote style="border-left: 2px solid #ccc; padding-left: 10px; color: #666;">
        {{ $originalMessage }}
    </blockquote>
    
    <p><strong>Our Response:</strong></p>
    <p>{{ $replyMessage }}</p>
    
    <p>Best regards,<br>
    {{ config('app.name') }} Team</p>
</body>
</html>
