<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.6;
            background: #f3f4f6;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 620px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #111827;
            color: white;
            padding: 22px;
            border-radius: 10px 10px 0 0;
        }

        .header h2 {
            margin: 0;
        }

        .content {
            background: #ffffff;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }

        .btn {
            display: inline-block;
            background: #111827;
            color: white !important;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 8px;
            margin: 18px 8px 18px 0;
            font-weight: bold;
        }

        .btn.reject {
            background: #dc2626;
        }

        .note {
            font-size: 13px;
            color: #666;
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Nitriansky technologický inkubátor</h2>
    </div>

    <div class="content">
        <h3>Pozvánka do spoločnosti</h3>

        <p>
            <strong>{{ $inviterName }}</strong> vás pozýva, aby ste sa pripojili k spoločnosti
            <strong>{{ $companyName }}</strong> ako <strong>{{ $roleLabel }}</strong>.
        </p>

        @if($hasAccount)
            <p>Prihláste sa a vyberte jednu z možností:</p>
        @else
            <p>
                Zaregistrujte sa na NTI portáli ako zástupca spoločnosti.
                Pozvánka sa automaticky prepojí s vaším účtom.
            </p>
        @endif

        <a href="{{ $acceptUrl }}" class="btn">Prijať pozvánku</a>
        <a href="{{ $rejectUrl }}" class="btn reject">Odmietnuť pozvánku</a>

        <p class="note">
            Odkaz platí 7 dní. Ak ste túto pozvánku nečakali, môžete ju odmietnuť alebo ignorovať.
        </p>
    </div>
</div>
</body>
</html>