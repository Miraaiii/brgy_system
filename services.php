<?php
require_once __DIR__ . '/includes/public_helpers.php';

function services_fallback_documents() {
    return [
        ['id' => 1, 'name' => 'Barangay Clearance', 'slug' => 'barangay-clearance', 'fee' => 75.00, 'processing_days' => 1, 'description' => '', 'requirements' => 'Valid government ID; Proof of residency'],
        ['id' => 2, 'name' => 'Certificate of Residency', 'slug' => 'certificate-residency', 'fee' => 50.00, 'processing_days' => 1, 'description' => '', 'requirements' => 'Valid government ID; Proof of address or utility bill'],
        ['id' => 3, 'name' => 'Certificate of Indigency', 'slug' => 'certificate-indigency', 'fee' => 0.00, 'processing_days' => 1, 'description' => '', 'requirements' => 'Valid government ID; Proof of residency; Supporting document for assistance request if available'],
        ['id' => 4, 'name' => 'Business Clearance', 'slug' => 'business-clearance', 'fee' => 300.00, 'processing_days' => 2, 'description' => '', 'requirements' => 'Valid government ID; Proof of business address; Business registration document if available'],
        ['id' => 5, 'name' => 'Barangay Certification', 'slug' => 'barangay-certification', 'fee' => 50.00, 'processing_days' => 1, 'description' => '', 'requirements' => 'Valid government ID; Supporting document for the certification type'],
        ['id' => 6, 'name' => 'Blotter Certificate', 'slug' => 'blotter-certificate', 'fee' => 100.00, 'processing_days' => 2, 'description' => '', 'requirements' => 'Valid government ID; Blotter case reference number'],
    ];
}

$documents = [];
if (pub_table_exists($conn, 'document_types')) {
    $documents = pub_fetch_all(
        $conn,
        "SELECT id, name, slug, fee, processing_days, description, requirements
         FROM document_types
         WHERE is_active = 1
         ORDER BY FIELD(slug,
           'barangay-clearance',
           'certificate-residency',
           'certificate-indigency',
           'business-clearance',
           'barangay-certification',
           'blotter-certificate'
         ), name"
    );
}

if (!$documents) {
    $documents = services_fallback_documents();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Barangay Sta. Rosa 1 document services, fees, processing time, and requirements.">
  <title>Services and Fees - Barangay Sta. Rosa 1</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/public_layout.css">
</head>
<body class="public-page">
<?php render_public_nav('services'); ?>

<main class="public-main">
  <section class="public-hero">
    <div class="public-container public-hero__grid">
      <div>
        <span class="public-kicker">Documents and fees</span>
        <h1>Services &amp; Fees</h1>
        <p>View available barangay documents, processing times, requirements, and fees before starting a request.</p>
      </div>
      <div class="public-hero__aside" aria-label="Service summary">
        <strong><?= count($documents) ?></strong>
        <span>Document type<?= count($documents) === 1 ? '' : 's' ?> available for online request</span>
      </div>
    </div>
  </section>

  <section class="public-section">
    <div class="public-container">
      <div class="service-grid">
        <?php foreach ($documents as $doc): ?>
          <?php
            $meta = pub_doc_meta($doc['slug'], $doc['name']);
            $requirements = pub_requirements_list($doc['requirements'] ?? '');
            $request_url = 'portal/request.php?doc=' . urlencode($doc['slug']);
          ?>
          <article class="service-public-card">
            <div class="service-public-card__icon" aria-hidden="true"><i class="bi <?= pub_e($meta['icon']) ?>"></i></div>
            <h2><?= pub_e($doc['name']) ?></h2>
            <p><?= pub_e($doc['description'] ?: $meta['description']) ?></p>

            <dl class="service-facts">
              <div>
                <dt>Fee</dt>
                <dd><?= pub_e(pub_money($doc['fee'])) ?></dd>
              </div>
              <div>
                <dt>Processing</dt>
                <dd><?= pub_e(pub_processing_time($doc['processing_days'])) ?></dd>
              </div>
              <div>
                <dt>Required IDs</dt>
                <dd><?= pub_e($meta['required_ids']) ?></dd>
              </div>
              <div>
                <dt>Eligibility</dt>
                <dd><?= pub_e($meta['eligibility']) ?></dd>
              </div>
            </dl>

            <a class="public-primary-button" href="<?= pub_e($request_url) ?>">Request this document <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="public-section public-section--soft">
    <div class="public-container">
      <div class="public-section-heading">
        <span class="public-kicker">Quick reference</span>
        <h2>Fee Table</h2>
      </div>

      <div class="table-responsive public-table-wrap">
        <table class="table public-table align-middle">
          <thead>
            <tr>
              <th>Document</th>
              <th>Fee</th>
              <th>Processing time</th>
              <th>Requirements</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($documents as $doc): ?>
              <tr>
                <td><strong><?= pub_e($doc['name']) ?></strong></td>
                <td><?= pub_e(pub_money($doc['fee'])) ?></td>
                <td><?= pub_e(pub_processing_time($doc['processing_days'])) ?></td>
                <td><?= pub_e(implode(', ', pub_requirements_list($doc['requirements'] ?? ''))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="public-section">
    <div class="public-container">
      <div class="public-section-heading">
        <span class="public-kicker">Required attachments</span>
        <h2>Requirements</h2>
      </div>

      <div class="accordion public-accordion" id="requirementsAccordion">
        <?php foreach ($documents as $index => $doc): ?>
          <?php
            $meta = pub_doc_meta($doc['slug'], $doc['name']);
            $requirements = pub_requirements_list($doc['requirements'] ?? '');
            $collapse_id = 'requirements-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($doc['slug']));
          ?>
          <div class="accordion-item">
            <h3 class="accordion-header">
              <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= pub_e($collapse_id) ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="<?= pub_e($collapse_id) ?>">
                <i class="bi <?= pub_e($meta['icon']) ?>" aria-hidden="true"></i>
                <?= pub_e($doc['name']) ?>
              </button>
            </h3>
            <div id="<?= pub_e($collapse_id) ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#requirementsAccordion">
              <div class="accordion-body">
                <ul class="public-check-list">
                  <?php foreach ($requirements as $requirement): ?>
                    <li><i class="bi bi-check2-circle" aria-hidden="true"></i><?= pub_e($requirement) ?></li>
                  <?php endforeach; ?>
                </ul>
                <a class="public-link-button" href="portal/request.php?doc=<?= pub_e(urlencode($doc['slug'])) ?>">Start request <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
              </div>
            </div>
          </div>
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
