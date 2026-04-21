<?php
$pageTitle = 'Kontakt';
require_once 'includes/header.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($name && $email && $message && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $to = 'rcaffe@email.cz';
        $headers = "From: $name <$email>\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
        $body = "Jméno: $name\nEmail: $email\nPředmět: $subject\n\nZpráva:\n$message";

        if (mail($to, $subject ?: 'Kontaktní formulář - Ciao Spritz', $body, $headers)) {
            $success = true;
        } else {
            $error = t('Chyba při odesílání. Prosím kontaktujte nás telefonicky.', 'Error sending message. Please contact us by phone.');
        }
    } else {
        $error = t('Vyplňte prosím všechna povinná pole.', 'Please fill in all required fields.');
    }
}
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a>
            <span>›</span>
            <span><?= t('Kontakt', 'Contact') ?></span>
        </div>
        <h1><?= t('Kontaktujte <span class="accent">nás</span>', 'Contact <span class="accent">us</span>') ?></h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="kontakt-grid" class="contact-layout" style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:start">

            <!-- KONTAKTNÍ INFO -->
            <div>
                <h2 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:24px"><?= t('Jsme tu pro vás', 'We\'re here for you') ?></h2>
                <p style="color:var(--gray-dark);margin-bottom:40px;line-height:1.7">
                    <?= t('Máte otázky ohledně našich produktů, objednávky nebo zapůjčení stánku? Neváhejte nás kontaktovat.', 'Have questions about our products, order or stand rental? Don\'t hesitate to contact us.') ?>
                </p>

                <div class="contact-info-items" style="display:flex;flex-direction:column;gap:24px">
                    <div style="display:flex;align-items:center;gap:16px">
                        <div style="width:48px;height:48px;background:rgba(232,99,26,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">📞</div>
                        <div>
                            <div style="font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:0.05em"><?= t('Telefon', 'Phone') ?></div>
                            <a href="tel:602556323" style="font-size:1.1rem;font-weight:600;color:var(--orange)">602 556 323</a>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:16px">
                        <div style="width:48px;height:48px;background:rgba(232,99,26,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">✉️</div>
                        <div>
                            <div style="font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:0.05em">Email</div>
                            <a href="mailto:rcaffe@email.cz" style="font-size:1.1rem;font-weight:600;color:var(--orange)">rcaffe@email.cz</a>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:16px">
                        <div style="width:48px;height:48px;background:rgba(232,99,26,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">📘</div>
                        <div>
                            <div style="font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:0.05em">Facebook</div>
                            <a href="https://www.facebook.com/ciaospritz" target="_blank" style="font-size:1rem;font-weight:600;color:var(--orange)">facebook.com/ciaospritz</a>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:16px">
                        <div style="width:48px;height:48px;background:rgba(232,99,26,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">📸</div>
                        <div>
                            <div style="font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:0.05em">Instagram</div>
                            <a href="https://www.instagram.com/ciao_spritz/" target="_blank" style="font-size:1rem;font-weight:600;color:var(--orange)">@ciao_spritz</a>
                        </div>
                    </div>
                </div>

                <!-- MAPA -->
                <div class="contact-map" style="margin-top:40px;border-radius:var(--radius-lg);overflow:hidden;height:280px;background:var(--gray-light);display:flex;align-items:center;justify-content:center">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2654.9!2d15.8!3d50.2!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zNTDCsDEyJzAwLjAiTiAxNcKwNDgnMDAuMCJF!5e0!3m2!1scs!2scz!4v1"
                        width="100%"
                        height="280"
                        style="border:0"
                        allowfullscreen
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>

            <!-- FORMULÁŘ -->
            <div>
                <div style="background:var(--gray-light);border-radius:var(--radius-lg);padding:40px">
                    <h3 style="font-family:var(--font-display);font-size:1.4rem;margin-bottom:24px"><?= t('Napište nám', 'Write to us') ?></h3>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            ✅ <?= t('Zpráva odeslána! Ozveme se vám co nejdříve.', 'Message sent! We will get back to you as soon as possible.') ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error">⚠️ <?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                            <div class="form-group">
                                <label class="form-label"><?= t('Jméno *', 'Name *') ?></label>
                                <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= t('Předmět', 'Subject') ?></label>
                            <input type="text" name="subject" class="form-control" value="<?= e($_POST['subject'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= t('Zpráva *', 'Message *') ?></label>
                            <textarea name="message" class="form-control" rows="6" required><?= e($_POST['message'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%">
                            <?= t('Odeslat zprávu', 'Send message') ?> →
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
