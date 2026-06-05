<?php
// admin/finance.php

$tab = $_GET['tab'] ?? 'collections';
$allowed_tabs = ['collections', 'record', 'receipts', 'expenditures', 'add-exp', 'budget', 'reports'];
if (!in_array($tab, $allowed_tabs)) {
    $tab = 'collections';
}

// Tab metadata for titles / active state
$tab_meta = [
    'collections'  => ['label' => 'All Collections',  'section' => 'collections'],
    'record'       => ['label' => 'Record Payment',    'section' => 'collections'],
    'receipts'     => ['label' => 'Official Receipts', 'section' => 'collections'],
    'expenditures' => ['label' => 'All Expenditures',  'section' => 'expenditures'],
    'add-exp'      => ['label' => 'Add Expenditure',   'section' => 'expenditures'],
    'budget'       => ['label' => 'Budget Management', 'section' => 'budget'],
    'reports'      => ['label' => 'Financial Reports', 'section' => 'reports'],
];

$page_title = $tab_meta[$tab]['label'] ?? 'Finance';

// Load section-specific data here before output
switch ($tab) {
    case 'collections':
        // $collections = fetch_all_collections();
        break;

    case 'record':
        // handle POST for new payment
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // save payment logic
        }
        break;

    case 'receipts':
        // $receipts = fetch_receipts();
        break;

    case 'expenditures':
        // $expenditures = fetch_all_expenditures();
        break;

    case 'add-exp':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // save expenditure logic
        }
        break;

    case 'budget':
        // $budget_items = fetch_budget();
        break;

    case 'reports':
        // $report_data = fetch_report_summary();
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($page_title) ?> — Barangay Finance</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="assets/css/admin.css" />
</head>
<body>

  <?php include 'partials/sidebar.php'; ?>

  <div class="admin-shell">

    <?php include 'partials/topbar.php'; ?>

    <main class="admin-main">

      <!-- Page header -->
      <div class="page-header">
        <div>
          <h1 class="page-title"><?= htmlspecialchars($page_title) ?></h1>
          <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
              <li class="breadcrumb-item active"><?= htmlspecialchars($page_title) ?></li>
            </ol>
          </nav>
        </div>
      </div>

      <!-- Bootstrap nav-tabs (URL-driven, not JS-driven) -->
      <ul class="nav nav-tabs mb-4" role="tablist">

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($tab, ['collections','record','receipts']) ? 'active' : '' ?>"
             data-bs-toggle="dropdown" href="#" role="button">
            <i class="fa-solid fa-money-bill-transfer me-1"></i> Collections
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item <?= $tab === 'collections' ? 'active' : '' ?>"
                   href="finance.php?tab=collections">All Collections</a></li>
            <li><a class="dropdown-item <?= $tab === 'record' ? 'active' : '' ?>"
                   href="finance.php?tab=record">Record Payment</a></li>
            <li><a class="dropdown-item <?= $tab === 'receipts' ? 'active' : '' ?>"
                   href="finance.php?tab=receipts">Official Receipts</a></li>
          </ul>
        </li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= in_array($tab, ['expenditures','add-exp']) ? 'active' : '' ?>"
             data-bs-toggle="dropdown" href="#" role="button">
            <i class="fa-solid fa-money-bill-wave me-1"></i> Expenditures
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item <?= $tab === 'expenditures' ? 'active' : '' ?>"
                   href="finance.php?tab=expenditures">All Expenditures</a></li>
            <li><a class="dropdown-item <?= $tab === 'add-exp' ? 'active' : '' ?>"
                   href="finance.php?tab=add-exp">Add Expenditure</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $tab === 'budget' ? 'active' : '' ?>"
             href="finance.php?tab=budget">
            <i class="fa-solid fa-chart-pie me-1"></i> Budget
          </a>
        </li>

        <li class="nav-item">
          <a class="nav-link <?= $tab === 'reports' ? 'active' : '' ?>"
             href="finance.php?tab=reports">
            <i class="fa-solid fa-file-invoice-dollar me-1"></i> Reports
          </a>
        </li>

      </ul>

      <!-- Section content -->
    <div class="tab-content-area">

    <?php if ($tab === 'collections'): ?>
        <section id="section-collections">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-heading">All Collections</h2>
            <a href="finance.php?tab=record" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i> Record Payment
            </a>
        </div>
        <p class="text-muted">Collections table goes here.</p>
        </section>

    <?php elseif ($tab === 'record'): ?>
        <section id="section-record">
        <h2 class="section-heading mb-3">Record New Payment</h2>
        <form method="POST" action="finance.php?tab=record" class="row g-3" style="max-width:640px;">
            <div class="col-md-6">
            <label class="form-label">Payment Type</label>
            <select class="form-select" name="payment_type" required>
                <option value="">Select type…</option>
                <option>Document Fee</option>
                <option>Business Permit</option>
                <option>Other</option>
            </select>
            </div>
            <div class="col-md-6">
            <label class="form-label">Amount (₱)</label>
            <input type="number" class="form-control" name="amount" min="0" step="0.01" required />
            </div>
            <div class="col-12">
            <label class="form-label">Payer Name</label>
            <input type="text" class="form-control" name="payer_name" required />
            </div>
            <div class="col-md-6">
            <label class="form-label">OR Number</label>
            <input type="text" class="form-control" name="or_number" />
            </div>
            <div class="col-md-6">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="payment_date" required />
            </div>
            <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="2"></textarea>
            </div>
            <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Payment</button>
            <a href="finance.php?tab=collections" class="btn btn-link">Cancel</a>
            </div>
        </form>
        </section>

    <?php elseif ($tab === 'receipts'): ?>
        <section id="section-receipts">
        <h2 class="section-heading mb-3">Official Receipts</h2>
        <p class="text-muted">Receipts table goes here.</p>
        </section>

    <?php elseif ($tab === 'expenditures'): ?>
        <section id="section-expenditures">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="section-heading">All Expenditures</h2>
            <a href="finance.php?tab=add-exp" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i> Add Expenditure
            </a>
        </div>
        <p class="text-muted">Expenditures table goes here.</p>
        </section>

    <?php elseif ($tab === 'add-exp'): ?>
        <section id="section-add-exp">
        <h2 class="section-heading mb-3">Add Expenditure</h2>
        <form method="POST" action="finance.php?tab=add-exp" class="row g-3" style="max-width:640px;">
            <div class="col-md-6">
            <label class="form-label">Category</label>
            <select class="form-select" name="category" required>
                <option value="">Select category…</option>
                <option>General Admin</option>
                <option>Public Services</option>
                <option>Social Services</option>
                <option>Other</option>
            </select>
            </div>
            <div class="col-md-6">
            <label class="form-label">Amount (₱)</label>
            <input type="number" class="form-control" name="amount" min="0" step="0.01" required />
            </div>
            <div class="col-12">
            <label class="form-label">Description</label>
            <input type="text" class="form-control" name="description" required />
            </div>
            <div class="col-md-6">
            <label class="form-label">Date</label>
            <input type="date" class="form-control" name="expenditure_date" required />
            </div>
            <div class="col-md-6">
            <label class="form-label">Approved By</label>
            <input type="text" class="form-control" name="approved_by" />
            </div>
            <div class="col-12">
            <label class="form-label">Remarks</label>
            <textarea class="form-control" name="remarks" rows="2"></textarea>
            </div>
            <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Expenditure</button>
            <a href="finance.php?tab=expenditures" class="btn btn-link">Cancel</a>
            </div>
        </form>
        </section>

    <?php elseif ($tab === 'budget'): ?>
        <section id="section-budget">
        <h2 class="section-heading mb-3">Budget Management</h2>
        <p class="text-muted">Budget management content goes here.</p>
        </section>

    <?php elseif ($tab === 'reports'): ?>
        <section id="section-reports">
        <h2 class="section-heading mb-3">Financial Reports</h2>
        <p class="text-muted">Financial reports content goes here.</p>
        </section>

    <?php endif; ?>

    </div>

    </main>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin.js"></script>
</body>
</html>