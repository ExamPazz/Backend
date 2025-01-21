<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f6f6f6;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .content {
            padding: 20px 0;
        }
        .verification-code {
            text-align: center;
            font-size: 32px;
            letter-spacing: 5px;
            color: #333;
            margin: 20px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            padding-top: 20px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #eee;
        }
        .note {
            color: #666;
            font-size: 14px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Email Verification</h1>
        </div>

        <div class="content">
            <p>Hello!</p>

            <p>Thank you for registering. To verify your email address, please use the following verification code:</p>

            <div class="verification-code">
                {{ $code }}
            </div>

            <p>This code will expire in 20 minutes.</p>

            <div class="note">
                <p>If you didn't request this verification code, please ignore this email.</p>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
