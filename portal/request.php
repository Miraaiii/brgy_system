<?php
require_once __DIR__ . '/includes/resident_portal.php';

rp_ensure_document_request_columns($conn);

$ctx = rp_get_resident_context($conn, true);
$errors = [];
$success_request = null;
$max_file_size = 5 * 1024 * 1024;
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$allowed_mimes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
];

$has_request_tables = rp_table_exists($conn, 'document_types')
    && rp_table_exists($conn, 'document_requests')
    && rp_table_exists($conn, 'request_attachments');

if ($has_request_tables && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_request') {
    if (!bms_verify_csrf_token($_POST['csrf_token'] ?? '', 'resident_request_csrf')) {
        $errors[] = 'Your session expired. Please refresh and try again.';
    }

    if (!$ctx['is_verified']) {
        $errors[] = 'Your account must be approved first before requesting documents.';
    }

    $doc_type_id = isset($_POST['doc_type_id']) ? (int)$_POST['doc_type_id'] : 0;
    $document_type = null;
    if ($doc_type_id > 0) {
        $document_type = rp_fetch_one(
            $conn,
            'SELECT id, name, slug, fee, processing_days, requirements
             FROM document_types
             WHERE id = ? AND is_active = 1
             LIMIT 1',
            'i',
            [$doc_type_id]
        );
    }
    if (!$document_type) {
        $errors[] = 'Please select a valid document type.';
    }

    $purpose = trim((string)($_POST['purpose'] ?? ''));
    if ($purpose === '') {
        $errors[] = 'Please enter the purpose of your request.';
    }

    if (empty($_POST['confirm_request'])) {
        $errors[] = 'Please confirm that the information is complete and accurate.';
    }

    $slug = $document_type['slug'] ?? '';
    $extra_details = [];
    if ($slug === 'business-clearance') {
        $extra_details['business_name'] = trim((string)($_POST['business_name'] ?? ''));
        $extra_details['business_type'] = trim((string)($_POST['business_type'] ?? ''));
        $extra_details['business_address'] = trim((string)($_POST['business_address'] ?? ''));
        foreach (['business_name' => 'business name', 'business_type' => 'type of business', 'business_address' => 'business address'] as $field => $label) {
            if ($extra_details[$field] === '') {
                $errors[] = 'Please enter the ' . $label . '.';
            }
        }
    } elseif ($slug === 'barangay-certification') {
        $extra_details['certification_type'] = trim((string)($_POST['certification_type'] ?? ''));
        if ($extra_details['certification_type'] === '') {
            $errors[] = 'Please select the type of certification.';
        }
    } elseif ($slug === 'blotter-certificate') {
        $extra_details['case_number'] = strtoupper(trim((string)($_POST['case_number'] ?? '')));
        if ($extra_details['case_number'] === '') {
            $errors[] = 'Please enter the blotter case number.';
        } elseif (!preg_match('/^BL-\d{4}-\d{4}$/', $extra_details['case_number'])) {
            $errors[] = 'Use the case number format BL-YYYY-NNNN.';
        }
    }

    $uploads = [];
    if (!empty($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
        $file_count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $file_count; $i++) {
            $error = (int)($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = 'One attachment failed to upload. Please try again.';
                continue;
            }

            $original_name = basename((string)$_FILES['attachments']['name'][$i]);
            $size = (int)($_FILES['attachments']['size'][$i] ?? 0);
            $tmp_name = (string)($_FILES['attachments']['tmp_name'][$i] ?? '');
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

            if ($size <= 0 || $tmp_name === '') {
                $errors[] = 'One attachment is empty. Please choose a valid file.';
                continue;
            }
            if ($size > $max_file_size) {
                $errors[] = $original_name . ' is larger than 5MB.';
                continue;
            }
            if (!in_array($extension, $allowed_extensions, true)) {
                $errors[] = $original_name . ' must be a PDF, JPG, JPEG, or PNG file.';
                continue;
            }

            $mime = function_exists('mime_content_type') ? (string)@mime_content_type($tmp_name) : ($allowed_mimes[$extension] ?? '');
            $safe_original = preg_replace('/[^A-Za-z0-9._-]+/', '_', $original_name);
            $stored_name = 'request_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
            $uploads[] = [
                'original_name' => $safe_original ?: ('attachment.' . $extension),
                'stored_name' => $stored_name,
                'tmp_name' => $tmp_name,
                'mime' => $mime ?: ($allowed_mimes[$extension] ?? 'application/octet-stream'),
                'size' => $size,
            ];
        }
    }

    if (!$uploads) {
        $errors[] = 'Please upload at least one attachment.';
    }

    if (!$errors) {
        $moved_paths = [];
        $upload_dir = __DIR__ . '/../uploads/request_attachments/' . date('Y');
        $relative_dir = 'uploads/request_attachments/' . date('Y');
        $transaction_started = false;

        try {
            if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                throw new Exception('Unable to create the attachment folder.');
            }

            $conn->begin_transaction();
            $transaction_started = true;
            $reference_no = rp_generate_request_reference($conn);
            $extra_json = json_encode($extra_details, JSON_UNESCAPED_SLASHES);
            $has_extra_column = rp_column_exists($conn, 'document_requests', 'extra_details');

            if ($has_extra_column) {
                $stmt = $conn->prepare(
                    'INSERT INTO document_requests (reference_no, resident_id, doc_type_id, purpose, extra_details)
                     VALUES (?, ?, ?, ?, ?)'
                );
                if (!$stmt) {
                    throw new Exception('Unable to prepare the request.');
                }
                $stmt->bind_param('siiss', $reference_no, $ctx['resident_id'], $doc_type_id, $purpose, $extra_json);
            } else {
                $stored_purpose = $purpose;
                if ($extra_details) {
                    $stored_purpose .= "\n\nAdditional details: " . json_encode($extra_details, JSON_UNESCAPED_SLASHES);
                }
                $stmt = $conn->prepare(
                    'INSERT INTO document_requests (reference_no, resident_id, doc_type_id, purpose)
                     VALUES (?, ?, ?, ?)'
                );
                if (!$stmt) {
                    throw new Exception('Unable to prepare the request.');
                }
                $stmt->bind_param('siis', $reference_no, $ctx['resident_id'], $doc_type_id, $stored_purpose);
            }

            if (!$stmt->execute()) {
                throw new Exception('Unable to save the request.');
            }
            $request_id = (int)$stmt->insert_id;
            $stmt->close();

            $attach_stmt = $conn->prepare(
                'INSERT INTO request_attachments (request_id, file_name, file_path, file_type, file_size)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if (!$attach_stmt) {
                throw new Exception('Unable to prepare attachments.');
            }

            foreach ($uploads as $upload) {
                $target_path = $upload_dir . DIRECTORY_SEPARATOR . $upload['stored_name'];
                if (!move_uploaded_file($upload['tmp_name'], $target_path)) {
                    throw new Exception('Unable to save uploaded files.');
                }
                $moved_paths[] = $target_path;
                $relative_path = $relative_dir . '/' . $upload['stored_name'];
                $attach_stmt->bind_param(
                    'isssi',
                    $request_id,
                    $upload['original_name'],
                    $relative_path,
                    $upload['mime'],
                    $upload['size']
                );
                if (!$attach_stmt->execute()) {
                    throw new Exception('Unable to save attachment records.');
                }
            }
            $attach_stmt->close();

            $conn->commit();
            rp_notify_request_submitted($conn, $ctx, $request_id, $reference_no);
            header('Location: request.php?submitted=' . $request_id);
            exit();
        } catch (Throwable $e) {
            if ($transaction_started) {
                $conn->rollback();
            }
            foreach ($moved_paths as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            $errors[] = 'We could not submit the request right now. Please try again.';
        }
    }
}

if ($has_request_tables && isset($_GET['submitted'])) {
    $success_request = rp_fetch_one(
        $conn,
        'SELECT dr.id, dr.reference_no, dr.created_at, dt.name AS document_name
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         WHERE dr.id = ? AND dr.resident_id = ?
         LIMIT 1',
        'ii',
        [(int)$_GET['submitted'], (int)$ctx['resident_id']]
    );
}

$document_types = [];
if (rp_table_exists($conn, 'document_types')) {
    $document_types = rp_fetch_all(
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

$csrf_token = bms_csrf_token('resident_request_csrf');
$prefill_doc_slug = trim((string)($_GET['doc'] ?? ''));
$prefill_case_number = strtoupper(trim((string)($_GET['case'] ?? ($_POST['case_number'] ?? ''))));

rp_page_start('Request a Document', 'request', $ctx, 'request-page');
?>

<?php if ($success_request): ?>
  <section class="success-panel">
    <span class="success-panel__icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
    <div>
      <p class="page-kicker">Request submitted</p>
      <h1>Your reference number is <code class="ref-code"><?= rp_e($success_request['reference_no']) ?></code></h1>
      <p>Your <?= rp_e($success_request['document_name']) ?> request has been submitted and is waiting for Secretary review.</p>
      <div class="success-actions">
        <a class="primary-action" href="request-detail.php?id=<?= rp_e($success_request['id']) ?>"><i class="fa-solid fa-timeline"></i> View timeline</a>
        <a class="secondary-action" href="track.php"><i class="fa-solid fa-folder-open"></i> Track my requests</a>
        <a class="secondary-action" href="request.php"><i class="fa-solid fa-file-circle-plus"></i> Request another</a>
      </div>
    </div>
  </section>
<?php else: ?>
  <section class="portal-page-header">
    <div>
      <p class="page-kicker">Document services</p>
      <h1>Request a Document</h1>
      <p>Complete the four steps below. A reference number is generated after submission.</p>
    </div>
    <a class="secondary-action" href="track.php"><i class="fa-solid fa-folder-open"></i> Track requests</a>
  </section>

  <?php if (!$has_request_tables): ?>
    <div class="account-alert account-alert--danger" role="alert">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span>Document request tables are not installed yet. Please import the database schema first.</span>
    </div>
  <?php endif; ?>

  <?php if (!$ctx['is_verified']): ?>
    <div class="account-alert" role="alert">
      <i class="fa-solid fa-lock"></i>
      <span>Your account must be approved first before requesting documents.</span>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="account-alert account-alert--danger" role="alert">
      <i class="fa-solid fa-circle-exclamation"></i>
      <span><?= rp_e(implode(' ', $errors)) ?></span>
    </div>
  <?php endif; ?>

  <form class="request-wizard" id="documentRequestForm" method="post" enctype="multipart/form-data" data-max-file-size="<?= rp_e($max_file_size) ?>">
    <input type="hidden" name="action" value="submit_request">
    <input type="hidden" name="csrf_token" value="<?= rp_e($csrf_token) ?>">

    <div class="wizard-progress" aria-label="Request steps">
      <button class="wizard-step-pill is-active" type="button" data-step-jump="1"><span>1</span>Select document</button>
      <button class="wizard-step-pill" type="button" data-step-jump="2"><span>2</span>Fill in details</button>
      <button class="wizard-step-pill" type="button" data-step-jump="3"><span>3</span>Upload attachments</button>
      <button class="wizard-step-pill" type="button" data-step-jump="4"><span>4</span>Review &amp; confirm</button>
    </div>

    <fieldset <?= (!$ctx['is_verified'] || !$has_request_tables) ? 'disabled' : '' ?>>
      <section class="wizard-step is-active" data-wizard-step="1" aria-labelledby="step1Title">
        <div class="wizard-heading">
          <div>
            <h2 id="step1Title">Select document</h2>
            <p>Choose the certificate or clearance you need.</p>
          </div>
          <span class="step-count">Step 1 of 4</span>
        </div>

        <div class="document-card-grid">
          <?php foreach ($document_types as $doc): ?>
            <?php $requirements = rp_split_requirements($doc['requirements'] ?? '', $doc['slug']); ?>
            <label class="document-card"
                   data-doc-card
                   data-doc-id="<?= rp_e($doc['id']) ?>"
                   data-doc-name="<?= rp_e($doc['name']) ?>"
                   data-doc-slug="<?= rp_e($doc['slug']) ?>"
                   data-doc-fee="<?= rp_e(number_format((float)$doc['fee'], 2)) ?>"
                   data-doc-days="<?= rp_e((int)$doc['processing_days']) ?>"
                   data-doc-requirements="<?= rp_json_attr($requirements) ?>">
              <input class="sr-only" type="radio" name="doc_type_id" value="<?= rp_e($doc['id']) ?>" <?= $prefill_doc_slug === $doc['slug'] ? 'checked' : '' ?> required>
              <span class="document-card__icon"><i class="fa-solid <?= rp_e(rp_doc_icon($doc['slug'])) ?>" aria-hidden="true"></i></span>
              <span class="document-card__body">
                <strong><?= rp_e($doc['name']) ?></strong>
                <small>Fee: PHP <?= rp_e(number_format((float)$doc['fee'], 2)) ?></small>
                <small><?= rp_e((int)$doc['processing_days']) ?> working day<?= (int)$doc['processing_days'] === 1 ? '' : 's' ?></small>
              </span>
              <span class="document-card__check"><i class="fa-solid fa-check"></i></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="wizard-step" data-wizard-step="2" aria-labelledby="step2Title">
        <div class="wizard-heading">
          <div>
            <h2 id="step2Title">Fill in details</h2>
            <p>Tell the barangay office what the document will be used for.</p>
          </div>
          <span class="step-count">Step 2 of 4</span>
        </div>

        <div class="form-grid">
          <div class="form-field form-field--full">
            <label for="requestPurpose">Purpose of request</label>
            <textarea id="requestPurpose" name="purpose" rows="5" placeholder="Example: Employment, school enrollment, utilities, scholarship, travel" required><?= rp_e($_POST['purpose'] ?? '') ?></textarea>
          </div>

          <div class="extra-fields form-field--full" data-extra-for="business-clearance" hidden>
            <div class="form-grid">
              <div class="form-field">
                <label for="businessName">Business name</label>
                <input id="businessName" name="business_name" type="text" value="<?= rp_e($_POST['business_name'] ?? '') ?>">
              </div>
              <div class="form-field">
                <label for="businessType">Type of business</label>
                <input id="businessType" name="business_type" type="text" value="<?= rp_e($_POST['business_type'] ?? '') ?>">
              </div>
              <div class="form-field form-field--full">
                <label for="businessAddress">Business address</label>
                <input id="businessAddress" name="business_address" type="text" value="<?= rp_e($_POST['business_address'] ?? '') ?>">
              </div>
            </div>
          </div>

          <div class="extra-fields form-field" data-extra-for="barangay-certification" hidden>
            <label for="certificationType">Type of certification</label>
            <select id="certificationType" name="certification_type">
              <option value="">Select type</option>
              <?php foreach (['Solo Parent', 'Good Standing', 'Organization Member', 'Other'] as $option): ?>
                <option value="<?= rp_e($option) ?>" <?= (($_POST['certification_type'] ?? '') === $option) ? 'selected' : '' ?>><?= rp_e($option) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="extra-fields form-field" data-extra-for="blotter-certificate" hidden>
            <label for="caseNumber">Blotter case number</label>
            <input id="caseNumber" name="case_number" type="text" placeholder="BL-2025-0003" value="<?= rp_e($prefill_case_number) ?>">
          </div>
        </div>
      </section>

      <section class="wizard-step" data-wizard-step="3" aria-labelledby="step3Title">
        <div class="wizard-heading">
          <div>
            <h2 id="step3Title">Upload attachments</h2>
            <p>Attach clear copies of the required files. Each file must be 5MB or smaller.</p>
          </div>
          <span class="step-count">Step 3 of 4</span>
        </div>

        <div class="attachment-layout">
          <div class="requirements-panel">
            <h3>Required attachments</h3>
            <ul id="requiredAttachmentList">
              <li>Select a document first.</li>
            </ul>
          </div>

          <div class="upload-dropzone" id="uploadDropzone">
            <input id="attachmentInput" name="attachments[]" type="file" accept=".pdf,.jpg,.jpeg,.png" multiple>
            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
            <strong>Drag files here or click to upload</strong>
            <span>Accepted: PDF, JPG, JPEG, PNG. Maximum 5MB per file.</span>
          </div>
        </div>
        <div class="upload-list" id="uploadList" aria-live="polite"></div>
      </section>

      <section class="wizard-step" data-wizard-step="4" aria-labelledby="step4Title">
        <div class="wizard-heading">
          <div>
            <h2 id="step4Title">Review &amp; confirm</h2>
            <p>Check every detail before sending the request.</p>
          </div>
          <span class="step-count">Step 4 of 4</span>
        </div>

        <div class="review-summary" id="requestReviewSummary">
          <div>
            <span>Document type</span>
            <strong data-review="document">Not selected</strong>
          </div>
          <div>
            <span>Purpose</span>
            <strong data-review="purpose">Not provided</strong>
          </div>
          <div>
            <span>Additional details</span>
            <strong data-review="extra">None</strong>
          </div>
          <div>
            <span>Uploaded files</span>
            <strong data-review="files">No files selected</strong>
          </div>
        </div>

        <label class="confirm-check">
          <input type="checkbox" name="confirm_request" value="1" required>
          <span>I confirm that the information and uploaded files are complete and accurate.</span>
        </label>
      </section>
    </fieldset>

    <div class="wizard-actions">
      <button class="secondary-action" type="button" data-wizard-back disabled><i class="fa-solid fa-arrow-left"></i> Back</button>
      <button class="primary-action" type="button" data-wizard-next <?= (!$ctx['is_verified'] || !$has_request_tables) ? 'disabled title="Your account must be approved first."' : '' ?>>Next <i class="fa-solid fa-arrow-right"></i></button>
      <button class="primary-action wizard-submit" type="submit" data-wizard-submit hidden <?= (!$ctx['is_verified'] || !$has_request_tables) ? 'disabled title="Your account must be approved first."' : '' ?>><i class="fa-solid fa-paper-plane"></i> Submit request</button>
    </div>
  </form>
<?php endif; ?>

<?php rp_page_end(); ?>
