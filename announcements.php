<?php
require_once __DIR__ . '/includes/public_helpers.php';

$category_tabs = [
    '' => 'All',
    'health' => 'Health',
    'events' => 'Events',
    'ordinance' => 'Ordinance',
    'emergency' => 'Emergency',
    'notice' => 'Notice',
];

function ann_url(array $overrides = []) {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null || $value === 0) {
            unset($params[$key]);
        }
    }

    return 'announcements.php' . ($params ? '?' . http_build_query($params) : '');
}

$article_id = max(0, (int)($_GET['id'] ?? 0));
$category = strtolower(trim((string)($_GET['category'] ?? '')));
if (!array_key_exists($category, $category_tabs)) {
    $category = '';
}

$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$article = null;
$announcements = [];
$total_announcements = 0;
$total_pages = 1;
$has_announcements_table = pub_table_exists($conn, 'announcements');

if ($has_announcements_table && $article_id > 0) {
    $article = pub_fetch_one(
        $conn,
        'SELECT id, title, category, body, thumbnail, published_at, created_at
         FROM announcements
         WHERE id = ? AND is_published = 1
         LIMIT 1',
        'i',
        [$article_id]
    );
} elseif ($has_announcements_table) {
    $where = 'WHERE is_published = 1';
    $types = '';
    $params = [];

    if ($category !== '') {
        $where .= ' AND category = ?';
        $types .= 's';
        $params[] = $category;
    }

    if ($search !== '') {
        $where .= ' AND (title LIKE ? OR body LIKE ?)';
        $like = '%' . $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }

    $total_announcements = pub_scalar($conn, "SELECT COUNT(*) FROM announcements {$where}", $types, $params);
    $total_pages = max(1, (int)ceil($total_announcements / $per_page));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;

    $list_params = $params;
    $list_params[] = $per_page;
    $list_params[] = $offset;

    $announcements = pub_fetch_all(
        $conn,
        "SELECT id, title, category, body, thumbnail, published_at, created_at
         FROM announcements
         {$where}
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC
         LIMIT ? OFFSET ?",
        $types . 'ii',
        $list_params
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Official announcements from Barangay Sta. Rosa 1, Noveleta, Cavite.">
  <title>Announcements - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/public_layout.css">
</head>
<body class="public-page">
<?php render_public_nav('announcements'); ?>

<main class="public-main">
  <?php if ($article_id > 0): ?>
    <section class="public-hero public-hero--compact">
      <div class="public-container">
        <nav class="public-breadcrumb" aria-label="Breadcrumb">
          <a href="index.php">Home</a>
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
          <a href="announcements.php">Announcements</a>
        </nav>

        <?php if ($article): ?>
          <?php
            $meta = pub_category_meta($article['category']);
            $published_at = $article['published_at'] ?: $article['created_at'];
          ?>
          <span class="public-badge public-badge--<?= pub_e($meta['class']) ?>"><i class="bi <?= pub_e($meta['icon']) ?>" aria-hidden="true"></i><?= pub_e($meta['label']) ?></span>
          <h1><?= pub_e($article['title']) ?></h1>
          <p>Published <?= pub_e(pub_date($published_at)) ?></p>
        <?php else: ?>
          <span class="public-badge public-badge--emergency"><i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>Not found</span>
          <h1>Announcement not found</h1>
          <p>The announcement may have been unpublished or removed.</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="public-section">
      <div class="public-container public-readable">
        <?php if ($article): ?>
          <?php if (!empty($article['thumbnail'])): ?>
            <img class="public-article-image" src="<?= pub_e($article['thumbnail']) ?>" alt="<?= pub_e($article['title']) ?>" loading="lazy">
          <?php endif; ?>
          <article class="public-article">
            <?= nl2br(pub_e($article['body'])) ?>
          </article>
        <?php else: ?>
          <div class="public-empty">
            <i class="bi bi-newspaper" aria-hidden="true"></i>
            <strong>No matching announcement</strong>
            <span>Return to the feed to browse published barangay updates.</span>
          </div>
        <?php endif; ?>

        <a class="public-link-button" href="announcements.php"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to announcements</a>
      </div>
    </section>
  <?php else: ?>
    <section class="public-hero">
      <div class="public-container public-hero__grid">
        <div>
          <span class="public-kicker">Barangay updates</span>
          <h1>Announcements</h1>
          <p>Official notices, emergency advisories, events, ordinances, and health updates from Barangay Sta. Rosa 1.</p>
        </div>
        <div class="public-hero__aside" aria-label="Announcement summary">
          <strong><?= pub_e((string)$total_announcements) ?></strong>
          <span>Published item<?= $total_announcements === 1 ? '' : 's' ?> matching the current view</span>
        </div>
      </div>
    </section>

    <section class="public-section">
      <div class="public-container">
        <div class="public-toolbar">
          <nav class="nav public-tab-pills" aria-label="Announcement categories">
            <?php foreach ($category_tabs as $key => $label): ?>
              <a class="nav-link <?= $category === $key ? 'active' : '' ?>" href="<?= pub_e(ann_url(['category' => $key, 'page' => 1, 'id' => 0])) ?>"><?= pub_e($label) ?></a>
            <?php endforeach; ?>
          </nav>

          <form class="public-search" method="get" action="announcements.php">
            <?php if ($category !== ''): ?>
              <input type="hidden" name="category" value="<?= pub_e($category) ?>">
            <?php endif; ?>
            <label class="visually-hidden" for="announcementSearch">Search announcements</label>
            <i class="bi bi-search" aria-hidden="true"></i>
            <input id="announcementSearch" type="search" name="q" value="<?= pub_e($search) ?>" placeholder="Search title or body">
            <button type="submit">Search</button>
          </form>
        </div>

        <?php if (!$has_announcements_table): ?>
          <div class="public-empty">
            <i class="bi bi-database-exclamation" aria-hidden="true"></i>
            <strong>Announcements table is not available</strong>
            <span>Create the announcements table from the schema to publish barangay updates.</span>
          </div>
        <?php elseif (!$announcements): ?>
          <div class="public-empty">
            <i class="bi bi-newspaper" aria-hidden="true"></i>
            <strong>No announcements found</strong>
            <span>Published updates that match your filters will appear here.</span>
          </div>
        <?php else: ?>
          <div class="announcement-feed">
            <?php foreach ($announcements as $announcement): ?>
              <?php
                $meta = pub_category_meta($announcement['category']);
                $published_at = $announcement['published_at'] ?: $announcement['created_at'];
              ?>
              <article class="announcement-card">
                <div class="announcement-card__icon announcement-card__icon--<?= pub_e($meta['class']) ?>">
                  <i class="bi <?= pub_e($meta['icon']) ?>" aria-hidden="true"></i>
                </div>
                <div class="announcement-card__body">
                  <div class="announcement-card__meta">
                    <span class="public-badge public-badge--<?= pub_e($meta['class']) ?>"><?= pub_e($meta['label']) ?></span>
                    <time datetime="<?= pub_e(date('Y-m-d', strtotime((string)$published_at))) ?>"><?= pub_e(pub_date_short($published_at)) ?></time>
                  </div>
                  <h2><?= pub_e($announcement['title']) ?></h2>
                  <p><?= pub_e(pub_excerpt($announcement['body'], 210)) ?></p>
                  <a href="announcements.php?id=<?= (int)$announcement['id'] ?>">Read more <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
              </article>
            <?php endforeach; ?>
          </div>

          <?php if ($total_pages > 1): ?>
            <nav class="public-pagination" aria-label="Announcement pages">
              <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : pub_e(ann_url(['page' => $page - 1, 'id' => 0])) ?>">Previous</a>
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a class="<?= $page === $i ? 'active' : '' ?>" href="<?= pub_e(ann_url(['page' => $i, 'id' => 0])) ?>"><?= $i ?></a>
              <?php endfor; ?>
              <a class="<?= $page >= $total_pages ? 'disabled' : '' ?>" href="<?= $page >= $total_pages ? '#' : pub_e(ann_url(['page' => $page + 1, 'id' => 0])) ?>">Next</a>
            </nav>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </section>
  <?php endif; ?>
</main>

<?php render_public_footer(); ?>
<button class="back-to-top" id="backToTop" aria-label="Back to top"><i class="bi bi-chevron-up" aria-hidden="true"></i></button>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/public_layout.js"></script>
</body>
</html>
