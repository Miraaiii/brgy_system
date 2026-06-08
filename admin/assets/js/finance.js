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
  const recReqResults = document.getElementById('recReqResults');

  if (!recReqResults) return;

  if (!e.target.closest('.rec-search-wrap')) {
    recReqResults.classList.remove('open');
  }
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
    const res = await fetch('finance_admin.php?tab=record', {
      method: 'POST',
      body: fd
    });

    const text = await res.text();
    console.log(text);

    // TEMPORARY
    return;
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
  document.getElementById('budAddCategory').value = '';
  document.getElementById('budAddDescription').value = '';
  document.getElementById('budAddAmount').value = '';

  const errFY = document.getElementById('budAddErrFY');
  if (errFY) errFY.textContent = '';

  const errCat = document.getElementById('budAddErrCat');
  if (errCat) errCat.textContent = '';

  const errAmt = document.getElementById('budAddErrAmt');
  if (errAmt) errAmt.textContent = '';

  const amountHint = document.getElementById('budAddAmountHint');
  if (amountHint) amountHint.textContent = '';

  const hint = document.getElementById('budAddCategoryHint');
  if (hint) {
    hint.textContent = '';
    hint.classList.remove('visible');
  }

  colOpenModal('budAddModal');
};

window.budSubmitAdd = async function () {
  const fyRaw = document.getElementById('fySelect').value;
  const fy = Number(fyRaw);

  if (!Number.isInteger(fy) || fy < 2000) {
    colToast('Invalid fiscal year selected.', 'err');
    return;
  }
  const cat  = document.getElementById('budAddCategory').value;
  const amt  = parseFloat(document.getElementById('budAddAmount').value);
  const desc = document.getElementById('budAddDescription').value.trim();

  console.log("FY RAW:", document.getElementById('fySelect').value);
  console.log("FY PARSED:", fy);

  let err = false;

  document.getElementById('budAddErrCat').textContent = '';
  document.getElementById('budAddErrAmt').textContent = '';

  if (!cat) {
    document.getElementById('budAddErrCat').textContent =
      'Please select a category.';
    err = true;
  }

  if (!amt || amt <= 0) {
    document.getElementById('budAddErrAmt').textContent =
      'Enter a valid amount greater than 0.';
    err = true;
  }

  if (err) return;

  const btn = document.getElementById('budAddSubmitBtn');
  btn.disabled = true;
  btn.textContent = 'Saving…';

  const fd = new FormData();
  fd.append('_bud_action', 'add');
  fd.append('fiscal_year', fy);
  fd.append('category', cat);
  fd.append('description', desc);
  fd.append('allocated_amount', amt);

  try {
    const res = await fetch(location.href, {
      method: 'POST',
      body: fd
    });

    const data = await res.json();

    if (data.success) {
      colToast(data.message, 'ok');
      colCloseModal('budAddModal');
      location.reload();
    } else {
      colToast(data.message, 'err');
    }
  } catch {
    colToast('Network error. Please try again.', 'err');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Add Item';
  }
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

/* REPORTS JS */
const RPT_DESCS = {
  'monthly-collections': 'OR numbers, collection amounts, and source type for the selected month. Exportable as PDF and CSV.',
  'monthly-expenditures': 'All expenditures for the selected month — category, payee, amount, and approval status. PDF and CSV export.',
  'monthly-summary': 'Income vs Expenses for the month, net surplus or deficit, and month-over-month comparison. PDF only.',
  'annual-budget': 'Full-year budget vs actual spending per category with % utilization per line item. PDF only.',
  'annual-income': 'Full-year total collections by source type vs total expenditures by category, and net balance. PDF only.',
};
 
const RPT_MONTHLY = ['monthly-collections','monthly-expenditures','monthly-summary'];
const RPT_CSV_OK  = ['monthly-collections','monthly-expenditures'];
 
const MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
 
function rptHandleTypeChange(val){
  const desc    = document.getElementById('rpt-desc');
  const mWrap   = document.getElementById('rpt-month-wrap');
  const actions = document.getElementById('rpt-actions');
  const pdfBtn  = document.getElementById('rpt-pdf-btn');
  const csvBtn  = document.getElementById('rpt-csv-btn');

  if (!desc || !mWrap || !actions || !pdfBtn || !csvBtn) {
      return;
  }

  if(!val){
      desc.style.display = 'none';
      actions.style.display = 'none';
      return;
  }

  desc.textContent = RPT_DESCS[val] || '';
  desc.style.display = 'block';
  mWrap.style.display = RPT_MONTHLY.includes(val) ? 'flex' : 'none';
  actions.style.display = 'flex';
  csvBtn.style.display = RPT_CSV_OK.includes(val) ? 'inline-flex' : 'none';
}
 
function rptSelectType(val){
  const sel = document.getElementById('rpt-type');
  sel.value = val;
  rptHandleTypeChange(val);
  sel.scrollIntoView({behavior:'smooth',block:'center'});
}
 
/* ── Mock data generators ── */
function rptMockData(type){
  if(type==='monthly-collections') return {
    head:['OR No.','Date','Taxpayer / Resident','Source Type','Amount (₱)'],
    rows:[
      ['OR-2025-0101','June 1','Dela Cruz, Juan','Real Property Tax','1,500.00'],
      ['OR-2025-0102','June 2','Santos, Maria','Business Permit','3,200.00'],
      ['OR-2025-0103','June 4','Reyes, Pedro','Community Tax Cert.','50.00'],
      ['OR-2025-0104','June 5','Garcia, Ana','Barangay Clearance','100.00'],
      ['OR-2025-0105','June 7','Torres, Luis','Building Permit','800.00'],
    ],
    foot:['','','','TOTAL','5,650.00'],
    amtCol:4,
  };
  if(type==='monthly-expenditures') return {
    head:['Date','Category','Payee','Particulars','Amount (₱)','Status'],
    rows:[
      ['June 2','Office Supplies','ABC Trading','Bond paper, pens, folders','1,200.00','approved'],
      ['June 5','Maintenance','J. Reyes Services','Basketball court repair','8,500.00','approved'],
      ['June 10','Utilities','MERALCO','Electricity – Purok Hall','3,100.00','approved'],
      ['June 12','Health Program','City Health Office','Vitamins distribution','4,500.00','pending'],
      ['June 15','Calamity Fund','BFP Station 3','Fire prevention supplies','2,000.00','approved'],
    ],
    foot:['','','','','TOTAL','19,300.00'],
    amtCol:4, statusCol:5,
  };
  if(type==='monthly-summary') return {
    head:['Category','This Month (₱)','Last Month (₱)','Change'],
    rows:[
      ['Total Collections','5,650.00','4,900.00','+750.00'],
      ['Total Expenditures','19,300.00','17,500.00','+1,800.00'],
      ['Net Surplus / (Deficit)','(13,650.00)','(12,600.00)','(1,050.00)'],
    ],
    foot:null, amtCol:-1,
  };
  if(type==='annual-budget') return {
    head:['Budget Category','Appropriated (₱)','Actual Spent (₱)','Balance (₱)','Utilization %'],
    rows:[
      ['Personal Services','240,000.00','192,400.00','47,600.00','80.2%'],
      ['Maintenance & Operating Exp.','180,000.00','143,200.00','36,800.00','79.6%'],
      ['Capital Outlay','120,000.00','87,500.00','32,500.00','72.9%'],
      ['Health & Nutrition','60,000.00','54,100.00','5,900.00','90.2%'],
      ['Peace & Order','40,000.00','31,800.00','8,200.00','79.5%'],
      ['Calamity Fund','50,000.00','12,000.00','38,000.00','24.0%'],
    ],
    foot:['TOTAL','690,000.00','521,000.00','169,000.00','75.5%'],
    amtCol:-1,
  };
  if(type==='annual-income') return {
    head:['Source / Category','Amount (₱)'],
    rows:[
      ['INCOME',''],
      ['  Real Property Tax','87,500.00'],
      ['  Business Permits','124,200.00'],
      ['  Community Tax Certificates','18,400.00'],
      ['  Barangay Clearances & Fees','32,100.00'],
      ['  IRA Share','310,000.00'],
      ['Total Income','572,200.00'],
      ['EXPENDITURES',''],
      ['  Personal Services','192,400.00'],
      ['  Maintenance & Operating Exp.','143,200.00'],
      ['  Capital Outlay','87,500.00'],
      ['  Health & Nutrition','54,100.00'],
      ['  Peace & Order','31,800.00'],
      ['  Calamity Fund','12,000.00'],
      ['Total Expenditures','521,000.00'],
      ['NET BALANCE','51,200.00'],
    ],
    foot:null, amtCol:1,
  };
}
 
async function rptPreview() {
  const type  = document.getElementById('rpt-type').value;
  const month = parseInt(document.getElementById('rpt-month').value);
  const year  = document.getElementById('rpt-year').value;

  if (!type) {
    rptToast('Please select a report type first.', 'warning');
    return;
  }

  if (type !== 'monthly-summary') {
    rptRenderMock(type, month, year);
    return;
  }

  rptToast('Loading report data…');

  try {
    const res = await fetch(
      `finance_admin.php?action=report&export=preview&month=${month}&year=${year}`
    );

    if (!res.ok) throw new Error('Server error ' + res.status);

    const data = await res.json();

    if (data.error) throw new Error(data.error);

    rptRenderSummary(data, month, year);

  } catch (err) {
    rptToast('Failed to load report: ' + err.message, 'warning');
  }
}
 
function rptClosePreview(){
  document.getElementById('rpt-preview-panel').style.display='none';
}
 
function rptRenderMock(type, month, year) {
  const data    = rptMockData(type);
  const isAnnual = !RPT_MONTHLY.includes(type);
  const period  = isAnnual ? `Fiscal Year ${year}` : `${MONTHS[month]} ${year}`;
  const names   = {
    'monthly-collections' : 'Monthly Collections Report',
    'monthly-expenditures': 'Monthly Expenditures Report',
    'annual-budget'       : 'Annual Budget Utilization',
    'annual-income'       : 'Annual Income Statement',
  };
  const today = new Date().toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });

  rptFillMeta(names[type], period, today);

  const thead = document.getElementById('rpt-thead');
  const tbody = document.getElementById('rpt-tbody');
  const tfoot = document.getElementById('rpt-tfoot');

  thead.innerHTML = '<tr>' + data.head.map(h => `<th>${h}</th>`).join('') + '</tr>';
  tbody.innerHTML = data.rows.map(row =>
    '<tr>' + row.map((cell, ci) => {
      if (data.statusCol !== undefined && ci === data.statusCol) {
        const lc = cell.toLowerCase();
        return `<td><span class="rpt-badge rpt-badge--${lc}">${cell.charAt(0).toUpperCase() + cell.slice(1)}</span></td>`;
      }
      return `<td class="${ci === data.amtCol ? 'rpt-amount' : ''}">${cell}</td>`;
    }).join('') + '</tr>'
  ).join('');
  tfoot.innerHTML = data.foot
    ? '<tr>' + data.foot.map((c, i) => `<td class="${i === data.amtCol ? 'rpt-amount' : ''}">${c}</td>`).join('') + '</tr>'
    : '';

  rptShowPanel(names[type] + ' — ' + period);
}

function rptRenderSummary(data, month, year) {
  const period = `${MONTHS[month]} ${year}`;
  rptFillMeta('Monthly Financial Summary', period, data.generated);

  const thead = document.getElementById('rpt-thead');
  const tbody = document.getElementById('rpt-tbody');
  const tfoot = document.getElementById('rpt-tfoot');

  thead.innerHTML = '<tr><th>Category</th><th class="rpt-amount">Transactions</th><th class="rpt-amount">Amount (₱)</th></tr>';

  let rows = '';

  // Collections section header
  rows += `<tr><td colspan="3" style="background:var(--brand-soft);color:var(--brand);font-weight:700;padding:8px 14px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;">Collections</td></tr>`;
  data.collections.forEach((r, i) => {
    rows += `<tr class="${i % 2 === 1 ? 'rpt-row-shade' : ''}">
      <td>${r.label}</td>
      <td class="rpt-amount">${r.count}</td>
      <td class="rpt-amount">${r.amount}</td>
    </tr>`;
  });
  rows += `<tr class="rpt-subtotal">
    <td><strong>Total Collections</strong></td>
    <td></td>
    <td class="rpt-amount"><strong>${data.totals.collections}</strong></td>
  </tr>`;

  // Expenditures section header
  rows += `<tr><td colspan="3" style="background:var(--amber-soft);color:var(--amber);font-weight:700;padding:8px 14px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;">Expenditures</td></tr>`;
  data.expenditures.forEach((r, i) => {
    rows += `<tr class="${i % 2 === 1 ? 'rpt-row-shade' : ''}">
      <td>${r.label}</td>
      <td class="rpt-amount">${r.count}</td>
      <td class="rpt-amount">${r.amount}</td>
    </tr>`;
  });
  rows += `<tr class="rpt-subtotal">
    <td><strong>Total Expenditures</strong></td>
    <td></td>
    <td class="rpt-amount"><strong>${data.totals.expenditures}</strong></td>
  </tr>`;

  tbody.innerHTML = rows;

  // Net balance in tfoot
  const netColor = data.totals.net_positive ? 'var(--teal)' : 'var(--danger)';
  tfoot.innerHTML = `<tr>
    <td colspan="2" style="color:${netColor};font-weight:700;">${data.totals.net_label}</td>
    <td class="rpt-amount" style="color:${netColor};font-weight:700;">₱ ${data.totals.net_balance}</td>
  </tr>`;

  rptShowPanel('Monthly Financial Summary — ' + period);
}

function rptFillMeta(reportName, period, generatedDate) {
  document.getElementById('rpt-meta-row').innerHTML = [
    { k: 'Report',         v: reportName },
    { k: 'Period Covered', v: period },
    { k: 'Date Generated', v: generatedDate },
    { k: 'Prepared By',    v: 'Juan Dela Cruz, Treasurer' },
  ].map(m => `<div class="rpt-meta-item"><span class="rpt-meta-key">${m.k}</span><span class="rpt-meta-val">${m.v}</span></div>`).join('');
}

function rptShowPanel(label) {
  document.getElementById('rpt-preview-label').textContent = label;
  const panel = document.getElementById('rpt-preview-panel');
  panel.style.display = 'block';
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function rptExport(fmt) {
  const type  = document.getElementById('rpt-type').value;
  const month = document.getElementById('rpt-month').value;
  const year  = document.getElementById('rpt-year').value;

  if (!type) {
    rptToast('Please select a report type first.', 'warning');
    return;
  }

  // Only monthly-summary has a real PHP backend right now
  if (type !== 'monthly-summary') {
    rptToast(fmt.toUpperCase() + ' export started — file will download shortly.', 'success');
    return;
  }

  window.location.href =
    `finance_admin.php?action=report&type=${type}&month=${month}&year=${year}&export=${fmt}`;
}
 
function rptPrint(){
  const type = document.getElementById('rpt-type').value;
  if(!type){ rptToast('Please generate a preview first.','warning'); return; }
  window.print();
}
 
function rptToast(msg, type=''){
  const el = document.getElementById('colToast');
  el.textContent = msg;
  el.className = 'rpt-toast'+(type?' '+type:'');
  void el.offsetWidth;
  el.classList.add('show');
  setTimeout(()=>el.classList.remove('show'),3200);
}
 
/* init — hide month selector for annual types on load */
document.addEventListener('DOMContentLoaded',()=>rptHandleTypeChange(''));
