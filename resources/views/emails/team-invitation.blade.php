<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #1a1a2e; color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
        .btn { display: inline-block; background: #4f46e5; color: white; padding: 12px 28px;
               text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; }
        .note { font-size: 13px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header"><h2 style="margin:0">Nitriansky technologický inkubátor</h2></div>
    <div class="content">
        <h3>Dostali ste pozvánku do tímu!</h3>
        <p><strong>{{ $leaderName }}</strong> vás pozýva do tímu <strong>{{ $teamName }}</strong>.</p>

        @if($hasAccount)
            <p>Prihláste sa a kliknite nižšie pre prijatie:</p>
        @else
            <p>Zaregistrujte sa na NTI portáli — pozvánka sa automaticky prepojí s vaším účtom.</p>
        @endif

        <a href="{{ $acceptUrl }}" class="btn">Prijať pozvánku</a>

        <p class="note">Odkaz platí 7 dní. Ak ste túto pozvánku nečakali, ignorujte tento e-mail.</p>
    </div>
</div>
</body>
</html>