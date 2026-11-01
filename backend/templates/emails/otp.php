<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= e(brand('app.name')) ?> - Verification Code</title>
</head>
<body style="font-family: sans-serif; background: #f8faf9; padding: 20px;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 16px;">
        <h2 style="color: <?= e(brand('colors.primary.600', '#059669')) ?>;"><?= e(brand('app.name')) ?></h2>
        <p>Your one-time login verification code is:</p>
        <div style="font-size: 32px; font-weight: bold; letter-spacing: 4px; padding: 12px; background: #ecfdf5; color: #065f46; text-align: center; border-radius: 8px;">
            <?= e($otp ?? '000000') ?>
        </div>
        <p style="color: #64748b; font-size: 13px; margin-top: 20px;">This code is valid for 5 minutes. Do not share it with anyone.</p>
    </div>
</body>
</html>

