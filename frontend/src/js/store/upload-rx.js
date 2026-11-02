import { api } from '../core/api.js';
import { el, append, clear, setText } from '../core/dom.js';
import { createStatusBadge } from '../ui/components.js';
import { toast } from '../core/toast.js';

import { auth } from '../core/auth.js';

let selectedFiles = [];

document.addEventListener('DOMContentLoaded', async () => {
  await auth.init();

  const form = document.getElementById('rx-upload-form');
  const dropZone = document.getElementById('rx-dropzone');
  const fileInput = document.getElementById('rx-file-input');
  const fileListContainer = document.getElementById('rx-selected-files');
  const submitBtn = document.getElementById('rx-submit-btn');
  const historyContainer = document.getElementById('rx-history-container');

  // 1. Setup Drag and drop + File Input
  if (dropZone && fileInput) {
    dropZone.addEventListener('click', () => fileInput.click());

    dropZone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropZone.classList.add('border-primary-500', 'bg-primary-50/20');
    });

    dropZone.addEventListener('dragleave', () => {
      dropZone.classList.remove('border-primary-500', 'bg-primary-50/20');
    });

    dropZone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropZone.classList.remove('border-primary-500', 'bg-primary-50/20');
      if (e.dataTransfer?.files) {
        handleFilesSelected(Array.from(e.dataTransfer.files));
      }
    });

    fileInput.addEventListener('change', () => {
      if (fileInput.files) {
        handleFilesSelected(Array.from(fileInput.files));
      }
    });
  }

  function handleFilesSelected(files) {
    const validFiles = files.filter((f) => {
      const isAllowed = ['image/jpeg', 'image/png', 'application/pdf'].includes(f.type) ||
        /\.(jpe?g|png|pdf)$/i.test(f.name);
      const isSizeOk = f.size <= 5 * 1024 * 1024; // 5 MB

      if (!isAllowed) {
        toast.error(`"${f.name}" is not a supported format (JPG, PNG, PDF only)`);
        return false;
      }
      if (!isSizeOk) {
        toast.error(`"${f.name}" exceeds maximum allowed file size of 5 MB`);
        return false;
      }
      return true;
    });

    selectedFiles = [...selectedFiles, ...validFiles].slice(0, 5); // max 5 files
    renderSelectedFiles();
  }

  function renderSelectedFiles() {
    clear(fileListContainer);

    if (selectedFiles.length === 0) {
      if (submitBtn) submitBtn.disabled = true;
      return;
    }

    if (submitBtn) submitBtn.disabled = false;

    selectedFiles.forEach((file, idx) => {
      const item = el(
        'div',
        {
          className:
            'px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs flex items-center justify-between gap-3',
        },
        [
          el('div', { className: 'flex items-center gap-2 truncate' }, [
            el('span', {}, [file.type === 'application/pdf' ? '📄' : '🖼️']),
            el('span', { className: 'truncate font-medium' }, [file.name]),
            el('span', { className: 'text-slate-400 text-[10px]' }, [
              `(${(file.size / (1024 * 1024)).toFixed(2)} MB)`,
            ]),
          ]),
          el(
            'button',
            {
              type: 'button',
              className: 'text-red-500 hover:text-red-700 font-bold text-sm leading-none px-1',
              onClick: () => {
                selectedFiles.splice(idx, 1);
                renderSelectedFiles();
              },
            },
            ['×']
          ),
        ]
      );
      append(fileListContainer, item);
    });
  }

  // 2. Handle Form Submit
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (selectedFiles.length === 0) {
        toast.warning('Please select at least one prescription file.');
        return;
      }

      const patientNameInput = document.getElementById('rx-patient-name');
      const notesInput = document.getElementById('rx-notes');

      const formData = new FormData();
      selectedFiles.forEach((f) => formData.append('prescriptions[]', f));
      if (patientNameInput && patientNameInput.value.trim()) {
        formData.append('patient_name', patientNameInput.value.trim());
      }
      if (notesInput && notesInput.value.trim()) {
        formData.append('notes', notesInput.value.trim());
      }

      try {
        if (submitBtn) {
          submitBtn.disabled = true;
          setText(submitBtn, 'Uploading & Encrypting...');
        }

        await api.post('/prescriptions', formData);

        toast.success('Prescription uploaded successfully for pharmacist verification!');
        selectedFiles = [];
        renderSelectedFiles();
        if (patientNameInput) patientNameInput.value = '';
        if (notesInput) notesInput.value = '';
        if (fileInput) fileInput.value = '';

        await loadPrescriptionHistory();
      } catch (err) {
        toast.error(err.message || 'Failed to upload prescription');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          setText(submitBtn, 'Submit Prescription for Review');
        }
      }
    });
  }

  // 3. Load Prescription History
  async function loadPrescriptionHistory() {
    if (!historyContainer) return;

    try {
      const resp = await api.get('/prescriptions');
      const prescriptions = resp?.data || [];

      clear(historyContainer);

      if (prescriptions.length === 0) {
        historyContainer.appendChild(
          el('div', { className: 'py-10 text-center text-xs text-slate-400' }, [
            'No prescriptions uploaded yet. When you upload prescriptions, their review status will appear here.',
          ])
        );
        return;
      }

      prescriptions.forEach((rx) => {
        const card = el(
          'div',
          {
            className:
              'p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-soft flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs transition',
          },
          [
            el('div', { className: 'space-y-1 flex-1' }, [
              el('div', { className: 'flex items-center gap-2 flex-wrap' }, [
                el('span', { className: 'font-bold text-slate-900 dark:text-white text-sm' }, [
                  rx.patient_name ? `Patient: ${rx.patient_name}` : `Prescription #${rx.id}`,
                ]),
                createStatusBadge(rx.status),
              ]),
              el('p', { className: 'text-slate-400 text-[11px]' }, [
                `Uploaded on ${new Date(rx.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}`,
              ]),
              rx.status === 'rejected' && rx.rejection_reason
                ? el('div', { className: 'mt-2 p-2.5 rounded-xl bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 border border-red-200 dark:border-red-800' }, [
                    `Rejection Reason: ${rx.rejection_reason}`,
                  ])
                : null,
              rx.status === 'approved' && rx.reviewed_by_name
                ? el('p', { className: 'text-emerald-700 dark:text-emerald-400 text-[11px] font-medium' }, [
                    `✓ Approved by Pharmacist ${rx.reviewed_by_name} (${rx.pharmacist_reg_no || 'Reg. Verified'})`,
                  ])
                : null,
            ]),

            // View Files link
            el('div', { className: 'flex items-center gap-2 shrink-0' }, [
              (rx.files || []).map((file, idx) =>
                el(
                  'a',
                  {
                    href: `/api/v1/prescriptions/${rx.id}/files/${file.id || idx}`,
                    target: '_blank',
                    rel: 'noopener noreferrer',
                    className:
                      'px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-semibold flex items-center gap-1 transition',
                  },
                  ['👁 View File']
                )
              ),
            ]),
          ]
        );
        historyContainer.appendChild(card);
      });
    } catch (_) {}
  }

  await loadPrescriptionHistory();
});

