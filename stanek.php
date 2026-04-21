<?php
$pageTitle = 'Zapůjčení mobilního stánku';
require_once 'includes/header.php';

$lang = LANG;
$success = false;
$errors = [];

// Načti obsazené termíny
$stmt = $pdo->query("SELECT date_from, date_to, status FROM reservations WHERE status IN ('ceka','schvaleno')");
$reservations = $stmt->fetchAll();
$bookedDates = [];
foreach ($reservations as $r) {
    $start = new DateTime($r['date_from']);
    $end   = new DateTime($r['date_to']);
    $end->modify('+1 day');
    $interval = new DateInterval('P1D');
    $range = new DatePeriod($start, $interval, $end);
    foreach ($range as $date) {
        $bookedDates[] = $date->format('Y-m-d');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $date_from = trim($_POST['date_from'] ?? '');
    $date_to   = trim($_POST['date_to'] ?? '');
    $location  = trim($_POST['location'] ?? '');
    $event_type= trim($_POST['event_type'] ?? '');
    $message   = trim($_POST['message'] ?? '');

    if (!$name) $errors[] = t('Vyplňte jméno.', 'Enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('Neplatný email.', 'Invalid email.');
    if (!$phone) $errors[] = t('Vyplňte telefon.', 'Enter phone number.');
    if (!$date_from || !$date_to) $errors[] = t('Vyberte termín.', 'Select dates.');
    if ($date_from && $date_to && $date_from > $date_to) $errors[] = t('Datum od musí být před datem do.', 'Start date must be before end date.');

    if (empty($errors)) {
        $pdo->prepare("INSERT INTO reservations (name, email, phone, date_from, date_to, location, event_type, message, status) VALUES (?,?,?,?,?,?,?,?,'ceka')")
            ->execute([$name, $email, $phone, $date_from, $date_to, $location, $event_type, $message]);

        // Email adminovi
        $body = "Nová poptávka zapůjčení stánku!\n\nJméno: $name\nEmail: $email\nTelefon: $phone\nOd: $date_from\nDo: $date_to\nMísto: $location\nTyp akce: $event_type\nZpráva: $message";
        mail('rcaffe@email.cz', 'Nová rezervace stánku - Ciao Spritz', $body, "From: web@ciaospritz.cz\r\nContent-Type: text/plain; charset=UTF-8");

        // Email zákazníkovi
        $bodyCustomer = t(
            "Dobrý den $name,\n\nDěkujeme za váš zájem o zapůjčení stánku Ciao Spritz!\n\nVaše poptávka byla přijata a my vás budeme kontaktovat co nejdříve.\n\nTermín: $date_from – $date_to\nMísto: $location\n\nS pozdravem,\nCiao Spritz tým\nrcaffe@email.cz | 602 556 323",
            "Dear $name,\n\nThank you for your interest in renting the Ciao Spritz stand!\n\nYour enquiry has been received and we will contact you as soon as possible.\n\nDates: $date_from – $date_to\nLocation: $location\n\nBest regards,\nCiao Spritz team\nrcaffe@email.cz | 602 556 323"
        );
        mail($email, t('Potvrzení poptávky - Ciao Spritz', 'Enquiry confirmation - Ciao Spritz'), $bodyCustomer, "From: Ciao Spritz <rcaffe@email.cz>\r\nContent-Type: text/plain; charset=UTF-8");

        $success = true;
    }
}
?>

<section class="page-hero">
    <div class="container">
        <div class="breadcrumb">
            <a href="<?= BASE_URL ?>/"><?= t('Domů', 'Home') ?></a> <span>›</span>
            <span><?= t('Zapůjčení stánku', 'Stand rental') ?></span>
        </div>
        <h1><?= t('Zapůjčení mobilního <span class="accent">stánku</span>', 'Mobile <span class="accent">stand</span> rental') ?></h1>
    </div>
</section>

<!-- INFO SEKCE -->
<section class="section section-gray">
    <div class="container">
        <div class="stanek-layout" style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center">
            <div>
                <span class="section-label"><?= t('Naše služba', 'Our service') ?></span>
                <h2 style="font-family:var(--font-display);font-size:clamp(1.6rem,3vw,2.2rem);font-weight:900;margin:12px 0 20px;line-height:1.2">
                    <?= t('Přivezeme Italsko <span style="color:var(--orange);font-style:italic">přímo k vám</span>', 'We bring Italy <span style="color:var(--orange);font-style:italic">right to you</span>') ?>
                </h2>
                <p style="color:var(--gray-dark);line-height:1.8;margin-bottom:24px">
                    <?= t(
                        'Chystáte festival, firemní akci, soukromou párty nebo svatbu? Zapůjčíme vám náš stylový mobilní stánek Ciao Spritz kompletně vybavený. Postaráme se o to, aby vaši hosté měli ten nejlepší zážitek.',
                        'Planning a festival, corporate event, private party or wedding? Rent our stylish fully-equipped Ciao Spritz mobile stand. We will make sure your guests have the best experience.'
                    ) ?>
                </p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <?php
                    $features = [
                        ['🎪', t('Festivaly', 'Festivals'), t('Hudební i kulturní', 'Music & cultural')],
                        ['🏢', t('Firemní akce', 'Corporate events'), t('Teambuilding, večírky', 'Teambuilding, parties')],
                        ['💒', t('Svatby', 'Weddings'), t('Nezapomenutelný den', 'Unforgettable day')],
                        ['🎉', t('Soukromé párty', 'Private parties'), t('Narozeniny a oslavy', 'Birthdays & celebrations')],
                    ];
                    foreach ($features as $f): ?>
                    <div style="background:white;border-radius:var(--radius);padding:16px;display:flex;gap:12px;align-items:flex-start;border:1px solid var(--border)">
                        <span style="font-size:1.5rem"><?= $f[0] ?></span>
                        <div>
                            <div style="font-weight:600;font-size:14px"><?= $f[1] ?></div>
                            <div style="font-size:12px;color:var(--gray)"><?= $f[2] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="background:white;border-radius:var(--radius-lg);aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;font-size:6rem;border:1px solid var(--border)">
                🍊
            </div>
        </div>
    </div>
</section>

<!-- FORMULÁŘ + KALENDÁŘ -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <span class="section-label"><?= t('Rezervace', 'Booking') ?></span>
            <h2 class="section-title"><?= t('Poptejte <span class="accent">termín</span>', 'Request a <span class="accent">date</span>') ?></h2>
            <p class="section-desc"><?= t('Vyberte termín v kalendáři a vyplňte formulář. Ozveme se vám do 24 hodin.', 'Select a date in the calendar and fill in the form. We will get back to you within 24 hours.') ?></p>
        </div>

        <?php if ($success): ?>
        <div style="text-align:center;padding:64px 0">
            <div style="width:80px;height:80px;background:rgba(45,122,58,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 24px">✅</div>
            <h2 style="font-family:var(--font-display);font-size:1.8rem;margin-bottom:12px"><?= t('Poptávka odeslána!', 'Enquiry sent!') ?></h2>
            <p style="color:var(--gray-dark);margin-bottom:32px"><?= t('Děkujeme! Ozveme se vám do 24 hodin na váš email.', 'Thank you! We will contact you within 24 hours at your email.') ?></p>
            <a href="<?= BASE_URL ?>/stanek.php" class="btn btn-secondary"><?= t('Zpět', 'Back') ?></a>
        </div>
        <?php else: ?>

        <div class="stanek-form-grid" class="stanek-layout" style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start">

            <!-- KALENDÁŘ -->
            <div>
                <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:20px"><?= t('Dostupnost termínů', 'Date availability') ?></h3>

                <div style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px">
                    <!-- Legenda -->
                    <div style="display:flex;gap:20px;margin-bottom:20px;font-size:13px">
                        <div style="display:flex;align-items:center;gap:6px"><span style="width:16px;height:16px;background:#e8f5e9;border:1px solid #4caf50;border-radius:3px;display:inline-block"></span><?= t('Volný', 'Available') ?></div>
                        <div style="display:flex;align-items:center;gap:6px"><span style="width:16px;height:16px;background:#ffebee;border:1px solid #ef5350;border-radius:3px;display:inline-block"></span><?= t('Obsazený', 'Booked') ?></div>
                        <div style="display:flex;align-items:center;gap:6px"><span style="width:16px;height:16px;background:rgba(232,99,26,0.15);border:2px solid var(--orange);border-radius:3px;display:inline-block"></span><?= t('Vybraný', 'Selected') ?></div>
                    </div>

                    <div id="calendar"></div>
                </div>
            </div>

            <!-- FORMULÁŘ -->
            <div>
                <h3 style="font-family:var(--font-display);font-size:1.2rem;margin-bottom:20px"><?= t('Vaše poptávka', 'Your enquiry') ?></h3>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">⚠️ <?= implode('<br>', array_map('e', $errors)) ?></div>
                <?php endif; ?>

                <form method="POST" style="background:white;border:1px solid var(--border);border-radius:var(--radius-lg);padding:32px">
                    <div class="form-group">
                        <label class="form-label"><?= t('Jméno a příjmení *', 'Full name *') ?></label>
                        <input type="text" name="name" class="form-control" required value="<?= e($_POST['name'] ?? '') ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= t('Telefon *', 'Phone *') ?></label>
                            <input type="tel" name="phone" class="form-control" required value="<?= e($_POST['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                        <div class="form-group">
                            <label class="form-label"><?= t('Datum od *', 'Date from *') ?></label>
                            <input type="date" name="date_from" id="date_from" class="form-control" required value="<?= e($_POST['date_from'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label"><?= t('Datum do *', 'Date to *') ?></label>
                            <input type="date" name="date_to" id="date_to" class="form-control" required value="<?= e($_POST['date_to'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= t('Místo konání', 'Event location') ?></label>
                        <input type="text" name="location" class="form-control" placeholder="<?= t('Město, adresa...', 'City, address...') ?>" value="<?= e($_POST['location'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= t('Typ akce', 'Event type') ?></label>
                        <select name="event_type" class="form-control">
                            <option value=""><?= t('Vyberte...', 'Select...') ?></option>
                            <option value="festival" <?= ($_POST['event_type']??'') === 'festival' ? 'selected' : '' ?>><?= t('Festival', 'Festival') ?></option>
                            <option value="firemni" <?= ($_POST['event_type']??'') === 'firemni' ? 'selected' : '' ?>><?= t('Firemní akce', 'Corporate event') ?></option>
                            <option value="svatba" <?= ($_POST['event_type']??'') === 'svatba' ? 'selected' : '' ?>><?= t('Svatba', 'Wedding') ?></option>
                            <option value="party" <?= ($_POST['event_type']??'') === 'party' ? 'selected' : '' ?>><?= t('Soukromá párty', 'Private party') ?></option>
                            <option value="jine" <?= ($_POST['event_type']??'') === 'jine' ? 'selected' : '' ?>><?= t('Jiné', 'Other') ?></option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><?= t('Zpráva / Doplňující informace', 'Message / Additional info') ?></label>
                        <textarea name="message" class="form-control" rows="4" placeholder="<?= t('Počet hostů, speciální požadavky...', 'Number of guests, special requirements...') ?>"><?= e($_POST['message'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">
                        📅 <?= t('Odeslat poptávku', 'Send enquiry') ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Obsazené termíny z PHP
const bookedDates = <?= json_encode(array_unique($bookedDates)) ?>;

// Jednoduchý kalendář
(function() {
    const container = document.getElementById('calendar');
    if (!container) return;

    let currentDate = new Date();
    let selectedFrom = null;
    let selectedTo = null;

    function render() {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const monthNames = ['Leden','Únor','Březen','Duben','Květen','Červen','Červenec','Srpen','Září','Říjen','Listopad','Prosinec'];
        const dayNames = ['Po','Út','St','Čt','Pá','So','Ne'];

        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        let startDow = firstDay.getDay(); // 0=Sun
        startDow = startDow === 0 ? 6 : startDow - 1; // Mon=0

        let html = `<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <button onclick="prevMonth()" style="background:none;border:1px solid #e0e0e0;border-radius:6px;padding:6px 12px;cursor:pointer">◀</button>
            <strong style="font-size:15px">${monthNames[month]} ${year}</strong>
            <button onclick="nextMonth()" style="background:none;border:1px solid #e0e0e0;border-radius:6px;padding:6px 12px;cursor:pointer">▶</button>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center">`;

        dayNames.forEach(d => {
            html += `<div style="font-size:11px;font-weight:600;color:#888;padding:4px">${d}</div>`;
        });

        for (let i = 0; i < startDow; i++) html += '<div></div>';

        for (let day = 1; day <= lastDay.getDate(); day++) {
            const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
            const today = new Date();
            today.setHours(0,0,0,0);
            const thisDate = new Date(year, month, day);
            const isPast = thisDate < today;
            const isBooked = bookedDates.includes(dateStr);

            let bg = 'white';
            let border = '1px solid #e0e0e0';
            let color = '#111';
            let cursor = 'pointer';

            if (isPast) { bg = '#f5f5f5'; color = '#ccc'; cursor = 'default'; }
            else if (isBooked) { bg = '#ffebee'; border = '1px solid #ef5350'; color = '#ef5350'; cursor = 'not-allowed'; }
            else if (selectedFrom && selectedTo) {
                if (dateStr >= selectedFrom && dateStr <= selectedTo) {
                    bg = 'rgba(232,99,26,0.15)'; border = '1px solid var(--orange)';
                }
            } else if (dateStr === selectedFrom) {
                bg = 'var(--orange)'; color = 'white'; border = '1px solid var(--orange)';
            }

            const clickable = !isPast && !isBooked;
            html += `<div onclick="${clickable ? `selectDate('${dateStr}')` : ''}"
                style="padding:6px 2px;border-radius:4px;font-size:13px;background:${bg};border:${border};color:${color};cursor:${cursor};transition:all 0.15s">
                ${day}
            </div>`;
        }

        html += '</div>';
        container.innerHTML = html;
    }

    window.prevMonth = function() {
        currentDate.setMonth(currentDate.getMonth() - 1);
        render();
    };
    window.nextMonth = function() {
        currentDate.setMonth(currentDate.getMonth() + 1);
        render();
    };
    window.selectDate = function(dateStr) {
        if (!selectedFrom || (selectedFrom && selectedTo)) {
            selectedFrom = dateStr;
            selectedTo = null;
            document.getElementById('date_from').value = dateStr;
            document.getElementById('date_to').value = '';
        } else {
            if (dateStr < selectedFrom) {
                selectedTo = selectedFrom;
                selectedFrom = dateStr;
            } else {
                selectedTo = dateStr;
            }
            document.getElementById('date_from').value = selectedFrom;
            document.getElementById('date_to').value = selectedTo;
        }
        render();
    };

    render();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
