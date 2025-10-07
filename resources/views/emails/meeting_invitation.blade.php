<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interview Scheduled</title>
    <style>
        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f5f5f5;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .email-header {
            background: linear-gradient(135deg, #0f766e 0%, #044f49ff 100%);
            padding: 30px 20px;
            text-align: center;
        }

        .logo {
            max-width: 180px;
            height: auto;
        }

        .email-body {
            padding: 30px;
        }

        h3 {
            color: #0f766e;
            margin-top: 0;
            font-size: 24px;
            font-weight: 600;
        }

        p {
            margin-bottom: 16px;
            font-size: 16px;
        }

        .interview-details {
            background-color: #f8f9fa;
            border-left: 4px solid #0f766e;
            padding: 16px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }

        .meeting-link {
            display: inline-block;
            background-color: #0f766e;
            color: white !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 4px;
            font-weight: 500;
            margin: 10px 0;
        }

        .password-note {
            background-color: #fff9e6;
            border: 1px solid #ffd166;
            padding: 12px 16px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .email-footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }

        .footer-links {
            margin-top: 10px;
        }

        .footer-links a {
            color: #6c757d;
            text-decoration: none;
            margin: 0 10px;
        }

        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 20px;
            }

            .email-header {
                padding: 20px 15px;
            }
        }
    </style>
</head>

<body>
    <div class="email-container">
        <!-- Header with Logo -->
        <div class="email-header">
            <img src="https://res.cloudinary.com/dwi5dlj62/image/upload/v1756741894/hustoro_logo_white_dxorts.png" alt="{{ $companyName ?? 'Company' }} Logo" class="logo">
        </div>

        <!-- Email Body -->
        <div class="email-body">
            <h3>Interview Scheduled</h3>

            <p>Dear {{ $candidateName ?? 'Candidate' }},</p>

            <p>Thank you for your interest in joining our team. We're pleased to invite you for an interview.</p>

            <div class="interview-details">
                <p><strong>Interview Date & Time:</strong><br>
                    {{ $scheduledAt }}
                </p>
            </div>

            <!-- <p><strong>Meeting Details:</strong></p> -->

            <div style="text-align: center; margin: 25px 0;">
                <a href="{{ $meetingUrl }}" class="meeting-link">Join Meeting</a>
            </div>

            <!-- <div class="password-note">
                <p><strong>Important:</strong> You will be prompted to enter a password when joining the Jitsi meeting. Please check your calendar invitation or a separate email for the meeting password.</p>
            </div>
             -->
            <p>Please ensure you have a stable internet connection and join the meeting 5 minutes before the scheduled time.</p>

            <p>We look forward to speaking with you!</p>

            <p>Best regards,<br>
                {{ $companyName ?? 'Company' }} Team
            </p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>&copy; {{ date('Y') }} {{ $companyName ?? 'Company' }}. All rights reserved.</p>
            <div class="footer-links">
                <a href="#">Privacy Policy</a> |
                <a href="#">Terms of Service</a> |
                <a href="#">Contact Us</a>
            </div>
        </div>
    </div>
</body>

</html>