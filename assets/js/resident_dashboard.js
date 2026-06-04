document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  const sidebarLinks = document.querySelectorAll('.sidebar-link[href^="#"]');
  const notificationToggle = document.getElementById('notificationToggle');
  const notificationPanel = document.getElementById('notificationPanel');
  const profileToggle = document.getElementById('profileToggle');
  const profilePanel = document.getElementById('profilePanel');
  const themeToggle = document.getElementById('themeToggle');
  const requestRows = Array.from(document.querySelectorAll('#requestRows tr[data-status]'));
  const requestFilterLabel = document.getElementById('requestFilterLabel');

  const getSavedTheme = () => {
    try {
      return localStorage.getItem('residentTheme');
    } catch (error) {
      return null;
    }
  };

  const saveTheme = (theme) => {
    try {
      localStorage.setItem('residentTheme', theme);
    } catch (error) {
      // Theme preference is optional when storage is unavailable.
    }
  };

  const setTheme = (theme) => {
    const isDark = theme === 'dark';
    body.classList.toggle('dark-mode', isDark);

    if (themeToggle) {
      themeToggle.setAttribute('aria-pressed', String(isDark));
      themeToggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      const icon = themeToggle.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-moon', !isDark);
        icon.classList.toggle('fa-sun', isDark);
      }
    }
  };

  const savedTheme = getSavedTheme();
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  setTheme(savedTheme || (prefersDark ? 'dark' : 'light'));

  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const nextTheme = body.classList.contains('dark-mode') ? 'light' : 'dark';
      setTheme(nextTheme);
      saveTheme(nextTheme);
    });
  }

  const closeSidebar = () => {
    body.classList.remove('sidebar-open');
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
    if (sidebarOverlay) sidebarOverlay.hidden = true;
  };

  const openSidebar = () => {
    body.classList.add('sidebar-open');
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
    if (sidebarOverlay) sidebarOverlay.hidden = false;
  };

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
    });
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
  }

  document.querySelectorAll('.quick-action[aria-disabled="true"]').forEach((link) => {
    link.addEventListener('click', (event) => {
      event.preventDefault();
    });
  });

  sidebarLinks.forEach((link) => {
    link.addEventListener('click', () => {
      sidebarLinks.forEach((item) => item.classList.remove('is-active'));
      link.classList.add('is-active');
      closeSidebar();
    });
  });

  const closeDropdowns = (except = null) => {
    [
      [notificationPanel, notificationToggle],
      [profilePanel, profileToggle],
    ].forEach(([panel, toggle]) => {
      if (!panel || panel === except) return;
      panel.classList.remove('is-open');
      if (toggle) toggle.setAttribute('aria-expanded', 'false');
    });
  };

  const toggleDropdown = (panel, toggle) => {
    if (!panel || !toggle) return;
    const willOpen = !panel.classList.contains('is-open');
    closeDropdowns(panel);
    panel.classList.toggle('is-open', willOpen);
    toggle.setAttribute('aria-expanded', String(willOpen));
  };

  if (notificationToggle) {
    notificationToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      toggleDropdown(notificationPanel, notificationToggle);
    });
  }

  if (profileToggle) {
    profileToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      toggleDropdown(profilePanel, profileToggle);
    });
  }

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.dropdown-wrap')) {
      closeDropdowns();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeDropdowns();
      closeSidebar();
    }
  });

  const filterLabels = {
    all: 'Showing full request history.',
    pending: 'Showing pending and processing requests.',
    ready: 'Showing ready for pick-up and released requests.',
  };

  const matchesFilter = (status, filter) => {
    if (filter === 'pending') return ['pending', 'processing'].includes(status);
    if (filter === 'ready') return ['approved', 'released'].includes(status);
    return true;
  };

  document.querySelectorAll('[data-request-filter]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
      const filter = trigger.getAttribute('data-request-filter') || 'all';
      let visibleCount = 0;

      requestRows.forEach((row) => {
        const isVisible = matchesFilter(row.dataset.status || '', filter);
        row.hidden = !isVisible;
        if (isVisible) visibleCount += 1;
      });

      if (requestFilterLabel) {
        requestFilterLabel.textContent = visibleCount
          ? filterLabels[filter] || filterLabels.all
          : 'No requests match this status filter.';
      }
    });
  });

  const wizard = document.getElementById('documentRequestForm');
  if (wizard) {
    const steps = Array.from(wizard.querySelectorAll('[data-wizard-step]'));
    const pills = Array.from(wizard.querySelectorAll('[data-step-jump]'));
    const backButton = wizard.querySelector('[data-wizard-back]');
    const nextButton = wizard.querySelector('[data-wizard-next]');
    const submitButton = wizard.querySelector('[data-wizard-submit]');
    const docCards = Array.from(wizard.querySelectorAll('[data-doc-card]'));
    const attachmentInput = wizard.querySelector('#attachmentInput');
    const uploadDropzone = wizard.querySelector('#uploadDropzone');
    const uploadList = wizard.querySelector('#uploadList');
    const requirementList = wizard.querySelector('#requiredAttachmentList');
    const maxFileSize = Number(wizard.dataset.maxFileSize || 5242880);
    let currentStep = 1;
    let selectedDoc = null;

    const parseRequirements = (card) => {
      if (!card) return [];
      try {
        return JSON.parse(card.dataset.docRequirements || '[]');
      } catch (error) {
        return [];
      }
    };

    const setExtraFields = (slug) => {
      wizard.querySelectorAll('.extra-fields').forEach((group) => {
        const isActive = group.dataset.extraFor === slug;
        group.hidden = !isActive;
        group.querySelectorAll('input, select, textarea').forEach((field) => {
          field.disabled = !isActive;
          field.required = isActive;
        });
      });
    };

    const renderRequirements = () => {
      const requirements = parseRequirements(selectedDoc);
      if (!requirementList) return;
      requirementList.innerHTML = '';
      (requirements.length ? requirements : ['Valid government ID']).forEach((item) => {
        const li = document.createElement('li');
        li.textContent = item;
        requirementList.appendChild(li);
      });
    };

    const selectDocument = (card) => {
      selectedDoc = card;
      docCards.forEach((item) => item.classList.toggle('is-selected', item === card));
      const radio = card.querySelector('input[type="radio"]');
      if (radio) radio.checked = true;
      setExtraFields(card.dataset.docSlug || '');
      renderRequirements();
      updateReview();
    };

    docCards.forEach((card) => {
      const radio = card.querySelector('input[type="radio"]');
      card.addEventListener('click', () => selectDocument(card));
      if (radio) {
        radio.addEventListener('change', () => {
          if (radio.checked) selectDocument(card);
        });
        if (radio.checked) selectDocument(card);
      }
    });

    const renderUploadList = () => {
      if (!uploadList || !attachmentInput) return;
      uploadList.innerHTML = '';
      const files = Array.from(attachmentInput.files || []);
      if (!files.length) {
        updateReview();
        return;
      }

      files.forEach((file) => {
        const item = document.createElement('div');
        item.className = 'upload-item';
        const tooLarge = file.size > maxFileSize;
        item.innerHTML = `
          <span><i class="fa-solid ${tooLarge ? 'fa-triangle-exclamation' : 'fa-file-lines'}" aria-hidden="true"></i></span>
          <strong>${file.name}</strong>
          <small>${(file.size / 1048576).toFixed(1)} MB${tooLarge ? ' - over 5MB limit' : ''}</small>
        `;
        if (tooLarge) item.classList.add('is-invalid');
        uploadList.appendChild(item);
      });
      updateReview();
    };

    if (attachmentInput) {
      attachmentInput.addEventListener('change', renderUploadList);
    }

    if (uploadDropzone && attachmentInput) {
      uploadDropzone.addEventListener('click', () => attachmentInput.click());
      ['dragenter', 'dragover'].forEach((eventName) => {
        uploadDropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          uploadDropzone.classList.add('is-dragging');
        });
      });
      ['dragleave', 'drop'].forEach((eventName) => {
        uploadDropzone.addEventListener(eventName, (event) => {
          event.preventDefault();
          uploadDropzone.classList.remove('is-dragging');
        });
      });
      uploadDropzone.addEventListener('drop', (event) => {
        const droppedFiles = Array.from(event.dataTransfer.files || []);
        if (!droppedFiles.length || typeof DataTransfer === 'undefined') return;
        const transfer = new DataTransfer();
        droppedFiles.forEach((file) => transfer.items.add(file));
        attachmentInput.files = transfer.files;
        renderUploadList();
      });
    }

    function updateReview() {
      const setReview = (key, value) => {
        const target = wizard.querySelector(`[data-review="${key}"]`);
        if (target) target.textContent = value;
      };

      setReview('document', selectedDoc ? selectedDoc.dataset.docName : 'Not selected');
      const purpose = (wizard.querySelector('#requestPurpose')?.value || '').trim();
      setReview('purpose', purpose || 'Not provided');

      const extraItems = [];
      wizard.querySelectorAll('.extra-fields:not([hidden]) input, .extra-fields:not([hidden]) select').forEach((field) => {
        if (!field.value) return;
        const label = wizard.querySelector(`label[for="${field.id}"]`);
        extraItems.push(`${label ? label.textContent : field.name}: ${field.value}`);
      });
      setReview('extra', extraItems.length ? extraItems.join('; ') : 'None');

      const files = attachmentInput ? Array.from(attachmentInput.files || []).map((file) => file.name) : [];
      setReview('files', files.length ? files.join(', ') : 'No files selected');
    }

    wizard.querySelectorAll('input, textarea, select').forEach((field) => {
      field.addEventListener('input', updateReview);
      field.addEventListener('change', updateReview);
    });

    const validateCurrentStep = () => {
      const activeStep = wizard.querySelector(`[data-wizard-step="${currentStep}"]`);
      if (!activeStep) return true;

      if (currentStep === 1 && !selectedDoc) {
        const firstRadio = wizard.querySelector('input[name="doc_type_id"]');
        if (firstRadio) firstRadio.reportValidity();
        return false;
      }

      if (currentStep === 3 && attachmentInput) {
        const files = Array.from(attachmentInput.files || []);
        if (!files.length) {
          attachmentInput.setCustomValidity('Please upload at least one attachment.');
          attachmentInput.reportValidity();
          attachmentInput.setCustomValidity('');
          return false;
        }
        const oversized = files.find((file) => file.size > maxFileSize);
        if (oversized) {
          attachmentInput.setCustomValidity(`${oversized.name} is larger than 5MB.`);
          attachmentInput.reportValidity();
          attachmentInput.setCustomValidity('');
          return false;
        }
      }

      const invalid = activeStep.querySelector(':invalid');
      if (invalid) {
        invalid.reportValidity();
        return false;
      }

      return true;
    };

    const goToStep = (step) => {
      currentStep = Math.max(1, Math.min(4, step));
      steps.forEach((section) => {
        const sectionStep = Number(section.dataset.wizardStep || 0);
        section.classList.toggle('is-active', sectionStep === currentStep);
      });
      pills.forEach((pill) => {
        const pillStep = Number(pill.dataset.stepJump || 0);
        pill.classList.toggle('is-active', pillStep === currentStep);
        pill.classList.toggle('is-complete', pillStep < currentStep);
      });
      if (backButton) backButton.disabled = currentStep === 1;
      if (nextButton) nextButton.hidden = currentStep === 4;
      if (submitButton) submitButton.hidden = currentStep !== 4;
      updateReview();
    };

    if (backButton) {
      backButton.addEventListener('click', () => goToStep(currentStep - 1));
    }

    if (nextButton) {
      nextButton.addEventListener('click', () => {
        if (validateCurrentStep()) goToStep(currentStep + 1);
      });
    }

    pills.forEach((pill) => {
      pill.addEventListener('click', () => {
        const targetStep = Number(pill.dataset.stepJump || 1);
        if (targetStep <= currentStep || (targetStep === currentStep + 1 && validateCurrentStep())) {
          goToStep(targetStep);
        }
      });
    });

    wizard.addEventListener('submit', (event) => {
      if (currentStep !== 4) {
        event.preventDefault();
        if (validateCurrentStep()) goToStep(currentStep + 1);
        return;
      }
      if (!validateCurrentStep()) {
        event.preventDefault();
      }
    });

    renderRequirements();
    renderUploadList();
    goToStep(1);
  }

  const trackRows = Array.from(document.querySelectorAll('[data-track-row]'));
  const trackSearch = document.getElementById('requestSearch');
  const trackResultLabel = document.getElementById('trackResultLabel');
  const statusTabs = Array.from(document.querySelectorAll('[data-track-filter]'));

  if (trackRows.length || trackSearch || statusTabs.length) {
    let activeTrackFilter = 'all';

    const trackMatchesStatus = (status, filter) => {
      if (filter === 'pending') return status === 'pending';
      if (filter === 'processing') return ['processing', 'for_approval'].includes(status);
      if (filter === 'ready') return status === 'approved';
      if (filter === 'released') return status === 'released';
      if (filter === 'rejected') return status === 'rejected';
      return true;
    };

    const applyTrackFilters = () => {
      const search = (trackSearch?.value || '').trim().toLowerCase();
      let visibleCount = 0;

      trackRows.forEach((row) => {
        const status = row.dataset.status || '';
        const ref = row.dataset.ref || '';
        const isVisible = trackMatchesStatus(status, activeTrackFilter) && (!search || ref.includes(search));
        row.hidden = !isVisible;
        if (isVisible) visibleCount += 1;
      });

      if (trackResultLabel) {
        trackResultLabel.textContent = visibleCount
          ? `Showing ${visibleCount} matching request${visibleCount === 1 ? '' : 's'}.`
          : 'No requests match the current search or filter.';
      }
    };

    statusTabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        activeTrackFilter = tab.dataset.trackFilter || 'all';
        statusTabs.forEach((item) => item.classList.toggle('is-active', item === tab));
        applyTrackFilters();
      });
    });

    if (trackSearch) {
      trackSearch.addEventListener('input', applyTrackFilters);
    }

    trackRows.forEach((row) => {
      row.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select, textarea')) return;
        if (row.dataset.href) window.location.href = row.dataset.href;
      });
    });

    applyTrackFilters();
  }

  const caseRows = Array.from(document.querySelectorAll('[data-case-row]'));
  const caseResultLabel = document.getElementById('caseResultLabel');
  const caseTabs = Array.from(document.querySelectorAll('[data-case-filter]'));
  if (caseRows.length || caseTabs.length) {
    let activeCaseFilter = 'all';
    const caseMatchesStatus = (status, filter) => {
      if (filter === 'under_mediation') return status === 'under_mediation';
      if (filter === 'open') return status === 'open';
      if (filter === 'settled') return status === 'settled';
      if (filter === 'closed') return status === 'closed';
      return true;
    };
    const applyCaseFilters = () => {
      let visibleCount = 0;
      caseRows.forEach((row) => {
        const isVisible = caseMatchesStatus(row.dataset.status || '', activeCaseFilter);
        row.hidden = !isVisible;
        if (isVisible) visibleCount += 1;
      });
      if (caseResultLabel) {
        caseResultLabel.textContent = visibleCount
          ? `Showing ${visibleCount} matching case${visibleCount === 1 ? '' : 's'}.`
          : 'No cases match this status filter.';
      }
    };
    caseTabs.forEach((tabButton) => {
      tabButton.addEventListener('click', () => {
        activeCaseFilter = tabButton.dataset.caseFilter || 'all';
        caseTabs.forEach((item) => item.classList.toggle('is-active', item === tabButton));
        applyCaseFilters();
      });
    });
    caseRows.forEach((row) => {
      row.addEventListener('click', (event) => {
        if (event.target.closest('a, button, input, select, textarea')) return;
        if (row.dataset.href) window.location.href = row.dataset.href;
      });
    });
    applyCaseFilters();
  }

  const profileTabsRoot = document.querySelector('[data-profile-tabs]');
  if (profileTabsRoot) {
    const tabButtons = Array.from(profileTabsRoot.querySelectorAll('[data-profile-tab]'));
    const tabPanels = Array.from(profileTabsRoot.querySelectorAll('[data-profile-panel]'));
    const setProfileTab = (tabName) => {
      tabButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.profileTab === tabName));
      tabPanels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.profilePanel === tabName));
      if (window.location.hash !== `#${tabName}`) {
        history.replaceState(null, '', `#${tabName}`);
      }
    };
    tabButtons.forEach((button) => {
      button.addEventListener('click', () => setProfileTab(button.dataset.profileTab || 'personal'));
    });
    const initialTab = window.location.hash ? window.location.hash.slice(1) : 'personal';
    if (tabButtons.some((button) => button.dataset.profileTab === initialTab)) {
      setProfileTab(initialTab);
    }
  }

  if (notificationToggle?.dataset.notificationCountUrl) {
    const updateNotificationBadge = async () => {
      try {
        const response = await fetch(notificationToggle.dataset.notificationCountUrl, { headers: { Accept: 'application/json' } });
        if (!response.ok) return;
        const data = await response.json();
        const unread = Number(data.unread || 0);
        notificationToggle.querySelector('.notif-badge, .notif-dot')?.remove();
        const badge = document.createElement('span');
        if (unread > 0) {
          badge.className = 'notif-badge';
          badge.textContent = String(Math.min(unread, 9));
        } else {
          badge.className = 'notif-dot';
          badge.setAttribute('aria-hidden', 'true');
        }
        notificationToggle.appendChild(badge);
      } catch (error) {
        // Notification count refresh is optional.
      }
    };
    window.setInterval(updateNotificationBadge, 60000);
  }

  const qrScannerRoot = document.querySelector('[data-qr-scanner]');
  if (qrScannerRoot) {
    const startButton = qrScannerRoot.querySelector('[data-start-qr]');
    const video = qrScannerRoot.querySelector('#qrVideo');
    const message = qrScannerRoot.querySelector('[data-qr-message]');
    let stream = null;
    let scanTimer = null;
    const stopScanner = () => {
      if (scanTimer) window.clearInterval(scanTimer);
      if (stream) stream.getTracks().forEach((track) => track.stop());
      scanTimer = null;
      stream = null;
    };
    if (startButton && video) {
      startButton.addEventListener('click', async () => {
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
          if (message) message.textContent = 'Camera QR scanning is not supported in this browser. Enter the token manually.';
          return;
        }
        try {
          stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
          video.srcObject = stream;
          video.hidden = false;
          await video.play();
          const detector = new BarcodeDetector({ formats: ['qr_code'] });
          if (message) message.textContent = 'Point the camera at the QR code.';
          scanTimer = window.setInterval(async () => {
            try {
              const codes = await detector.detect(video);
              if (!codes.length) return;
              const token = codes[0].rawValue || '';
              const input = document.getElementById('qrToken');
              if (input) input.value = token;
              stopScanner();
              video.hidden = true;
              if (message) message.textContent = 'QR token captured. Submit to verify.';
            } catch (error) {
              // Keep scanning until the browser returns a readable frame.
            }
          }, 900);
        } catch (error) {
          if (message) message.textContent = 'Camera permission was not granted. Enter the token manually.';
        }
      });
      window.addEventListener('beforeunload', stopScanner);
    }
  }

  const closeModal = (modal) => {
    if (!modal) return;
    modal.hidden = true;
    body.classList.remove('modal-open');
  };

  document.querySelectorAll('[data-open-modal]').forEach((button) => {
    button.addEventListener('click', () => {
      const modal = document.getElementById(button.dataset.openModal || '');
      if (!modal) return;
      modal.hidden = false;
      body.classList.add('modal-open');
      const closeButton = modal.querySelector('[data-close-modal]');
      if (closeButton) closeButton.focus();
    });
  });

  document.querySelectorAll('.modal-layer').forEach((modal) => {
    modal.addEventListener('click', (event) => {
      if (event.target === modal || event.target.closest('[data-close-modal]')) {
        closeModal(modal);
      }
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      document.querySelectorAll('.modal-layer:not([hidden])').forEach(closeModal);
    }
  });

  document.querySelectorAll('.faq-item').forEach((button) => {
    button.addEventListener('click', () => {
      const answer = button.nextElementSibling;
      const expanded = button.getAttribute('aria-expanded') === 'true';
      button.setAttribute('aria-expanded', String(!expanded));
      if (answer) answer.classList.toggle('is-open', !expanded);
    });
  });
});
