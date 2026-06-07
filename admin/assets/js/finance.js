/* ── Tab ─────────────────────────────────────────────────────────── */
function colSetTab(type) {
  document.getElementById('colTypeInput').value = type;
  document.getElementById('colFilterForm').submit();
}

/* ── Modal ───────────────────────────────────────────────────────── */
function colOpenModal(id)  { document.getElementById(id).classList.add('open');    }
function colCloseModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.col-backdrop').forEach(el =>
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); })
);

/* ── Toast ───────────────────────────────────────────────────────── */
function colToast(msg, type = 'ok') {
  const t = document.getElementById('colToast');
  t.textContent = msg;
  t.className   = 'show ' + type;
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.className = ''; }, 3500);
}

/* ── Record modal ────────────────────────────────────────────────── */
function colOpenRecordModal() { colOpenModal('colRecordModal'); }

/* ── Receipt ─────────────────────────────────────────────────────── */
function colBuildReceipt(d) {
  const dt = new Date(d.collected_at).toLocaleString('en-PH', {
    year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit'
  });
  const voidBanner = d.voided ? `
    <div style="background:#fee2e2;border:1.5px solid #fca5a5;border-radius:8px;
                padding:10px;margin-bottom:12px;color:#991b1b;font-weight:700;text-align:center;">
      ⛔ THIS RECEIPT HAS BEEN VOIDED
      ${d.void_reason ? `<br><small style="font-weight:400">${d.void_reason}</small>` : ''}
    </div>` : '';
  const desc = d.description ? `
    <div class="col-receipt-row">
      <span class="lbl">Description</span>
      <span class="val">${d.description}</span>
    </div>` : '';
  return `
    <div class="col-receipt-hd">
      <div class="col-brgy">Barangay Office</div>
      <div class="col-sub">Official Collection Receipt</div>
      <div class="col-or-lbl">OR Number</div>
      <div class="col-or-val">${d.or_number}</div>
    </div>
    ${voidBanner}
    <div class="col-receipt-row"><span class="lbl">Received From</span><span class="val">${d.resident_name}</span></div>
    <div class="col-receipt-row"><span class="lbl">Payment For</span><span class="val">${d.source_type}</span></div>
    ${desc}
    <div class="col-receipt-row"><span class="lbl">Date Collected</span><span class="val">${dt}</span></div>
    <div class="col-receipt-row"><span class="lbl">Collected By</span><span class="val">${d.collected_by}</span></div>
    <div class="col-receipt-total"><span>Total Paid</span><span>₱ ${d.amount}</span></div>
    <p style="text-align:center;margin-top:12px;font-size:11px;color:#94a3b8;">
      This is your official receipt. Please keep for your records.
    </p>`;
}

function colViewReceipt(d) {
  document.getElementById('colReceiptBody').innerHTML = colBuildReceipt(d);
  window._colReceiptData = d;
  colOpenModal('colReceiptModal');
}
function colPrintFromModal() {
  if (window._colReceiptData) colPrintOR(window._colReceiptData);
}
function colPrintOR(d) {
  const area = document.getElementById('colPrintArea');
  area.innerHTML = colBuildReceipt(d);
  area.style.display = 'block';
  window.print();
  area.style.display = 'none';
}

/* ── Void ────────────────────────────────────────────────────────── */
window._colVoidId = null;
function colOpenVoid(id, orNum) {
  window._colVoidId = id;

  document.getElementById('colVoidOrLabel').textContent = 'OR #' + orNum;
  document.getElementById('colVoidReason').value = '';

  colOpenModal('colVoidModal');
}

async function colSubmitVoid() {
  const reason = document.getElementById('colVoidReason').value.trim();

  if (!reason) {
    colToast('Please provide a void reason.', 'err');
    return;
  }

  const fd = new FormData();
  fd.append('_col_action', 'void');
  fd.append('id', window._colVoidId);
  fd.append('reason', reason);

  try {
    const res  = await fetch('finance_admin.php?tab=collections', {
      method: 'POST',
      body: fd
    });

    const data = await res.json();

    if (data.success) {
      colToast(data.message, 'ok');
      colCloseModal('colVoidModal');

      const row = document.querySelector(`tr[data-col-id="${window._colVoidId}"]`);

      if (row) {
        row.classList.add('col-voided');

        const orEl = row.querySelector('.col-or');
        if (orEl && !row.querySelector('.col-void-label')) {
          const badge = document.createElement('span');
          badge.className = 'col-void-label';
          badge.innerHTML = '<i class="fa-solid fa-ban"></i> VOID';
          orEl.after(document.createElement('br'), badge);
        }

        const voidBtn = row.querySelector('[title="Void Collection"]');
        if (voidBtn) voidBtn.remove();
      }
    } else {
      colToast(data.message, 'err');
    }

  } catch (e) {
    colToast('Network error. Please try again.', 'err');
  }
}

// ── Record Payment ────────────────────────────────────────────────
window.recSearchTimer = null;
let recResidentIdVal = null;
let recRequestIdVal  = null;

// Show/hide linked request row based on source type
document.addEventListener('DOMContentLoaded', () => {
  const radios = document.querySelectorAll('input[name="rec_source_type"]');
  const el = document.getElementById('recLinkedRequestRow');

  if (!radios.length || !el) return;

  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      const isDoc = radio.value === 'document_fee';
      el.style.display = isDoc ? '' : 'none';
    });
  });
});

// Search document requests
const recReqSearch = document.getElementById('recReqSearch');

if (recReqSearch) {
  recReqSearch.addEventListener('input', function () {
    clearTimeout(window.recSearchTimer);

    const q = this.value.trim();
    if (q.length < 2) {
      document.getElementById('recReqResults').classList.remove('open');
      return;
    }

    window.recSearchTimer = setTimeout(() => {
      fetch(`finance_admin.php?tab=record&_rec_search_req=1&q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
          const box = document.getElementById('recReqResults');
          if (!box) return;

          if (!data.length) {
            box.classList.remove('open');
            return;
          }

          box.innerHTML = data.map(row => `
            <div class="rec-result-item"
                 onclick="recSelectRequest(${row.id}, ${row.resident_id},
                          '${row.reference_number}', '${row.document_type}',
                          '${row.resident_name}')">
              <strong>${row.reference_number} — ${row.document_type}</strong>
              <span>${row.resident_name}</span>
            </div>`).join('');

          box.classList.add('open');
        });
    }, 300);
  });
}

function recSelectRequest(reqId, resId, refNo, docType, resName) {
  recRequestIdVal  = reqId;
  recResidentIdVal = resId;
  document.getElementById('recRequestId').value  = reqId;
  document.getElementById('recResidentId').value = resId;
  document.getElementById('recLinkedText').textContent = `${refNo} — ${docType} (${resName})`;
  document.getElementById('recLinkedPreview').style.display = 'flex';
  document.getElementById('recReqSearch').value = '';
  document.getElementById('recReqResults').classList.remove('open');
  document.getElementById('recResidentName').value = resName;
  document.getElementById('recDescription').value  = docType + ' Fee';
}

function recClearLinked() {
  recRequestIdVal  = null;
  recResidentIdVal = null;
  document.getElementById('recRequestId').value  = '';
  document.getElementById('recResidentId').value = '';
  document.getElementById('recLinkedPreview').style.display = 'none';
  document.getElementById('recReqSearch').value  = '';
  document.getElementById('recResidentName').value = '';
}

// Close search results on outside click
document.addEventListener('click', e => {
  if (!e.target.closest('.rec-search-wrap'))
    document.getElementById('recReqResults').classList.remove('open');
});

function recClearErrors() {
  ['recErrSourceType','recErrResident','recErrAmount','recErrDate','recErrDescription']
    .forEach(id => { document.getElementById(id).textContent = ''; });
  document.querySelectorAll('.rec-input').forEach(el => el.classList.remove('rec-input--error'));
}

async function recSubmit() {
  recClearErrors();

  const sourceType  = document.querySelector('input[name="rec_source_type"]:checked')?.value ?? '';
  const residentName= document.getElementById('recResidentName').value.trim();
  const amount      = parseFloat(document.getElementById('recAmount').value);
  const date        = document.getElementById('recDateCollected').value;
  const description = document.getElementById('recDescription').value.trim();
  const notes       = document.getElementById('recNotes').value.trim();

  // Client-side validation
  let hasErr = false;
  if (!sourceType) {
    document.getElementById('recErrSourceType').textContent = 'Please select a source type.';
    hasErr = true;
  }
  if (!residentName) {
    document.getElementById('recErrResident').textContent = 'Resident name is required.';
    hasErr = true;
  }
  if (!amount || amount <= 0) {
    document.getElementById('recErrAmount').textContent = 'Enter a valid amount greater than 0.';
    hasErr = true;
  }
  if (!date) {
    document.getElementById('recErrDate').textContent = 'Date collected is required.';
    hasErr = true;
  }
  if (!description) {
    document.getElementById('recErrDescription').textContent = 'Description is required.';
    hasErr = true;
  }
  if (hasErr) return;

  const btn = document.getElementById('recSubmitBtn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

  const fd = new FormData();
  fd.append('_rec_action',   'record_payment');
  fd.append('source_type',   sourceType);
  fd.append('resident_id',   document.getElementById('recResidentId').value);
  fd.append('request_id',    document.getElementById('recRequestId').value);
  fd.append('amount',        amount);
  fd.append('description',   description);
  fd.append('collected_at',  date);
  fd.append('notes',         notes);

  try {
    const res  = await fetch('finance_admin.php?tab=record', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      colToast(`Payment saved — ${data.or_number}`, 'ok');
      recReset();
      // Update OR preview
      document.getElementById('recOrPreview').textContent = data.or_number;
    } else {
      colToast(data.message, 'err');
    }
  } catch {
    colToast('Network error. Please try again.', 'err');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Payment';
  }
}

/* ── Tab ─────────────────────────────────────────────────────────── */
function colSetTab(type) {
  document.getElementById('colTypeInput').value = type;
  document.getElementById('colFilterForm').submit();
}

/* ── Modal ───────────────────────────────────────────────────────── */
function colOpenModal(id)  { document.getElementById(id).classList.add('open');    }
function colCloseModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.col-backdrop').forEach(el =>
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); })
);

/* ── Toast ───────────────────────────────────────────────────────── */
function colToast(msg, type = 'ok') {
  const t = document.getElementById('colToast');
  t.textContent = msg;
  t.className   = 'show ' + type;
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.className = ''; }, 3500);
}

/* ── Record modal ────────────────────────────────────────────────── */
function colOpenRecordModal() { colOpenModal('colRecordModal'); }

/* ── Receipt ─────────────────────────────────────────────────────── */
function colBuildReceipt(d) {
  const dt = new Date(d.collected_at).toLocaleString('en-PH', {
    year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit'
  });
  const voidBanner = d.voided ? `
    <div style="background:#fee2e2;border:1.5px solid #fca5a5;border-radius:8px;
                padding:10px;margin-bottom:12px;color:#991b1b;font-weight:700;text-align:center;">
      ⛔ THIS RECEIPT HAS BEEN VOIDED
      ${d.void_reason ? `<br><small style="font-weight:400">${d.void_reason}</small>` : ''}
    </div>` : '';
  const desc = d.description ? `
    <div class="col-receipt-row">
      <span class="lbl">Description</span>
      <span class="val">${d.description}</span>
    </div>` : '';
  return `
    <div class="col-receipt-hd">
      <div class="col-brgy">Barangay Office</div>
      <div class="col-sub">Official Collection Receipt</div>
      <div class="col-or-lbl">OR Number</div>
      <div class="col-or-val">${d.or_number}</div>
    </div>
    ${voidBanner}
    <div class="col-receipt-row"><span class="lbl">Received From</span><span class="val">${d.resident_name}</span></div>
    <div class="col-receipt-row"><span class="lbl">Payment For</span><span class="val">${d.source_type}</span></div>
    ${desc}
    <div class="col-receipt-row"><span class="lbl">Date Collected</span><span class="val">${dt}</span></div>
    <div class="col-receipt-row"><span class="lbl">Collected By</span><span class="val">${d.collected_by}</span></div>
    <div class="col-receipt-total"><span>Total Paid</span><span>₱ ${d.amount}</span></div>
    <p style="text-align:center;margin-top:12px;font-size:11px;color:#94a3b8;">
      This is your official receipt. Please keep for your records.
    </p>`;
}

function colViewReceipt(d) {
  document.getElementById('colReceiptBody').innerHTML = colBuildReceipt(d);
  window._colReceiptData = d;
  colOpenModal('colReceiptModal');
}
function colPrintFromModal() {
  if (window._colReceiptData) colPrintOR(window._colReceiptData);
}
function colPrintOR(d) {
  const area = document.getElementById('colPrintArea');
  area.innerHTML = colBuildReceipt(d);
  area.style.display = 'block';
  window.print();
  area.style.display = 'none';
}

/* ── Add Expenditure ─────────────────────────────────────────────── */
(function () {

  const expenditureForm = document.getElementById('add-exp');

  if (!expenditureForm) return;

  const THRESHOLD = 5000;

  const amountEl = document.getElementById('amount');
  const badge    = document.getElementById('approvalBadge');

  if (!amountEl || !badge) return;

  amountEl.addEventListener('input', function () {
    const val = parseFloat(this.value);

    if (!val || val <= 0) {
      badge.style.display = 'none';
      return;
    }

    badge.style.display = 'flex';

    if (val < THRESHOLD) {
      badge.className = 'approval-badge auto';
      badge.innerHTML =
        '✅ This expenditure will be <strong style="margin-left:4px">auto-approved</strong>.';
    } else {
      badge.className = 'approval-badge pending';
      badge.innerHTML =
        '🕐 This expenditure requires <strong style="margin-left:4px">Captain approval</strong>.';
    }
  });
})();
/* ── Budget Overview ─────────────────────────────────────────────── */
(function () {
  if (!document.getElementById('budget')) return;

  /* ── Category hint lookup ── */
  const BUD_CAT_HINTS = {
    'Personnel':      'Salaries, honoraria, allowances for barangay staff and officials.',
    'Supplies':       'Office supplies, ink, paper, cleaning materials, barangay hall needs.',
    'Infrastructure': 'Road repair, drainage, waiting shed construction, painting.',
    'Events':         'Barangay fiesta, health mission, programs, sports fest expenses.',
    'Maintenance':    'Repair of barangay equipment, generator, computers.',
    'Calamity Fund':  'Emergency relief, disaster response, evacuation supplies.',
    'Other':          'Miscellaneous expenses not covered by the above categories.',
  };

  /* ── Helper: show category hint in a given element ── */
  window.budUpdateCategoryHint = function (hintId, category) {
    const el = document.getElementById(hintId);
    if (!el) return;
    const hint = BUD_CAT_HINTS[category];
    if (hint) { el.textContent = hint; el.classList.add('visible'); }
    else      { el.textContent = '';   el.classList.remove('visible'); }
  };

  /* ── Helper: show formatted amount hint ── */
  window.budFormatAmountHint = function (hintId, value) {
    const el  = document.getElementById(hintId);
    if (!el) return;
    const amt = parseFloat(value);
    if (!amt || amt <= 0) { el.textContent = ''; return; }
    el.textContent = '₱' + amt.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  /* ── Add ── */
  window.budOpenAdd = function () {
    document.getElementById('budAddCategory').value     = '';
    document.getElementById('budAddDescription').value  = '';
    document.getElementById('budAddAmount').value       = '';
    document.getElementById('budAddErrFY').textContent  = '';
    document.getElementById('budAddErrCat').textContent = '';
    document.getElementById('budAddErrAmt').textContent = '';
    document.getElementById('budAddAmountHint').textContent = '';
    const hint = document.getElementById('budAddCategoryHint');
    if (hint) { hint.textContent = ''; hint.classList.remove('visible'); }
    colOpenModal('budAddModal');
  };

  window.budSubmitAdd = async function () {
    const fy   = parseInt(document.getElementById('budAddFY').value, 10);
    const cat  = document.getElementById('budAddCategory').value;
    const amt  = parseFloat(document.getElementById('budAddAmount').value);
    const desc = document.getElementById('budAddDescription').value.trim();
    let err    = false;

    document.getElementById('budAddErrFY').textContent  = '';
    document.getElementById('budAddErrCat').textContent = '';
    document.getElementById('budAddErrAmt').textContent = '';

    if (!fy || fy < 2020 || fy > new Date().getFullYear() + 1) {
      document.getElementById('budAddErrFY').textContent = 'Enter a valid fiscal year.'; err = true;
    }
    if (!cat) {
      document.getElementById('budAddErrCat').textContent = 'Please select a category.'; err = true;
    }
    if (!amt || amt <= 0) {
      document.getElementById('budAddErrAmt').textContent = 'Enter a valid amount greater than 0.'; err = true;
    }
    if (err) return;

    const btn = document.getElementById('budAddSubmitBtn');
    btn.disabled = true; btn.textContent = 'Saving…';

    const fd = new FormData();
    fd.append('_bud_action',      'add');
    fd.append('fiscal_year',      fy);
    fd.append('category',         cat);
    fd.append('description',      desc);
    fd.append('allocated_amount', amt);

    try {
      const res  = await fetch(location.href, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        colToast(data.message, 'ok');
        colCloseModal('budAddModal');
        location.reload();
      } else {
        colToast(data.message, 'err');
      }
    } catch { colToast('Network error. Please try again.', 'err'); }
    finally  { btn.disabled = false; btn.textContent = 'Add Item'; }
  };

  /* ── Edit ── */
  window.budOpenEdit = function (id, category, amount, description, fiscalYear) {
    document.getElementById('budEditId').value          = id;
    document.getElementById('budEditFY').value          = fiscalYear;
    document.getElementById('budEditCategory').value    = category;
    document.getElementById('budEditAmount').value      = amount;
    document.getElementById('budEditDescription').value = description;
    document.getElementById('budEditErrAmt').textContent = '';
    document.getElementById('budEditAmountHint').textContent =
      amount ? '₱' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '';
    budUpdateCategoryHint('budEditCategoryHint', category);
    colOpenModal('budEditModal');
  };

  window.budSubmitEdit = async function () {
    const id   = document.getElementById('budEditId').value;
    const amt  = parseFloat(document.getElementById('budEditAmount').value);
    const desc = document.getElementById('budEditDescription').value.trim();

    document.getElementById('budEditErrAmt').textContent = '';
    if (!amt || amt <= 0) {
      document.getElementById('budEditErrAmt').textContent = 'Enter a valid amount greater than 0.';
      return;
    }

    const btn = document.getElementById('budEditSubmitBtn');
    btn.disabled = true; btn.textContent = 'Saving…';

    const fd = new FormData();
    fd.append('_bud_action',      'edit');
    fd.append('id',               id);
    fd.append('allocated_amount', amt);
    fd.append('description',      desc);

    try {
      const res  = await fetch(location.href, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        colToast(data.message, 'ok');
        colCloseModal('budEditModal');
        location.reload();
      } else {
        colToast(data.message, 'err');
      }
    } catch { colToast('Network error. Please try again.', 'err'); }
    finally  { btn.disabled = false; btn.textContent = 'Save Changes'; }
  };

  /* ── Delete ── */
  window.budOpenDelete = function (id, category, spent) {
    document.getElementById('budDeleteId').value = id;
    document.getElementById('budDeleteText').textContent =
      spent > 0
        ? `"${category}" has ₱${parseFloat(spent).toLocaleString('en-PH', { minimumFractionDigits: 2 })} in linked expenditures and cannot be deleted.`
        : `Are you sure you want to delete the "${category}" budget line? This cannot be undone.`;
    document.getElementById('budDeleteSubmitBtn').style.display = spent > 0 ? 'none' : '';
    colOpenModal('budDeleteModal');
  };

  window.budSubmitDelete = async function () {
    const id  = document.getElementById('budDeleteId').value;
    const btn = document.getElementById('budDeleteSubmitBtn');
    btn.disabled = true; btn.textContent = 'Deleting…';

    const fd = new FormData();
    fd.append('_bud_action', 'delete');
    fd.append('id', id);

    try {
      const res  = await fetch(location.href, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        colToast(data.message, 'ok');
        colCloseModal('budDeleteModal');
        const row = document.querySelector(`tr[data-bud-id="${id}"]`);
        if (row) row.remove();
      } else {
        colToast(data.message, 'err');
      }
    } catch { colToast('Network error. Please try again.', 'err'); }
    finally  { btn.disabled = false; btn.textContent = 'Delete'; }
  };

  /* ── Export PDF ── */
  window.budExportPDF = function () {
    document.getElementById('budPrintArea').style.display = 'block';
    window.print();
    document.getElementById('budPrintArea').style.display = 'none';
  };
})();
