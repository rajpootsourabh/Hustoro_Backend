<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome Candidate</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #0d9488;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e0e0e0;
        }
        .password-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 6px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 18px;
            text-align: center;
            letter-spacing: 1px;
        }
        .button {
            display: inline-block;
            background-color: #0d9488;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            margin: 15px 0;
            font-weight: bold;
        }
        .warning {
            background-color: #f8f9fa;
            border-left: 4px solid #0d9488;
            padding: 15px;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Welcome to {{ $company_name }}</h1>
    </div>
    
    <div class="content">
        <p>Dear <strong>{{ $candidate_name }}</strong>,</p>
        
        <p>Thank you for applying for the position of <strong>{{ $job_title }}</strong> at {{ $company_name }}.</p>
        
        <p>We have created a candidate account for you where you can:</p>
        <ul>
            <li>Track your application status</li>
            <li>Complete required forms</li>
        </ul>
        
        <p><strong>Your login credentials:</strong></p>
        <div class="password-box">
            <strong>Email:</strong> {{ $email }}<br>
            <strong>Temporary Password:</strong> {{ $temp_password }}
        </div>
        
        <a href="{{ $login_url }}" class="button">Login to Your Account</a>
        
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p>{{ $login_url }}</p>
        
        <p>Best regards,<br>
        The {{ $company_name }} Hiring Team</p>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>&copy; {{ date('Y') }} {{ $company_name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>