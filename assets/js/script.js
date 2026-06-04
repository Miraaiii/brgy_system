/**
 * BARANGAY STA. ROSA 1 — MIS PORTAL SYSTEM SCRIPT
 * Custom premium interactive engine for:
 * 1. Datetime Clock & Sidebar Navigation
 * 2. Profile Dropdown & Modal Form Handling
 * 3. Resident Records (Dynamic Search & Multiselect Filtering)
 * 4. Document Request Pipeline (Approve, Reject, Official Print Preview)
 * 5. Blotter Case Log & Interactive Hearings
 * 6. Financial Ledger & Budget Doughnut Charts (Chart.js)
 * 7. Projects & Programs Grid (Committee Filters)
 * 8. System Configurations, Permissions & Audit Trails
 */

// Global State
const state = {
  residents: [
    { id: "RES-2024-001", name: "Juan A. Dela Cruz", address: "142 Sampaloc St.", purok: "Purok 1 — Sampaloc", age: 34, gender: "Male", civilStatus: "Married", voterStatus: "Registered Voter", contact: "+63 917 123 4567", email: "juan.delacruz@gmail.com" },
    { id: "RES-2024-002", name: "Maria Clara S. Santos", address: "55 Narra Ave.", purok: "Purok 2 — Narra", age: 28, gender: "Female", civilStatus: "Single", voterStatus: "Registered Voter", contact: "+63 918 234 5678", email: "clara.santos@yahoo.com" },
    { id: "RES-2024-003", name: "Jose P. Rizal Jr.", address: "12 Makopa Lane", purok: "Purok 3 — Makopa", age: 45, gender: "Male", civilStatus: "Married", voterStatus: "Registered Voter", contact: "+63 919 345 6789", email: "pepe.rizal@gmail.com" },
    { id: "RES-2024-004", name: "Emily G. Francisco", address: "88 Santol Rd.", purok: "Purok 4 — Santol", age: 19, gender: "Female", civilStatus: "Single", voterStatus: "Non-Voter", contact: "+63 920 456 7890", email: "emily.f@outlook.com" },
    { id: "RES-2024-005", name: "Antonio K. Luna", address: "24 Bayabas St.", purok: "Purok 5 — Bayabas", age: 62, gender: "Male", civilStatus: "Widowed", voterStatus: "Registered Voter", contact: "+63 921 567 8901", email: "general.luna@gmail.com" },
    { id: "RES-2024-006", name: "Gabriela A. Silang", address: "19 Narra Ave.", purok: "Purok 2 — Narra", age: 50, gender: "Female", civilStatus: "Widowed", voterStatus: "Registered Voter", contact: "+63 922 678 9012", email: "gabriela.silang@gmail.com" },
    { id: "RES-2024-007", name: "Andres B. Bonifacio", address: "7 Sampaloc St.", purok: "Purok 1 — Sampaloc", age: 24, gender: "Male", civilStatus: "Single", voterStatus: "Non-Voter", contact: "+63 923 789 0123", email: "supremo@gmail.com" },
  ],
  documents: [
    { reqNum: "REQ-1092", residentId: "RES-2024-002", name: "Maria Clara S. Santos", type: "Barangay Clearance", purpose: "Employment", dateFiled: "2026-05-28", status: "Pending", fee: 50, remarks: "First time clearance application" },
    { reqNum: "REQ-1093", residentId: "RES-2024-004", name: "Emily G. Francisco", type: "Certificate of Indigency", purpose: "Scholarship Application", dateFiled: "2026-05-29", status: "Pending", fee: 0, remarks: "DSWD educational aid requirement" },
    { reqNum: "REQ-1094", residentId: "RES-2024-001", name: "Juan A. Dela Cruz", type: "Certificate of Residency", purpose: "Bank Account Opening", dateFiled: "2026-05-30", status: "Pending", fee: 50, remarks: "Valid ID matching required" },
    { reqNum: "REQ-1095", residentId: "RES-2024-005", name: "Antonio K. Luna", type: "Business Permit Clearance", purpose: "Sari-Sari Store Permit", dateFiled: "2026-05-30", status: "Pending", fee: 200, remarks: "Luna Retail Store" },
    { reqNum: "REQ-1088", name: "Jose P. Rizal Jr.", type: "Barangay Clearance", approvedBy: "Hon. Juan A. Reyes", dateReleased: "2026-05-26", status: "Approved" },
    { reqNum: "REQ-1089", name: "Andres B. Bonifacio", type: "Certificate of Residency", approvedBy: "Hon. Juan A. Reyes", dateReleased: "2026-05-27", status: "Approved" },
    { reqNum: "REQ-1085", name: "Antonio K. Luna", type: "Business Permit Clearance", reason: "Invalid Business Address Location", date: "2026-05-24", status: "Rejected" },
  ],
  blotters: [
    { caseNum: "CASE-2026-001", complainant: "Maria Clara S. Santos", respondent: "Andres B. Bonifacio", type: "Noise Complaint", dateFiled: "2026-05-20", hearingDate: "2026-06-02", priority: "Normal", status: "Ongoing", officer: "Kgd. Santos" },
    { caseNum: "CASE-2026-002", complainant: "Antonio K. Luna", respondent: "Jose P. Rizal Jr.", type: "Property Dispute", dateFiled: "2026-05-22", hearingDate: "2026-06-03", priority: "High", status: "Pending", officer: "Kgd. Mendoza" },
    { caseNum: "CASE-2026-003", complainant: "Emily G. Francisco", respondent: "Unknown", type: "Theft", dateFiled: "2026-05-25", hearingDate: "2026-05-29", priority: "Urgent", status: "Resolved", officer: "Kgd. Torres" },
  ],
  transactions: [
    { orNum: "OR-99812", date: "2026-05-28", desc: "Clearance Fees - Clara Santos", category: "Document Fees", type: "Income", amount: 50, balance: 102150 },
    { orNum: "OR-99813", date: "2026-05-29", desc: "Barangay Hall Aircon Repair", category: "Maintenance", type: "Expense", amount: 4500, balance: 97650 },
    { orNum: "OR-99814", date: "2026-05-30", desc: "IRA Allocation Q2 Received", category: "IRA Allocation", type: "Income", amount: 9000, balance: 106650 },
  ],
  projects: [
    { title: "Barangay Health Center Renovation", committee: "Health", duration: "May 1 - Jun 15, 2026", budget: 150000, status: "Ongoing", progress: 65, desc: "Renovating the primary health clinic to add maternal wellness wards." },
    { title: "Purok 2 Drainage Reconstruction", committee: "Environment", duration: "Apr 15 - May 30, 2026", budget: 180000, status: "Completed", progress: 100, desc: "Sewer and drainage line cleaning and concrete structural reinforcements." },
    { title: "Kagawad Scholarship Program", committee: "Education", duration: "Jun 1 - Jul 31, 2026", budget: 75000, status: "Planning", progress: 15, desc: "Providing financial allowance and educational aids to 50 deserving college students." },
  ],
  admins: [
    { name: "Hon. Juan A. Reyes", role: "Captain", status: "Active" },
    { name: "Ana Maria S. Clara", role: "Secretary", status: "Active" },
    { name: "Roberto T. Santos", role: "Treasurer", status: "Active" },
  ],
  activities: [
    { text: "Hon. Juan A. Reyes approved Barangay Clearance for Jose Rizal", time: "10 minutes ago", type: "success" },
    { text: "Ana Maria S. Clara added new resident records for G. Silang", time: "30 minutes ago", type: "info" },
    { text: "Roberto T. Santos recorded expense OR-99813 for maintenance", time: "1 hour ago", type: "warning" },
  ],
  alerts: [
    { text: "Urgent blotter hearing schedule for CASE-2026-002 on June 3", type: "urgent" },
    { text: "General assembly regarding clean-up drive on Saturday morning", type: "info" },
  ],
  auditLog: [
    { timestamp: "2026-05-31 18:22:04", action: "User login validated successfully.", user: "Hon. Juan A. Reyes" },
    { timestamp: "2026-05-31 18:45:12", action: "Accessed resident directory.", user: "Hon. Juan A. Reyes" },
  ]
};

// Document Ready
document.addEventListener("DOMContentLoaded", () => {
  initDateTime();
  initSidebar();
  initDropdown();
  initSectionNavigation();
  initToast();
  initCharts();
  
  // Data initial load
  renderAll();
  initFormHandlers();
  initInteractiveActions();
});

/* ============================================
   1. SYSTEM CLOCK & TIME FUNCTIONS
   ============================================ */
function initDateTime() {
  const clockEl = document.getElementById("navDatetime");
  if (!clockEl) return;
  
  const updateClock = () => {
    const now = new Date();
    const options = { 
      weekday: 'short', 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric',
      hour: '2-digit', 
      minute: '2-digit', 
      second: '2-digit',
      hour12: true 
    };
    clockEl.textContent = now.toLocaleDateString('en-PH', options);
  };
  
  updateClock();
  setInterval(updateClock, 1000);
}

/* ============================================
   2. SIDEBAR RESPONSIVE NAVIGATION
   ============================================ */
function initSidebar() {
  const body = document.querySelector("body");
  const hamburger = document.getElementById("hamburgerBtn");
  const closeBtn = document.getElementById("sidebarCloseBtn");
  const overlay = document.getElementById("sidebarOverlay");
  
  const toggleSidebar = () => {
    if (window.innerWidth >= 992) {
      body.classList.toggle("sidebar-icon-only");
    } else {
      body.classList.toggle("sidebar-mobile-show");
      overlay.classList.toggle("show");
    }
  };

  const closeMobileSidebar = () => {
    body.classList.remove("sidebar-mobile-show");
    overlay.classList.remove("show");
  };

  if (hamburger) hamburger.addEventListener("click", toggleSidebar);
  if (closeBtn) closeBtn.addEventListener("click", closeMobileSidebar);
  if (overlay) overlay.addEventListener("click", closeMobileSidebar);

  // Resize listener to prevent broken states
  window.addEventListener("resize", () => {
    if (window.innerWidth >= 992) {
      body.classList.remove("sidebar-mobile-show");
      overlay.classList.remove("show");
    }
  });
}

/* ============================================
   3. ADMIN PROFILE DROPDOWN
   ============================================ */
function initDropdown() {
  const profileBtn = document.getElementById("adminProfile");
  const dropdown = document.getElementById("profileDropdown");
  
  if (!profileBtn || !dropdown) return;
  
  profileBtn.addEventListener("click", (e) => {
    e.stopPropagation();
    dropdown.classList.toggle("show");
  });
  
  document.addEventListener("click", () => {
    dropdown.classList.remove("show");
  });
}

/* ============================================
   4. ROUTING & SECTION SWITCHING
   ============================================ */
function initSectionNavigation() {
  const navItems = document.querySelectorAll(".sidebar-nav .nav-item");
  const sections = document.querySelectorAll(".content-section");
  
  navItems.forEach(item => {
    item.addEventListener("click", (e) => {
      const sectionName = item.getAttribute("data-section");
      
      // Ignore normal link behavior for logout
      if (item.id === "logoutBtn") return;
      
      e.preventDefault();
      
      // Update sidebar active state
      navItems.forEach(i => i.classList.remove("active"));
      item.classList.add("active");
      
      // Switch visible section
      sections.forEach(s => s.classList.remove("active"));
      const targetSec = document.getElementById(`section-${sectionName}`);
      if (targetSec) targetSec.classList.add("active");
      
      // Close mobile drawer on selection
      document.querySelector("body").classList.remove("sidebar-mobile-show");
      const overlay = document.getElementById("sidebarOverlay");
      if (overlay) overlay.classList.remove("show");
      
      logAudit(`Navigated to ${sectionName.toUpperCase()} tab.`);
    });
  });
  
  // Quick redirect handler for View All Activity link
  const viewAllBtn = document.getElementById("btnViewAllActivity");
  if (viewAllBtn) {
    viewAllBtn.addEventListener("click", () => {
      const settingsNav = Array.from(navItems).find(i => i.getAttribute("data-section") === "settings");
      if (settingsNav) settingsNav.click();
    });
  }
}

/* ============================================
   5. TOAST NOTIFICATION UTILITY
   ============================================ */
let toastBS = null;
function initToast() {
  const toastEl = document.getElementById("liveToast");
  if (toastEl && typeof bootstrap !== "undefined") {
    toastBS = new bootstrap.Toast(toastEl, { delay: 3000 });
  }
}

function triggerToast(message, isError = false) {
  const toastEl = document.getElementById("liveToast");
  const toastBody = document.getElementById("toastBody");
  
  if (toastEl && toastBody) {
    toastBody.textContent = message;
    
    // Aesthetic tailoring
    if (isError) {
      toastEl.classList.remove("bg-success", "text-white");
      toastEl.classList.add("bg-danger", "text-white");
    } else {
      toastEl.classList.remove("bg-danger", "text-white");
      toastEl.classList.add("bg-success", "text-white");
    }
    
    if (toastBS) {
      toastBS.show();
    } else {
      // Fallback
      toastEl.style.opacity = 1;
      setTimeout(() => { toastEl.style.opacity = 0; }, 3000);
    }
  }
}

/* ============================================
   6. RENDER DATA FUNCTIONS
   ============================================ */
function renderAll() {
  updateDashboardCards();
  renderResidentsTable();
  renderDocsTables();
  renderBlotterTable();
  renderFinanceTable();
  renderProjectsGrid();
  renderAdminsTable();
  renderActivityLog();
  renderAlertsList();
  renderAuditTrail();
  populateRequestResidentSelect();
}

// 6.1 Dashboard Overview Stat Cards
function updateDashboardCards() {
  const totalResidentsVal = state.residents.length;
  const pendingRequestsVal = state.documents.filter(d => d.status === "Pending").length;
  const activeBlottersVal = state.blotters.filter(b => b.status !== "Resolved" && b.status !== "Dismissed").length;
  
  // Calculate total monthly revenue (collections from income transactions this month)
  const totalRevVal = state.transactions
    .filter(t => t.type === "Income")
    .reduce((acc, curr) => acc + curr.amount, 0);

  // Set values dynamically
  document.getElementById("cardTotalResidents").textContent = totalResidentsVal;
  document.getElementById("cardPendingRequests").textContent = pendingRequestsVal;
  document.getElementById("cardActiveBlotters").textContent = activeBlottersVal;
  document.getElementById("cardMonthlyRevenue").textContent = "₱" + totalRevVal.toLocaleString('en-PH');
  
  // Update badge in sidebar
  const pendingBadge = document.querySelector(".sidebar-nav .nav-badge.pending");
  if (pendingBadge) pendingBadge.textContent = pendingRequestsVal;
}

// 6.2 Residents Records Table
function renderResidentsTable() {
  const tbody = document.getElementById("residentsTableBody");
  if (!tbody) return;
  
  tbody.innerHTML = "";
  
  // Get filter settings
  const searchVal = document.getElementById("globalSearchInput").value.toLowerCase();
  const purokVal = document.getElementById("filterPurok").value;
  const genderVal = document.getElementById("filterGender").value;
  const voterVal = document.getElementById("filterVoter").value;
  const civilVal = document.getElementById("filterCivil").value;
  
  const filtered = state.residents.filter(r => {
    const matchSearch = r.name.toLowerCase().includes(searchVal) || r.id.toLowerCase().includes(searchVal) || r.address.toLowerCase().includes(searchVal);
    const matchPurok = !purokVal || r.purok === purokVal;
    const matchGender = !genderVal || r.gender === genderVal;
    const matchVoter = !voterVal || r.voterStatus === voterVal;
    const matchCivil = !civilVal || r.civilStatus === civilVal;
    
    return matchSearch && matchPurok && matchGender && matchVoter && matchCivil;
  });
  
  if (filtered.length === 0) {
    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-4">No matching resident records found.</td></tr>`;
    return;
  }
  
  filtered.forEach(r => {
    const voterBadge = r.voterStatus === "Registered Voter" ? "status-voter" : "status-nonvoter";
    
    tbody.innerHTML += `
      <tr>
        <td class="fw-bold text-primary">${r.id}</td>
        <td class="text-dark fw-semibold">${r.name}</td>
        <td>${r.address} <br><small class="text-muted">${r.purok}</small></td>
        <td>${r.age}</td>
        <td>${r.civilStatus}</td>
        <td><span class="status-badge ${voterBadge}">${r.voterStatus}</span></td>
        <td>${r.contact}</td>
        <td class="actions">
          <button class="btn-table view" onclick="editResident('${r.id}')" title="Edit Record"><i class="fa-solid fa-user-pen"></i></button>
          <button class="btn-table reject" onclick="deleteResident('${r.id}')" title="Delete Record"><i class="fa-solid fa-trash"></i></button>
        </td>
      </tr>
    `;
  });
}

// 6.3 Document Management Tables
function renderDocsTables() {
  const pendingTbody = document.getElementById("pendingDocsBody");
  const approvedTbody = document.getElementById("approvedDocsBody");
  const rejectedTbody = document.getElementById("rejectedDocsBody");
  const historyLog = document.getElementById("docHistoryLog");
  
  const pendingDocs = state.documents.filter(d => d.status === "Pending");
  const approvedDocs = state.documents.filter(d => d.status === "Approved");
  const rejectedDocs = state.documents.filter(d => d.status === "Rejected");
  
  // Update sub-tab badges
  document.getElementById("pendingDocsBadge").textContent = pendingDocs.length;
  document.getElementById("approvedDocsBadge").textContent = approvedDocs.length;
  document.getElementById("rejectedDocsBadge").textContent = rejectedDocs.length;
  
  // Render Pending
  if (pendingTbody) {
    pendingTbody.innerHTML = "";
    if (pendingDocs.length === 0) {
      pendingTbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No pending requests at this time.</td></tr>`;
    } else {
      pendingDocs.forEach(d => {
        pendingTbody.innerHTML += `
          <tr>
            <td class="fw-bold">${d.reqNum}</td>
            <td class="fw-semibold text-dark">${d.name}</td>
            <td><span class="badge bg-light text-dark border">${d.type}</span></td>
            <td>${d.purpose}</td>
            <td>${d.dateFiled}</td>
            <td><span class="status-badge status-pending">Pending</span></td>
            <td class="actions">
              <button class="btn-table approve" onclick="approveDocument('${d.reqNum}')" title="Approve Request"><i class="fa-solid fa-check me-1"></i>Approve</button>
              <button class="btn-table reject" onclick="rejectDocument('${d.reqNum}')" title="Reject Request"><i class="fa-solid fa-xmark me-1"></i>Reject</button>
            </td>
          </tr>
        `;
      });
    }
  }
  
  // Render Approved
  if (approvedTbody) {
    approvedTbody.innerHTML = "";
    if (approvedDocs.length === 0) {
      approvedTbody.innerHTML = `<tr><td colspan="6" class="text-center text-muted py-4">No approved certificates released.</td></tr>`;
    } else {
      approvedDocs.forEach(d => {
        approvedTbody.innerHTML += `
          <tr>
            <td class="fw-bold">${d.reqNum}</td>
            <td class="fw-semibold text-dark">${d.name}</td>
            <td><span class="badge bg-light text-dark border">${d.type}</span></td>
            <td>${d.approvedBy}</td>
            <td>${d.dateReleased}</td>
            <td class="actions">
              <button class="btn-table view" onclick="previewCertificate('${d.reqNum}')" title="Preview / Print"><i class="fa-solid fa-print me-1"></i>Print</button>
            </td>
          </tr>
        `;
      });
    }
  }
  
  // Render Rejected
  if (rejectedTbody) {
    rejectedTbody.innerHTML = "";
    if (rejectedDocs.length === 0) {
      rejectedTbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">No rejected requests.</td></tr>`;
    } else {
      rejectedDocs.forEach(d => {
        rejectedTbody.innerHTML += `
          <tr>
            <td class="fw-bold">${d.reqNum}</td>
            <td class="fw-semibold text-dark">${d.name}</td>
            <td><span class="badge bg-light text-dark border">${d.type}</span></td>
            <td class="text-danger">${d.reason}</td>
            <td>${d.date}</td>
          </tr>
        `;
      });
    }
  }
  
  // Render History Logs
  if (historyLog) {
    historyLog.innerHTML = "";
    const released = state.documents.filter(d => d.status === "Approved");
    if (released.length === 0) {
      historyLog.innerHTML = `<div class="text-center text-muted py-4">No issuance activity recorded.</div>`;
    } else {
      released.forEach(d => {
        historyLog.innerHTML += `
          <div class="timeline-item">
            <div class="timeline-icon success"><i class="fa-solid fa-stamp"></i></div>
            <div class="timeline-content">
              <div class="fw-semibold text-dark">Document Issued: ${d.type} (${d.reqNum})</div>
              <div class="text-muted" style="font-size:12.5px;">Issued to <strong>${d.name}</strong>, approved by Punong Barangay <strong>${d.approvedBy}</strong> on ${d.dateReleased}.</div>
            </div>
          </div>
        `;
      });
    }
  }
}

// 6.4 Blotter Logs Table
function renderBlotterTable() {
  const tbody = document.getElementById("blotterTableBody");
  if (!tbody) return;
  
  tbody.innerHTML = "";
  
  // Count stats
  const pending = state.blotters.filter(b => b.status === "Pending").length;
  const ongoing = state.blotters.filter(b => b.status === "Ongoing").length;
  const resolved = state.blotters.filter(b => b.status === "Resolved").length;
  const dismissed = state.blotters.filter(b => b.status === "Dismissed").length;
  
  document.getElementById("blotterPendingCount").textContent = pending;
  document.getElementById("blotterOngoingCount").textContent = ongoing;
  document.getElementById("blotterResolvedCount").textContent = resolved;
  document.getElementById("blotterDismissedCount").textContent = dismissed;
  
  if (state.blotters.length === 0) {
    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">No incident blotters recorded.</td></tr>`;
    return;
  }
  
  state.blotters.forEach(b => {
    let priorityBadge = "priority-normal";
    if (b.priority === "High") priorityBadge = "priority-high";
    if (b.priority === "Urgent") priorityBadge = "priority-urgent";
    
    let statusClass = "status-ongoing";
    if (b.status === "Pending") statusClass = "status-pending";
    if (b.status === "Resolved") statusClass = "status-resolved";
    if (b.status === "Dismissed") statusClass = "status-dismissed";
    
    tbody.innerHTML += `
      <tr>
        <td class="fw-bold">${b.caseNum}</td>
        <td class="text-dark fw-semibold">${b.complainant}</td>
        <td class="text-dark">${b.respondent}</td>
        <td><span class="badge bg-light text-dark border">${b.type}</span></td>
        <td>${b.dateFiled}</td>
        <td class="fw-semibold text-dark">${b.hearingDate}</td>
        <td><span class="${priorityBadge}">${b.priority}</span></td>
        <td><span class="status-badge ${statusClass}">${b.status}</span></td>
        <td class="actions">
          ${b.status !== 'Resolved' && b.status !== 'Dismissed' ? `
            <button class="btn-table approve" onclick="resolveBlotter('${b.caseNum}')" title="Mark Resolved"><i class="fa-solid fa-scale-balanced me-1"></i>Resolve</button>
            <button class="btn-table reject" onclick="dismissBlotter('${b.caseNum}')" title="Dismiss Case"><i class="fa-solid fa-ban me-1"></i>Dismiss</button>
          ` : `
            <button class="btn-table view" onclick="triggerToast('Case is already closed.')" title="Closed"><i class="fa-solid fa-lock"></i> Closed</button>
          `}
        </td>
      </tr>
    `;
  });
}

// 6.5 Financial Ledger Table
function renderFinanceTable() {
  const tbody = document.getElementById("financeTableBody");
  if (!tbody) return;
  
  tbody.innerHTML = "";
  
  // Calculate Income / Expense stats
  let totalInc = 284500; // base YTD
  let totalExp = 182350;
  
  // calculate dynamic additions
  state.transactions.forEach(t => {
    if (t.type === "Income") totalInc += t.amount;
    else totalExp += t.amount;
  });
  
  const netBal = totalInc - totalExp;
  
  document.getElementById("finTotalIncome").textContent = "₱" + totalInc.toLocaleString('en-PH');
  document.getElementById("finTotalExpenses").textContent = "₱" + totalExp.toLocaleString('en-PH');
  document.getElementById("finNetBalance").textContent = "₱" + netBal.toLocaleString('en-PH');
  
  if (state.transactions.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No finance transactions logged.</td></tr>`;
    return;
  }
  
  // Reverse array to show recent transactions first
  [...state.transactions].reverse().forEach((t, idx) => {
    const isInc = t.type === "Income";
    const typeBadge = isInc ? "bg-success-subtle text-success" : "bg-danger-subtle text-danger";
    const amountPrefix = isInc ? "+" : "-";
    
    tbody.innerHTML += `
      <tr>
        <td class="fw-bold">${t.orNum}</td>
        <td>${t.date}</td>
        <td class="fw-semibold text-dark">${t.desc}</td>
        <td><span class="badge bg-light text-dark border">${t.category}</span></td>
        <td><span class="badge ${typeBadge} fw-bold" style="font-size:11px;">${t.type}</span></td>
        <td class="fw-bold ${isInc ? 'text-success' : 'text-danger'}">${amountPrefix} ₱${t.amount.toLocaleString('en-PH')}</td>
        <td class="fw-semibold text-dark">₱${t.balance.toLocaleString('en-PH')}</td>
      </tr>
    `;
  });
}

// 6.6 Projects & Programs Grid
function renderProjectsGrid() {
  const grid = document.getElementById("projectsGrid");
  if (!grid) return;
  
  grid.innerHTML = "";
  
  // Get active filter
  const filterBtn = document.querySelector("#projectFilters .committee-btn.active");
  const filterVal = filterBtn ? filterBtn.getAttribute("data-committee") : "all";
  
  const filtered = state.projects.filter(p => filterVal === "all" || p.committee === filterVal);
  
  if (filtered.length === 0) {
    grid.innerHTML = `<div class="col-12 text-center text-muted py-5">No programs active under the selected committee.</div>`;
    return;
  }
  
  filtered.forEach(p => {
    let statusClass = "bg-primary";
    if (p.status === "Completed") statusClass = "bg-success";
    if (p.status === "Delayed") statusClass = "bg-danger";
    if (p.status === "Planning") statusClass = "bg-warning text-dark";
    
    grid.innerHTML += `
      <div class="col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm border-light" style="border-radius:12px;overflow:hidden;">
          <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center pt-3 pb-0 px-3">
            <span class="badge bg-secondary-subtle text-secondary fw-bold" style="font-size:11.5px;"><i class="fa-solid fa-tag me-1"></i>${p.committee}</span>
            <span class="badge ${statusClass}">${p.status}</span>
          </div>
          <div class="card-body px-3 py-2">
            <h5 class="card-title fw-bold text-dark mb-1" style="font-size:16px;">${p.title}</h5>
            <p class="text-muted mb-2" style="font-size:12.5px;"><i class="fa-regular fa-calendar-days me-1"></i>${p.duration}</p>
            <p class="card-text text-secondary" style="font-size:13px;line-height:1.5;">${p.desc}</p>
            
            <div class="project-budget mb-3">
              <div class="text-muted mb-1" style="font-size:11.5px;">Budget Allocation</div>
              <div class="fw-bold text-success" style="font-size:15px;">₱${p.budget.toLocaleString('en-PH')}</div>
            </div>
            
            <div class="progress-bar-wrapper">
              <div class="d-flex justify-content-between mb-1" style="font-size:12px;">
                <span>Completion progress</span>
                <span class="fw-bold text-dark">${p.progress}%</span>
              </div>
              <div class="progress" style="height: 6px;">
                <div class="progress-bar" role="progressbar" style="width: ${p.progress}%" aria-valuenow="${p.progress}" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    `;
  });
}

// 6.7 Admin Accounts Table
function renderAdminsTable() {
  const tbody = document.getElementById("adminTableBody");
  if (!tbody) return;
  
  tbody.innerHTML = "";
  
  state.admins.forEach(a => {
    const isAct = a.status === "Active";
    tbody.innerHTML += `
      <tr>
        <td class="fw-semibold text-dark">${a.name}</td>
        <td><span class="badge bg-light text-dark border">${a.role}</span></td>
        <td><span class="status-badge ${isAct ? 'status-approved' : 'status-rejected'}">${a.status}</span></td>
        <td class="actions">
          <button class="btn-table approve" onclick="toggleAdminStatus('${a.name}')" title="Toggle Status"><i class="fa-solid fa-power-off"></i></button>
        </td>
      </tr>
    `;
  });
}

// 6.8 Recent Activity Log (Dashboard)
function renderActivityLog() {
  const list = document.getElementById("activityList");
  if (!list) return;
  
  list.innerHTML = "";
  
  state.activities.slice(0, 3).forEach(act => {
    let dotColor = "var(--success)";
    if (act.type === "info") dotColor = "var(--primary)";
    if (act.type === "warning") dotColor = "var(--warning)";
    
    list.innerHTML += `
      <div class="activity-item">
        <span class="activity-dot" style="background: ${dotColor}"></span>
        <div class="activity-content">
          <div class="activity-text text-dark">${act.text}</div>
          <div class="activity-time">${act.time}</div>
        </div>
      </div>
    `;
  });
}

// 6.9 Alerts Queue List (Dashboard)
function renderAlertsList() {
  const list = document.getElementById("alertsList");
  if (!list) return;
  
  list.innerHTML = "";
  
  // Update count badge
  document.getElementById("alertsQueueBadge").textContent = state.alerts.length + " notifications";
  
  state.alerts.forEach(a => {
    let iconClass = "fa-solid fa-circle-exclamation text-primary";
    let alertClass = "info";
    if (a.type === "urgent") {
      iconClass = "fa-solid fa-triangle-exclamation text-danger";
      alertClass = "urgent";
    }
    
    list.innerHTML += `
      <div class="alert-item ${alertClass}">
        <i class="${iconClass}"></i>
        <div class="alert-text">
          <strong>${a.type === 'urgent' ? 'Urgent Notice' : 'System Information'}</strong>
          ${a.text}
        </div>
      </div>
    `;
  });
}

// 6.10 Audit Trail (Settings)
function renderAuditTrail() {
  const container = document.getElementById("auditLog");
  if (!container) return;
  
  container.innerHTML = "";
  
  // Show recent audit logs first
  [...state.auditLog].reverse().forEach(log => {
    container.innerHTML += `
      <div class="audit-item py-1" style="font-family:'DM Mono', monospace; font-size:12.5px; border-bottom:1px solid var(--border);">
        <span class="text-muted">[${log.timestamp}]</span> 
        <strong class="text-primary">${log.user}</strong>: 
        <span class="text-dark">${log.action}</span>
      </div>
    `;
  });
}

// Helper to log audit actions
function logAudit(actionString) {
  const now = new Date();
  const formatTime = now.getFullYear() + "-" + 
    String(now.getMonth()+1).padStart(2, '0') + "-" + 
    String(now.getDate()).padStart(2, '0') + " " + 
    String(now.getHours()).padStart(2, '0') + ":" + 
    String(now.getMinutes()).padStart(2, '0') + ":" + 
    String(now.getSeconds()).padStart(2, '0');
    
  state.auditLog.push({
    timestamp: formatTime,
    action: actionString,
    user: "Hon. Juan A. Reyes" // active session user
  });
  renderAuditTrail();
}

// Helper to populate resident selection in New Doc Request Modal
function populateRequestResidentSelect() {
  const select = document.getElementById("docReqResidentId");
  if (!select) return;
  
  select.innerHTML = "";
  state.residents.forEach(r => {
    select.innerHTML += `<option value="${r.id}">${r.name} (${r.id})</option>`;
  });
}

/* ============================================
   7. INTERACTIVE & CORE OPERATION ACTIONS
   ============================================ */
function initInteractiveActions() {
  // Residents Search & Live Filter change bindings
  const searchInput = document.getElementById("globalSearchInput");
  const filterPurok = document.getElementById("filterPurok");
  const filterGender = document.getElementById("filterGender");
  const filterVoter = document.getElementById("filterVoter");
  const filterCivil = document.getElementById("filterCivil");
  
  if (searchInput) searchInput.addEventListener("keyup", renderResidentsTable);
  if (filterPurok) filterPurok.addEventListener("change", renderResidentsTable);
  if (filterGender) filterGender.addEventListener("change", renderResidentsTable);
  if (filterVoter) filterVoter.addEventListener("change", renderResidentsTable);
  if (filterCivil) filterCivil.addEventListener("change", renderResidentsTable);
  
  const resetFiltersBtn = document.getElementById("btnResetFilters");
  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener("click", () => {
      if (filterPurok) filterPurok.value = "";
      if (filterGender) filterGender.value = "";
      if (filterVoter) filterVoter.value = "";
      if (filterCivil) filterCivil.value = "";
      if (searchInput) searchInput.value = "";
      renderResidentsTable();
      triggerToast("Filters cleared.");
    });
  }

  // Dashboard Refresh Bindings
  const refreshStats = document.getElementById("btnRefreshStats");
  if (refreshStats) {
    refreshStats.addEventListener("click", () => {
      triggerToast("Dashboard counters and analytics updated.");
      logAudit("Refreshed dashboard metrics.");
      renderAll();
    });
  }

  // Dashboard Export Bindings
  const exportStats = document.getElementById("btnExportStats");
  if (exportStats) {
    exportStats.addEventListener("click", () => {
      triggerToast("Preparing quarterly barangay administrative report...");
      setTimeout(() => {
        triggerToast("Report exported successfully as PDF!");
        logAudit("Exported administrative analytics report.");
      }, 1500);
    });
  }

  // Project Committee Filter Selection
  const committeeBtns = document.querySelectorAll("#projectFilters .committee-btn");
  committeeBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      committeeBtns.forEach(b => {
        b.classList.remove("btn-primary", "active");
        b.classList.add("btn-outline-secondary");
      });
      btn.classList.remove("btn-outline-secondary");
      btn.classList.add("btn-primary", "active");
      
      renderProjectsGrid();
    });
  });

  // Settings Save profile binding
  const btnSaveBgy = document.getElementById("btnSaveBgyProfile");
  if (btnSaveBgy) {
    btnSaveBgy.addEventListener("click", () => {
      const bgyName = document.getElementById("setBgyName").value;
      const bgyCaptain = document.getElementById("setBgyCaptain").value;
      
      triggerToast(`Successfully saved configurations for ${bgyName}!`);
      logAudit(`Modified Barangay Profile settings (Captain set to: ${bgyCaptain}).`);
    });
  }

  // Logout operations
  const runLogout = (e) => {
    e.preventDefault();
    triggerToast("Logging out. Good day!", false);
    logAudit("Administrative session closed.");
    setTimeout(() => {
      window.location.href = "../logout.php";
    }, 1200);
  };

  const logoutBtn = document.getElementById("logoutBtn");
  const dropdownLogoutBtn = document.getElementById("dropdownLogoutBtn");
  if (logoutBtn) logoutBtn.addEventListener("click", runLogout);
  if (dropdownLogoutBtn) dropdownLogoutBtn.addEventListener("click", runLogout);
}

// 7.1 Edit Resident Action
window.editResident = function(resId) {
  const res = state.residents.find(r => r.id === resId);
  if (res) {
    // Populate form or trigger alert
    triggerToast(`Direct DB edit mode enabled for: ${res.name}`, false);
    logAudit(`Began record editing for resident ${resId}.`);
  }
};

// 7.2 Delete Resident Action
window.deleteResident = function(resId) {
  const res = state.residents.find(r => r.id === resId);
  if (res && confirm(`Are you sure you want to permanently delete resident record for ${res.name}?`)) {
    state.residents = state.residents.filter(r => r.id !== resId);
    triggerToast("Resident record successfully purged.", false);
    logAudit(`Permanently deleted resident record: ${resId} (${res.name}).`);
    renderAll();
  }
};

// 7.3 Approve Document Action
window.approveDocument = function(reqNum) {
  const req = state.documents.find(d => d.reqNum === reqNum);
  if (req) {
    req.status = "Approved";
    req.approvedBy = "Hon. Juan A. Reyes";
    
    // date standard
    const now = new Date();
    req.dateReleased = now.toISOString().split('T')[0];
    
    // Log OR income transaction automatically
    const orVal = "OR-" + Math.floor(10000 + Math.random() * 90000);
    const balanceVal = state.transactions[state.transactions.length - 1].balance + req.fee;
    
    state.transactions.push({
      orNum: orVal,
      date: req.dateReleased,
      desc: `${req.type} Issuance Fees — ${req.name}`,
      category: "Document Fees",
      type: "Income",
      amount: req.fee,
      balance: balanceVal
    });
    
    state.activities.unshift({
      text: `Hon. Juan A. Reyes approved ${req.type} for ${req.name}`,
      time: "Just now",
      type: "success"
    });
    
    triggerToast(`Approved request ${reqNum}! Issued document released to ledger.`);
    logAudit(`Approved document request ${reqNum} (${req.type}) for ${req.name}. Recorded transaction ${orVal}.`);
    renderAll();
  }
};

// 7.4 Reject Document Action
window.rejectDocument = function(reqNum) {
  const req = state.documents.find(d => d.reqNum === reqNum);
  if (req) {
    const reason = prompt(`Provide rejection reason for ${req.name}'s request:`, "Incorrect clearance form requirements submitted");
    if (reason === null) return; // cancel
    
    req.status = "Rejected";
    req.reason = reason;
    
    const now = new Date();
    req.date = now.toISOString().split('T')[0];
    
    state.activities.unshift({
      text: `Rejected ${req.type} request from ${req.name}`,
      time: "Just now",
      type: "warning"
    });
    
    triggerToast(`Document request ${reqNum} rejected.`, true);
    logAudit(`Rejected document request ${reqNum} (${req.type}) for ${req.name}. Reason: ${reason}`);
    renderAll();
  }
};

// 7.5 Print Preview Certificate
let activeCert = null;
window.previewCertificate = function(reqNum) {
  const req = state.documents.find(d => d.reqNum === reqNum);
  if (!req) return;
  
  activeCert = req;
  const content = document.getElementById("docPreviewContent");
  if (!content) return;
  
  // Format template based on document type
  let template = "";
  const dateFormatted = new Date(req.dateReleased).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  
  if (req.type === "Barangay Clearance") {
    template = `
      <div class="text-center mb-4">
        <h3 class="fw-bold text-uppercase border-bottom pb-2" style="font-family:'DM Serif Display',serif;letter-spacing:1px;color:var(--navy);">BARANGAY CLEARANCE</h3>
      </div>
      <p class="mb-3"><strong>TO WHOM IT MAY CONCERN:</strong></p>
      <p style="text-indent: 40px; line-height: 1.8;">
        This is to certify that <strong>${req.name}</strong>, of legal age, Filipino citizen, is a bona fide resident of Barangay Sta. Rosa 1, Noveleta, Cavite, with residence address located at <strong>${req.residentId ? state.residents.find(r => r.id === req.residentId).address : 'this Barangay'}</strong>.
      </p>
      <p style="text-indent: 40px; line-height: 1.8;">
        Based on our local Lupon Tagapamayapa case files and administrative records, the aforementioned individual has <strong>NO PENDING CASE</strong> filed against him/her and is known to be of good moral character and a law-abiding citizen in this community.
      </p>
      <p style="text-indent: 40px; line-height: 1.8; margin-bottom: 40px;">
        This clearance is being issued upon his/her request for the purpose of: <strong class="text-decoration-underline">${req.purpose || 'General Reference Purposes'}</strong>.
      </p>
      <p class="mb-5">Given this <strong>${dateFormatted}</strong> at the office of the Punong Barangay, Barangay Sta. Rosa 1, Noveleta, Cavite, Philippines.</p>
    `;
  } else if (req.type === "Certificate of Residency") {
    template = `
      <div class="text-center mb-4">
        <h3 class="fw-bold text-uppercase border-bottom pb-2" style="font-family:'DM Serif Display',serif;letter-spacing:1px;color:var(--navy);">CERTIFICATE OF RESIDENCY</h3>
      </div>
      <p class="mb-3"><strong>TO WHOM IT MAY CONCERN:</strong></p>
      <p style="text-indent: 40px; line-height: 1.8;">
        This is to officially certify that <strong>${req.name}</strong> is a permanent resident of Barangay Sta. Rosa 1, Noveleta, Cavite, residing at <strong>${req.residentId ? state.residents.find(r => r.id === req.residentId).address : 'this Barangay'}</strong>.
      </p>
      <p style="text-indent: 40px; line-height: 1.8;">
        He/She has been a resident of this Barangay since birth or has been in continuous residence for over <strong>3 years</strong>.
      </p>
      <p style="text-indent: 40px; line-height: 1.8; margin-bottom: 40px;">
        This certificate is issued for whatever legal purposes and clearances it may serve.
      </p>
      <p class="mb-5">Issued on this <strong>${dateFormatted}</strong> at Barangay Sta. Rosa 1 Hall, Noveleta, Cavite, Philippines.</p>
    `;
  } else if (req.type === "Certificate of Indigency") {
    template = `
      <div class="text-center mb-4">
        <h3 class="fw-bold text-uppercase border-bottom pb-2" style="font-family:'DM Serif Display',serif;letter-spacing:1px;color:var(--navy);">CERTIFICATE OF INDIGENCY</h3>
      </div>
      <p class="mb-3"><strong>TO WHOM IT MAY CONCERN:</strong></p>
      <p style="text-indent: 40px; line-height: 1.8;">
        This is to certify that <strong>${req.name}</strong> is a resident of Barangay Sta. Rosa 1, Noveleta, Cavite. Our records indicate that his/her household family belongs to the low-income bracket or indigent sector of this community.
      </p>
      <p style="text-indent: 40px; line-height: 1.8;">
        The subject family is currently unable to meet primary basic needs due to financial difficulties.
      </p>
      <p style="text-indent: 40px; line-height: 1.8; margin-bottom: 40px;">
        This certification is issued upon request to aid the applicant in procuring assistance from the DSWD, local government agencies, health centers, or educational institutions.
      </p>
      <p class="mb-5">Issued this <strong>${dateFormatted}</strong> at the Barangay Hall of Barangay Sta. Rosa 1, Noveleta, Cavite, Philippines.</p>
    `;
  } else {
    // Business Permit Clearance
    template = `
      <div class="text-center mb-4">
        <h3 class="fw-bold text-uppercase border-bottom pb-2" style="font-family:'DM Serif Display',serif;letter-spacing:1px;color:var(--navy);">BUSINESS CLEARANCE PERMIT</h3>
      </div>
      <p class="mb-3"><strong>TO WHOM IT MAY CONCERN:</strong></p>
      <p style="text-indent: 40px; line-height: 1.8;">
        Clearance is hereby officially granted to: <strong>${req.name}</strong> to operate his/her business enterprise located at Barangay Sta. Rosa 1, Noveleta, Cavite.
      </p>
      <p style="text-indent: 40px; line-height: 1.8;">
        Subject business has complied with barangay sanitation, neighborhood codes, safety regulations, and tax requirements.
      </p>
      <p style="text-indent: 40px; line-height: 1.8; margin-bottom: 40px;">
        This Business Clearance is valid until December 31, ${new Date().getFullYear()} and is subject to cancellation upon violation of local ordinances.
      </p>
      <p class="mb-5">Issued on this <strong>${dateFormatted}</strong> at Barangay Sta. Rosa 1 Hall, Noveleta, Cavite, Philippines.</p>
    `;
  }
  
  content.innerHTML = template;
  
  // Show Bootstrap Modal
  const modalEl = document.getElementById("docPreviewModal");
  if (modalEl && typeof bootstrap !== "undefined") {
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    logAudit(`Opened print preview certificate: ${reqNum} for ${req.name}.`);
  }
};

// Bind Print Button in Preview Modal
const printDocBtn = document.getElementById("btnPrintDoc");
if (printDocBtn) {
  printDocBtn.addEventListener("click", () => {
    if (activeCert) {
      triggerToast(`Preparing queue command for printing official ${activeCert.type}...`);
      logAudit(`Sent print certificate queue command: ${activeCert.reqNum}.`);
      
      // Close modal
      const modalEl = document.getElementById("docPreviewModal");
      if (modalEl && typeof bootstrap !== "undefined") {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
    }
  });
}

// 7.6 Resolve Blotter Case Action
window.resolveBlotter = function(caseNum) {
  const caseItem = state.blotters.find(b => b.caseNum === caseNum);
  if (caseItem) {
    caseItem.status = "Resolved";
    
    state.activities.unshift({
      text: `CASE ${caseNum} resolved by assigning officer`,
      time: "Just now",
      type: "success"
    });
    
    triggerToast(`Blotter Case ${caseNum} officially resolved!`);
    logAudit(`Resolved blotter case ${caseNum} (${caseItem.type}) between ${caseItem.complainant} and ${caseItem.respondent}.`);
    renderAll();
  }
};

// 7.7 Dismiss Blotter Case Action
window.dismissBlotter = function(caseNum) {
  const caseItem = state.blotters.find(b => b.caseNum === caseNum);
  if (caseItem) {
    caseItem.status = "Dismissed";
    
    state.activities.unshift({
      text: `CASE ${caseNum} dismissed due to lack of hearing merit`,
      time: "Just now",
      type: "warning"
    });
    
    triggerToast(`Blotter Case ${caseNum} dismissed.`, true);
    logAudit(`Dismissed blotter case ${caseNum} (${caseItem.type}) between ${caseItem.complainant} and ${caseItem.respondent}.`);
    renderAll();
  }
};

// 7.8 Toggle Admin Accounts Status
window.toggleAdminStatus = function(adminName) {
  const admin = state.admins.find(a => a.name === adminName);
  if (admin) {
    admin.status = admin.status === "Active" ? "Inactive" : "Active";
    triggerToast(`Admin account for ${adminName} is now set to ${admin.status}.`);
    logAudit(`Toggled admin account status for ${adminName} to ${admin.status}.`);
    renderAll();
  }
};

/* ============================================
   8. DYNAMIC FORM SUBMISSION HANDLERS
   ============================================ */
function initFormHandlers() {
  // Add Resident Form
  const formAddRes = document.getElementById("formAddResident");
  if (formAddRes) {
    formAddRes.addEventListener("submit", (e) => {
      e.preventDefault();
      
      const first = document.getElementById("resFirstName").value.trim();
      const middle = document.getElementById("resMiddleName").value.trim();
      const last = document.getElementById("resLastName").value.trim();
      const bdate = document.getElementById("resBirthdate").value;
      const gender = document.getElementById("resGender").value;
      const civil = document.getElementById("resCivilStatus").value;
      const voter = document.getElementById("resVoterStatus").value;
      const address = document.getElementById("resAddress").value.trim();
      const purok = document.getElementById("resPurok").value;
      const contact = document.getElementById("resContact").value.trim();
      const email = document.getElementById("resEmail").value.trim();
      
      const fullname = `${first} ${middle ? middle + ' ' : ''}${last}`;
      
      // Calculate age
      const birth = new Date(bdate);
      const ageDiff = Date.now() - birth.getTime();
      const ageDate = new Date(ageDiff);
      const age = Math.abs(ageDate.getUTCFullYear() - 1970);
      
      const resId = "RES-2024-" + String(state.residents.length + 1).padStart(3, '0');
      
      state.residents.push({
        id: resId,
        name: fullname,
        address: address,
        purok: purok,
        age: age,
        gender: gender,
        civilStatus: civil,
        voterStatus: voter,
        contact: contact,
        email: email
      });
      
      state.activities.unshift({
        text: `Ana Maria S. Clara added new resident record: ${fullname}`,
        time: "Just now",
        type: "info"
      });
      
      triggerToast(`Successfully registered new resident: ${fullname}! ID: ${resId}`);
      logAudit(`Registered new resident: ${resId} (${fullname}), ${gender}, Age ${age}.`);
      
      // Reset & hide modal
      formAddRes.reset();
      const modalEl = document.getElementById("addResidentModal");
      if (modalEl && typeof bootstrap !== "undefined") {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
      
      renderAll();
    });
  }

  // New Document Request Form
  const formNewDoc = document.getElementById("formNewDocRequest");
  if (formNewDoc) {
    formNewDoc.addEventListener("submit", (e) => {
      e.preventDefault();
      
      const resId = document.getElementById("docReqResidentId").value;
      const type = document.getElementById("docReqType").value;
      const purpose = document.getElementById("docReqPurpose").value.trim();
      const fee = parseFloat(document.getElementById("docReqFee").value);
      const remarks = document.getElementById("docReqRemarks").value.trim();
      
      const res = state.residents.find(r => r.id === resId);
      const resName = res ? res.name : "Unknown Resident";
      
      const reqNum = "REQ-" + Math.floor(1000 + Math.random() * 9000);
      const now = new Date();
      const dateFiled = now.toISOString().split('T')[0];
      
      state.documents.push({
        reqNum: reqNum,
        residentId: resId,
        name: resName,
        type: type,
        purpose: purpose,
        dateFiled: dateFiled,
        status: "Pending",
        fee: fee,
        remarks: remarks
      });
      
      state.activities.unshift({
        text: `New pending request ${reqNum} filed by ${resName}`,
        time: "Just now",
        type: "info"
      });
      
      triggerToast(`Filing request ${reqNum} completed. Awaiting review.`);
      logAudit(`Created document request ${reqNum} (${type}) for resident ${resId} (${resName}).`);
      
      // Reset & hide modal
      formNewDoc.reset();
      const modalEl = document.getElementById("newDocRequestModal");
      if (modalEl && typeof bootstrap !== "undefined") {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
      
      renderAll();
    });
  }

  // New Blotter Entry Form
  const formNewBlotter = document.getElementById("formNewBlotter");
  if (formNewBlotter) {
    formNewBlotter.addEventListener("submit", (e) => {
      e.preventDefault();
      
      const complainant = document.getElementById("blotterComplainant").value.trim();
      const respondent = document.getElementById("blotterRespondent").value.trim();
      const type = document.getElementById("blotterType").value;
      const date = document.getElementById("blotterDate").value;
      const priority = document.getElementById("blotterPriority").value;
      const narrative = document.getElementById("blotterNarrative").value.trim();
      const hearing = document.getElementById("blotterHearing").value;
      const officer = document.getElementById("blotterOfficer").value;
      
      const caseNum = "CASE-2026-" + String(state.blotters.length + 1).padStart(3, '0');
      
      state.blotters.push({
        caseNum: caseNum,
        complainant: complainant,
        respondent: respondent,
        type: type,
        dateFiled: new Date().toISOString().split('T')[0],
        hearingDate: hearing,
        priority: priority,
        status: "Pending",
        officer: officer
      });
      
      state.alerts.unshift({
        text: `Urgent blotter hearing scheduled for ${caseNum} on ${hearing}`,
        type: "urgent"
      });
      
      state.activities.unshift({
        text: `New incident complaint CASE ${caseNum} filed`,
        time: "Just now",
        type: "warning"
      });
      
      triggerToast(`Successfully filed incident blotter ${caseNum}. Initial hearing set to: ${hearing}`);
      logAudit(`Filed new blotter complaint ${caseNum} (${type}) between complainant ${complainant} and respondent ${respondent}. Narrative: ${narrative.substring(0,40)}...`);
      
      // Reset & hide modal
      formNewBlotter.reset();
      const modalEl = document.getElementById("newBlotterModal");
      if (modalEl && typeof bootstrap !== "undefined") {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
      
      renderAll();
    });
  }

  // Add Transaction Form
  const formAddTx = document.getElementById("formAddTransaction");
  if (formAddTx) {
    formAddTx.addEventListener("submit", (e) => {
      e.preventDefault();
      
      const type = document.getElementById("txType").value;
      const category = document.getElementById("txCategory").value;
      const desc = document.getElementById("txDescription").value.trim();
      const amount = parseFloat(document.getElementById("txAmount").value);
      const date = document.getElementById("txDate").value;
      const reference = document.getElementById("txReference").value.trim();
      
      // Calculate new balance
      const lastBal = state.transactions[state.transactions.length - 1].balance;
      const newBal = type === "Income" ? lastBal + amount : lastBal - amount;
      
      state.transactions.push({
        orNum: reference,
        date: date,
        desc: desc,
        category: category,
        type: type,
        amount: amount,
        balance: newBal
      });
      
      state.activities.unshift({
        text: `Roberto T. Santos recorded transaction ${reference}`,
        time: "Just now",
        type: type === "Income" ? "success" : "warning"
      });
      
      triggerToast(`Successfully posted ${type} transaction: ${reference}. Current balance: ₱${newBal.toLocaleString('en-PH')}`);
      logAudit(`Recorded financial transaction: ${reference} (${type}), Category: ${category}, Amount: ₱${amount}. Description: ${desc}`);
      
      // Reset & hide modal
      formAddTx.reset();
      const modalEl = document.getElementById("addTransactionModal");
      if (modalEl && typeof bootstrap !== "undefined") {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
      
      // Re-load finance charts
      if (budgetPieChartObj) budgetPieChartObj.destroy();
      if (incomeExpChartObj) incomeExpChartObj.destroy();
      initFinanceCharts();
      
      renderAll();
    });
  }

  // Add Project Form
  const formAddProj = document.getElementById("formAddProject");
  if (formAddProj) {
    formAddProj.addEventListener("submit", (e) => {
      e.preventDefault();
      
      const title = document.getElementById("projTitle").value.trim();
      const committee = document.getElementById("projCommittee").value;
      const start = document.getElementById("projStart").value;
      const end = document.getElementById("projEnd").value;
      const budget = parseFloat(document.getElementById("projBudget").value);
      const status = document.getElementById("projStatus").value;
      const desc = document.getElementById("projDescription").value.trim();
      
      // Format duration
      const options = { month: 'short', day: 'numeric' };
      const startF = new Date(start).toLocaleDateString('en-US', options);
      const endF = new Date(end).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
      
      let progress = 0;
      if (status === "Completed") progress = 100;
      if (status === "Ongoing") progress = 30;
      if (status === "Delayed") progress = 45;
      
      state.projects.push({
        title: title,
        committee: committee,
        duration: `${startF} - ${endF}`,
        budget: budget,
        status: status,
        progress: progress,
        desc: desc
      });
      
      state.activities.unshift({
        text: `New community program posted: ${title}`,
        time: "Just now",
        type: "info"
      });
      
      triggerToast(`Community development project saved: ${title}!`);
      logAudit(`Added development project: ${title} (${committee}), Budget: ₱${budget}.`);
      
      // Reset & hide modal
      formAddProj.reset();
      const modalEl = document.getElementById("addProjectModal");
      if (modalEl && typeof bootstrap !== "undefined") {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
      
      renderAll();
    });
  }

  // Add Admin Account Form
  const formAddAdmin = document.getElementById("formAddAdmin");
  if (formAddAdmin) {
    formAddAdmin.addEventListener("submit", (e) => {
      e.preventDefault();
      
      const fullname = document.getElementById("admFullName").value.trim();
      const username = document.getElementById("admUsername").value.trim();
      const email = document.getElementById("admEmail").value.trim();
      const role = document.getElementById("admRole").value;
      const password = document.getElementById("admPassword").value;
      
      state.admins.push({
        name: fullname,
        role: role,
        status: "Active"
      });
      
      triggerToast(`Created administrative login credential for: ${fullname}`);
      logAudit(`Created new admin login account: ${fullname} (${role}), Username: ${username}, Email: ${email}.`);
      
      // Reset & hide modal
      formAddAdmin.reset();
      const modalEl = document.getElementById("addAdminModal");
      if (modalEl && typeof bootstrap !== "undefined") {
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
      }
      
      renderAll();
    });
  }
}

/* ============================================
   9. ANALYTICS CHARTS SYSTEM (CHART.JS)
   ============================================ */
let populationChartObj = null;
let docTypeChartObj = null;
let budgetPieChartObj = null;
let incomeExpChartObj = null;

function initCharts() {
  initDashboardCharts();
  initFinanceCharts();
}

function initDashboardCharts() {
  // Population Growth Chart
  const popCanvas = document.getElementById('populationChart');
  if (popCanvas) {
    const ctx = popCanvas.getContext('2d');
    populationChartObj = new Chart(ctx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [
          {
            label: 'Registered',
            data: [4500, 4580, 4650, 4710, 4773, 4821],
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,0.06)',
            fill: true,
            tension: 0.4,
            borderWidth: 2.5
          },
          {
            label: 'New Residents',
            data: [40, 80, 70, 60, 63, 48],
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34,197,94,0.06)',
            fill: true,
            tension: 0.4,
            borderWidth: 2.5
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } },
          x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } }
        }
      }
    });
  }

  // Document Requests Polar Area
  const docTypeCanvas = document.getElementById('docTypeChart');
  if (docTypeCanvas) {
    const ctx = docTypeCanvas.getContext('2d');
    docTypeChartObj = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Clearance', 'Residency', 'Indigency', 'Business'],
        datasets: [{
          data: [142, 85, 50, 35],
          backgroundColor: ['#2563eb', '#10b981', '#f59e0b', '#ef4444'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { boxWidth: 10, font: { family: 'Plus Jakarta Sans', size: 11 } }
          }
        }
      }
    });
  }
}

function initFinanceCharts() {
  // Budget Allocation Pie Chart
  const budgetCanvas = document.getElementById('budgetPieChart');
  if (budgetCanvas) {
    // Group transaction amounts by category to render
    const categories = ['Document Fees', 'IRA Allocation', 'Business Permits', 'Community Projects', 'Emergency Funds', 'Maintenance'];
    const dataSet = categories.map(cat => {
      return state.transactions
        .filter(t => t.category === cat)
        .reduce((sum, curr) => sum + curr.amount, 0) + (cat === 'IRA Allocation' ? 350000 : cat === 'Community Projects' ? 50000 : 15000); // base values
    });

    const ctx = budgetCanvas.getContext('2d');
    budgetPieChartObj = new Chart(ctx, {
      type: 'polarArea',
      data: {
        labels: ['Doc Fees', 'IRA', 'Permits', 'Projects', 'Emergency', 'Maintenance'],
        datasets: [{
          data: dataSet,
          backgroundColor: [
            'rgba(37,99,235,0.7)',
            'rgba(34,197,94,0.7)',
            'rgba(245,158,11,0.7)',
            'rgba(139,92,246,0.7)',
            'rgba(236,72,153,0.7)',
            'rgba(239,68,68,0.7)'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'right',
            labels: { boxWidth: 10, font: { family: 'Plus Jakarta Sans', size: 10.5 } }
          }
        },
        scales: {
          r: { ticks: { display: false } }
        }
      }
    });
  }

  // Monthly Income vs Expenses
  const incExpCanvas = document.getElementById('incomeExpChart');
  if (incExpCanvas) {
    const ctx = incExpCanvas.getContext('2d');
    
    // Calculate dynamic totals for the current month
    let monthlyIncome = 45000; // default average
    let monthlyExpense = 22000;
    
    state.transactions.forEach(t => {
      if (t.type === "Income") monthlyIncome += t.amount;
      else monthlyExpense += t.amount;
    });

    incomeExpChartObj = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [
          {
            label: 'Income',
            data: [35000, 42000, 39000, 48000, 41000, monthlyIncome],
            backgroundColor: '#10b981',
            borderRadius: 6
          },
          {
            label: 'Expenses',
            data: [25000, 18000, 31000, 22000, 15000, monthlyExpense],
            backgroundColor: '#ef4444',
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
            labels: { boxWidth: 12, font: { family: 'Plus Jakarta Sans', size: 11 } }
          }
        },
        scales: {
          y: { grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } },
          x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } } }
        }
      }
    });
  }
}
