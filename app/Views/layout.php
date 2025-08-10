<!DOCTYPE html>
<html>
<head>
    <title><?= esc($title) ?></title>
</head>
<body>
    <?= session('message') ? '<p style="color:green;">' . session('message') . '</p>' : '' ?>
    <?= session('error') ? '<p style="color:red;">' . session('error') . '</p>' : '' ?>
    <?= $content ?>
</body>
</html>