<!-- resources/views/emails/otp.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your OTP Code</title>
    <style>
        /* General Styles */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        /* Container */
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border: 1px solid #e4e4e4;
            border-radius: 8px;
        }

        /* Header */
        .email-header {
            text-align: center;
            padding: 10px 0;
            background-color: #007bff;
            color: #ffffff;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        /* Body */
        .email-body {
            margin: 20px 0;
            line-height: 1.6;
            color: #333333;
        }

        .otp-code {
            display: block;
            width: fit-content;
            margin: 20px auto;
            padding: 10px 20px;
            font-size: 24px;
            font-weight: bold;
            background-color: #f8f9fa;
            border-radius: 5px;
            color: #007bff;
            border: 1px solid #007bff;
        }

        /* Footer */
        .email-footer {
            text-align: center;
            margin-top: 20px;
            color: #888888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>Your OTP Code</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>Hello{{ isset($data['name']) ? ', ' . $data['name'] : '' }},</p>
            <p>Please use the following OTP code to proceed:</p>
            <div class="otp-code">{{ $data['otp'] }}</div>
            <p>This code is valid for 1 minute. Please do not share it with anyone.</p>
            <p>If you did not request this OTP, please ignore this email.</p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>Thank you for using our service!</p>
            <p>&copy; {{ date('Y') }} Your Company Name. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
