<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New account approval request</title>
</head>
<body>
    <h2>New account approval request</h2>

    <p>A new user has registered and is waiting for approval:</p>

    <ul>
        <li>Name: {{ $user->first_name }} {{ $user->last_name }}</li>
        <li>Email: {{ $user->email }}</li>
        <li>Account type: {{ $user->account_type }}</li>
    </ul>

    <p>Please review this request in the admin panel.</p>
</body>
</html>