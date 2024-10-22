<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Support Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .header {
            background-color: #007BFF;
            color: #fff;
            padding: 10px 0;
            text-align: center;
        }
        .message-content {
            margin-top: 20px;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Support Request</h1>
        </div>

        <div class="message-content">
            <p><strong>Message from:</strong> {{ $email }}</p>
            <p><strong>Message:</strong></p>
            <p>{{ $content }}</p>
        </div>

        <div class="footer">
            <p>This email was sent as a support request. Please respond accordingly.</p>
        </div>
    </div>
</body>
</html>
