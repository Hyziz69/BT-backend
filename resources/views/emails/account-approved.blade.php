<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account approved</title>
</head>
<body>
    <h2>Your account was approved</h2>

    <p>Hello {{ $user->first_name }},</p>

    <p>Your registration request has been approved. You can now sign in using the account you created.</p>

    <p>Thank you.</p>
</body>
</html>