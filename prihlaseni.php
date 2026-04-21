<?php
$pageTitle = 'Přihlášení';
require_once 'includes/header.php';

$lang = LANG;
$tab = $_GET['tab'] ?? 'login';
$errors = [];
$success = '';

// Přesměruj pokud je přihlášen
if (isset($_SESSION['user_id'])) {
    header('Location: '.BASE_URL.'/muj-ucet.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['login'])) {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email']= $user['email'];
            header('Location: '.BASE_URL.'/muj-ucet.php');
            exit;
        } else {
            $errors[] = t('Nesprávný email nebo heslo.', 'Incorrect email or password.');
            $tab = 'login';
        }
    }

    if (isset($_POST['register'])) {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2= $_POST['password2'] ?? '';

        if (!$name) $errors[] = t('Vyplňte jméno.', 'Enter your name.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('Neplatný email.', 'Invalid email.');
        if (strlen($password) < 6) $errors[] = t('Heslo musí mít alespoň 6 znaků.', 'Password must be at least 6 characters.');
        if ($password !== $password2) $errors[] = t('Hesla se neshodují.', 'Passwords do not match.');

        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = t('Tento email je již zaregistrován.', 'This email is already registered.');
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?,?,?)")->execute([$name, $email, $hash]);
                $success = t('Registrace proběhla úspěšně! Nyní se můžete přihlásit.', 'Registration successful! You can now log in.');
                $tab = 'login';
            }
        } else {
            $tab = 'register';
        }
    }
}
?>

<section class="section">
    <div class="container" style="max-width:480px">

        <div style="text-align:center;margin-bottom:40px">
            <h1 style="font-family:var(--font-display);font-size:2rem;font-weight:900;margin-bottom:8px">
                <?= t('Váš <span style="color:var(--orange);font-style:italic">účet</span>', 'Your <span style="color:var(--orange);font-style:italic">account</span>') ?>
            </h1>
            <p style="color:var(--gray)"><?= t('Přihlaste se nebo si vytvořte účet.', 'Sign in or create an account.') ?></p>
        </div>

        <!-- TABY -->
        <div style="display:grid;grid-template-columns:1fr 1fr;background:var(--gray-light);border-radius:var(--radius);padding:4px;margin-bottom:32px">
            <a href="?tab=login" style="text-align:center;padding:10px;border-radius:6px;font-weight:600;font-size:14px;text-decoration:none;transition:all 0.2s;<?= $tab === 'login' ? 'background:white;color:var(--black);box-shadow:0 2px 8px rgba(0,0,0,0.08)' : 'color:var(--gray)' ?>">
                <?= t('Přihlášení', 'Sign in') ?>
            </a>
            <a href="?tab=register" style="text-align:center;padding:10px;border-radius:6px;font-weight:600;font-size:14px;text-decoration:none;transition:all 0.2s;<?= $tab === 'register' ? 'background:white;color:var(--black);box-shadow:0 2px 8px rgba(0,0,0,0.08)' : 'color:var(--gray)' ?>">
                <?= t('Registrace', 'Register') ?>
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">⚠️ <?= implode('<br>', array_map('e', $errors)) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">✅ <?= e($success) ?></div>
        <?php endif; ?>

        <!-- PŘIHLÁŠENÍ -->
        <?php if ($tab === 'login'): ?>
        <form method="POST" style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px">
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= t('Heslo', 'Password') ?></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary" style="width:100%">
                <?= t('Přihlásit se', 'Sign in') ?>
            </button>
            <div style="text-align:center;margin-top:16px">
                <a href="?tab=register" style="font-size:14px;color:var(--gray)"><?= t('Nemáte účet? Zaregistrujte se', 'No account? Register') ?></a>
            </div>
        </form>

        <!-- REGISTRACE -->
        <?php else: ?>
        <form method="POST" style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px">
            <div class="form-group">
                <label class="form-label"><?= t('Jméno a příjmení *', 'Full name *') ?></label>
                <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= t('Heslo *', 'Password *') ?> <span style="font-size:12px;color:var(--gray)">(<?= t('min. 6 znaků', 'min. 6 chars') ?>)</span></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?= t('Heslo znovu *', 'Repeat password *') ?></label>
                <input type="password" name="password2" class="form-control" required>
            </div>
            <button type="submit" name="register" class="btn btn-primary" style="width:100%">
                <?= t('Vytvořit účet', 'Create account') ?>
            </button>
            <div style="text-align:center;margin-top:16px">
                <a href="?tab=login" style="font-size:14px;color:var(--gray)"><?= t('Máte účet? Přihlaste se', 'Have account? Sign in') ?></a>
            </div>
        </form>
        <?php endif; ?>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
