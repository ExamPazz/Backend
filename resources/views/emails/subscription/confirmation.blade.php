<!DOCTYPE html>
<html>
<head>
    <title>Subscription Confirmation</title>
</head>
<body>
    <h1>Thank you for subscribing!</h1>

    <p>Dear {{ $user->name }},</p>

    <p>Your subscription has been successfully activated.</p>

    <h2>Subscription Details:</h2>
    <ul>
        <li>Plan: {{ $planname }}</li>
        <li>Start Date: {{ $subscription->created_at->format('d M Y') }}</li>
        <li>You have {{ $subscription->allowed_number_attempts }} attempts to pass the mock exams</li>
    </ul>

    <p>Thank you for choosing our service!</p>

    <p>Best regards,<br>
    {{ config('app.name') }}</p>
</body>
</html>
