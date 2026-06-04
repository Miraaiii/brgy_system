<?php
require_once __DIR__ . '/includes/public_helpers.php';

function officials_default_committees() {
    return [
        'Committee on Health & Sanitation',
        'Committee on Education & Culture',
        'Committee on Peace, Order & Public Safety',
        'Committee on Infrastructure & Public Works',
        'Committee on Livelihood & Economic Affairs',
        'Committee on Environment & Natural Resources',
        'Committee on Women, Family & Senior Citizens',
    ];
}

function officials_placeholder($position, $name, $committee = null) {
    return [
        'id' => 0,
        'fullname' => $name,
        'position' => $position,
        'committee' => $committee,
        'photo_path' => '',
        'term_start' => '2023-01-01',
        'term_end' => '2025-12-31',
        'is_placeholder' => true,
    ];
}

function officials_photo_html(array $official, $class_name) {
    $path = trim((string)($official['photo_path'] ?? ''));
    if ($path !== '') {
        $safe_path = str_replace('\\', '/', $path);
        $local_path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($safe_path, '/'));
        if (is_file($local_path)) {
            return '<img class="' . pub_e($class_name) . '" src="' . pub_e($safe_path) . '" alt="' . pub_e($official['fullname']) . '" loading="lazy">';
        }
    }

    return '<div class="' . pub_e($class_name) . ' ' . pub_e($class_name) . '--placeholder" aria-hidden="true">' . pub_e(pub_initials($official['fullname'] ?? '')) . '</div>';
}

$officials = [];
if (pub_table_exists($conn, 'officials') && pub_table_exists($conn, 'users')) {
    $officials = pub_fetch_all(
        $conn,
        "SELECT o.id, o.position, o.committee, o.photo_path, o.term_start, o.term_end,
                u.fullname, u.email
         FROM officials o
         INNER JOIN users u ON u.id = o.user_id
         WHERE o.is_active = 1
         ORDER BY FIELD(o.position, 'captain', 'secretary', 'treasurer', 'kagawad', 'sk_chair', 'sk_kagawad'), o.id"
    );
}

$captain_user = null;
if (pub_table_exists($conn, 'users')) {
    $captain_user = pub_fetch_one(
        $conn,
        "SELECT fullname
         FROM users
         WHERE role = 'captain'
         ORDER BY id
         LIMIT 1"
    );
}

$captain = null;
foreach ($officials as $official) {
    if ($official['position'] === 'captain') {
        $captain = $official;
        break;
    }
}
if (!$captain) {
    $captain_name = $captain_user['fullname'] ?? 'Hon. Juan Reyes';
    $captain = officials_placeholder('captain', $captain_name, 'Executive Office');
}

$term_label = pub_term_years($captain['term_start'] ?? null, $captain['term_end'] ?? null);
$others = array_values(array_filter($officials, function ($official) {
    return $official['position'] !== 'captain';
}));

$has_secretary = count(array_filter($others, fn($official) => $official['position'] === 'secretary')) > 0;
$has_treasurer = count(array_filter($others, fn($official) => $official['position'] === 'treasurer')) > 0;
$has_sk_chair = count(array_filter($others, fn($official) => $official['position'] === 'sk_chair')) > 0;

if (!$has_secretary) {
    $others[] = officials_placeholder('secretary', 'Barangay Secretary', 'Records and Civil Registry');
}
if (!$has_treasurer) {
    $others[] = officials_placeholder('treasurer', 'Barangay Treasurer', 'Finance and Collections');
}

$committees = officials_default_committees();
$kagawad_count = 0;
foreach ($others as $index => $official) {
    if ($official['position'] === 'kagawad') {
        if (empty($others[$index]['committee'])) {
            $others[$index]['committee'] = $committees[$kagawad_count] ?? 'Committee Assignment';
        }
        $kagawad_count++;
    }
}

for ($i = $kagawad_count; $i < 7; $i++) {
    $others[] = officials_placeholder('kagawad', 'Barangay Kagawad ' . ($i + 1), $committees[$i]);
}

if (!$has_sk_chair) {
    $others[] = officials_placeholder('sk_chair', 'SK Chairperson', 'Sangguniang Kabataan');
}

$position_order = [
    'secretary' => 1,
    'treasurer' => 2,
    'kagawad' => 3,
    'sk_chair' => 4,
    'sk_kagawad' => 5,
];
usort($others, function ($a, $b) use ($position_order) {
    $left = $position_order[$a['position']] ?? 99;
    $right = $position_order[$b['position']] ?? 99;
    if ($left === $right) {
        return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
    }
    return $left <=> $right;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Directory of Barangay Sta. Rosa 1 officials and committee assignments.">
  <title>Officials Directory - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/public_layout.css">
</head>
<body class="public-page">
<?php render_public_nav('officials'); ?>

<main class="public-main">
  <section class="public-hero">
    <div class="public-container public-hero__grid">
      <div>
        <span class="public-kicker">Barangay leadership</span>
        <h1>Officials Directory</h1>
        <p>Meet the officials serving Barangay Sta. Rosa 1 for the current term.</p>
      </div>
      <div class="public-hero__aside" aria-label="Current term">
        <strong><?= pub_e($term_label) ?></strong>
        <span>Current barangay term period</span>
      </div>
    </div>
  </section>

  <section class="public-section">
    <div class="public-container">
      <article class="captain-profile-card">
        <div class="captain-profile-card__photo">
          <?= officials_photo_html($captain, 'captain-photo') ?>
        </div>
        <div class="captain-profile-card__body">
          <span class="public-badge public-badge--notice"><i class="bi bi-shield-fill-check" aria-hidden="true"></i>Captain</span>
          <h2><?= pub_e($captain['fullname']) ?></h2>
          <p><?= pub_e(pub_position_label($captain['position'])) ?></p>
          <dl class="official-term-list">
            <div>
              <dt>Term period</dt>
              <dd><?= pub_e(pub_term_years($captain['term_start'] ?? null, $captain['term_end'] ?? null)) ?></dd>
            </div>
            <div>
              <dt>Office</dt>
              <dd><?= pub_e($captain['committee'] ?: 'Executive Office') ?></dd>
            </div>
          </dl>
        </div>
      </article>
    </div>
  </section>

  <section class="public-section public-section--soft">
    <div class="public-container">
      <div class="public-section-heading">
        <span class="public-kicker">Directory</span>
        <h2>Barangay Officials</h2>
      </div>

      <div class="official-grid">
        <?php foreach ($others as $official): ?>
          <article class="official-card">
            <?= officials_photo_html($official, 'official-photo') ?>
            <div class="official-card__body">
              <h3><?= pub_e($official['fullname']) ?></h3>
              <p><?= pub_e(pub_position_label($official['position'])) ?></p>
              <?php if (!empty($official['committee'])): ?>
                <span class="official-committee"><?= pub_e($official['committee']) ?></span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<?php render_public_footer(); ?>
<button class="back-to-top" id="backToTop" aria-label="Back to top"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/public_layout.js"></script>
</body>
</html>
