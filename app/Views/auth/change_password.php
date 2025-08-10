<!DOCTYPE html>
<html>
<head>
    <title>Set New Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f0fdf4;">

    <div style="display: flex; justify-content: center; align-items: center; height: 100vh;">
        <div style="background-color: #e6ffed; padding: 30px; border: 1px solid #a3d9a5; box-shadow: 0 4px 10px rgba(0, 100, 0, 0.1); border-radius: 10px; width: 100%; max-width: 400px;">
            <h2 style="text-align: center; color: #2e7d32; margin-bottom: 20px;">Set Your New Password</h2>

            <?php if (session('errors')): ?>
                <div style="background-color: #ffe5e5; border: 1px solid #ff9e9e; padding: 10px 15px; border-radius: 5px; margin-bottom: 15px; color: #d32f2f;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach (session('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= site_url('change-password') ?>" method="post">
                <?= csrf_field() ?>

                <div style="margin-bottom: 15px;">
                    <label for="password" style="display: block; margin-bottom: 5px; color: #2e7d32;">New Password</label>
                    <input type="password" name="password" required
                           style="width: 100%; padding: 10px; border: 1px solid #a5d6a7; border-radius: 5px;">
                </div>

                <div style="margin-bottom: 20px;">
                    <label for="pass_confirm" style="display: block; margin-bottom: 5px; color: #2e7d32;">Confirm Password</label>
                    <input type="password" name="pass_confirm" required
                           style="width: 100%; padding: 10px; border: 1px solid #a5d6a7; border-radius: 5px;">
                </div>

                <button type="submit"
                        style="width: 100%; padding: 10px; background-color: #66bb6a; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
                    Change Password
                </button>
            </form>
        </div>
    </div>

</body>
</html>
