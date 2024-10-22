<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access Alert</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #d9534f;
        }
        p {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Unauthorized Access Attempt</h2>
        <p>An unauthorized user tried to access admin information.</p>
        <p><strong>IP Address:</strong> {{ $ip ?? 'not found' }}</p>
        <p><strong>User Email:</strong> {{ $email ?? 'not provided' }}</p>
        <p>Please investigate this issue promptly.</p>
    </div>
</body>
</html>
