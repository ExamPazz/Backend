<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New Contact Form Submission</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        .header {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .content {
            background: white;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .field {
            margin-bottom: 15px;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>New Contact Form Submission</h2>
            <p>Received on:{!!$submitted_at !!}</p>
        </div>

        <div class="content">
            <div class="field">
                <div class="label">Name:</div>
                <div> {!! $name !!}</div>
            </div>

            <div class="field">
                <div class="label">Email:</div>
                <div> {!! $email !!}</div>
            </div>

            <div class="field">
                <div class="label">Phone Number:</div>
                <div>{!! $phone_number !!}</div>
            </div>

            <div class="field">
                <div class="label">Message:</div>
                <div>{!! $content !!}</div>
            </div>
        </div>
    </div>
</body>
</html>
