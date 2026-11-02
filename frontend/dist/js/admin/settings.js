import { api } from '../core/api.js';
import { toast } from '../core/toast.js';
import { setText } from '../core/dom.js';
import { initAdminCommon } from './admin-common.js';

document.addEventListener('DOMContentLoaded', async () => {
  const user = await initAdminCommon('settings');
  if (!user) return;

  setupEventListeners();
  await loadSettings();
});

function setupEventListeners() {
  document.getElementById('form-admin-settings')?.addEventListener('submit', handleSaveSettings);
  document.getElementById('btn-create-backup')?.addEventListener('click', handleCreateBackup);
}

async function loadSettings() {
  try {
    const res = await api.get('/admin/settings');
    const s = res?.data || {};

    setInputValue('#setting-store-name', s.shop_name || s.store_name || 'MediTrack Pharmacy');
    setInputValue('#setting-owner-name', s.owner_name || 'Pharmacist');
    setInputValue('#setting-email', s.support_email || 'support@meditrack.example');
    setInputValue('#setting-phone', s.support_phone || '+91 98765 43210');
    setInputValue('#setting-address', s.address || 'Shop No 12, Health Complex, Mumbai');
    setInputValue('#setting-gstin', s.gstin || s.gst_number || '27AABCU9603R1ZM');
    setInputValue('#setting-license', s.drug_license || s.drug_license_no || 'MH-MZ2-458921');
    setInputValue('#setting-threshold', String(s.low_stock_threshold || 10));
    setInputValue('#setting-delivery-fee', String(s.delivery_fee || 40));
  } catch (err) {
    toast.error('Failed to load system settings');
  }
}

function setInputValue(selector, val) {
  const input = document.querySelector(selector);
  if (input) input.value = val;
}

async function handleSaveSettings(e) {
  e.preventDefault();

  const payload = {
    shop_name: document.getElementById('setting-store-name')?.value.trim(),
    owner_name: document.getElementById('setting-owner-name')?.value.trim(),
    support_email: document.getElementById('setting-email')?.value.trim(),
    support_phone: document.getElementById('setting-phone')?.value.trim(),
    address: document.getElementById('setting-address')?.value.trim(),
    gst_number: document.getElementById('setting-gstin')?.value.trim(),
    drug_license_no: document.getElementById('setting-license')?.value.trim(),
    low_stock_threshold: parseInt(document.getElementById('setting-threshold')?.value, 10) || 10,
    delivery_fee: parseFloat(document.getElementById('setting-delivery-fee')?.value) || 40,
  };

  try {
    await api.put('/admin/settings', payload);
    toast.success('Pharmacy settings updated successfully');
  } catch (err) {
    toast.error(err.message || 'Failed to update settings');
  }
}

async function handleCreateBackup() {
  const statusEl = document.getElementById('backup-status-text');
  if (statusEl) statusEl.textContent = 'Triggering SQL dump job...';

  try {
    const res = await api.post('/admin/backup');
    const downloadUrl = res?.data?.download_url || '/api/v1/admin/backup/download';

    if (statusEl) statusEl.textContent = 'Backup ready. Starting download...';
    toast.success('Backup snapshot ready');
    window.open(downloadUrl, '_blank');
  } catch (err) {
    if (statusEl) statusEl.textContent = 'Backup failed.';
    toast.error(err.message || 'Failed to create database backup');
  }
}

