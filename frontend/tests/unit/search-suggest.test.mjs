import test from 'node:test';
import assert from 'node:assert/strict';
import { formatSearchTitle, isInternalPath, ROUTE_LABELS } from '../../src/js/ui/search-suggest.js';

test('Search Suggest: formatSearchTitle cleans file extensions', () => {
  assert.equal(formatSearchTitle('medicines.html'), 'Medicines');
  assert.equal(formatSearchTitle('dashboard.php'), 'Dashboard');
  assert.equal(formatSearchTitle('app.js'), 'App');
  assert.equal(formatSearchTitle('style.css'), 'Style');
  assert.equal(formatSearchTitle('catalog.htm'), 'Medicine Catalog');
  assert.equal(formatSearchTitle('data.json'), 'Data');
});

test('Search Suggest: formatSearchTitle converts hyphens and underscores to Title Case', () => {
  assert.equal(formatSearchTitle('expired_medicines'), 'Expired Medicines');
  assert.equal(formatSearchTitle('upload-prescription.html'), 'Upload Prescription');
  assert.equal(formatSearchTitle('my_patient_appointments'), 'My Patient Appointments');
});

test('Search Suggest: formatSearchTitle uses route mapping table', () => {
  assert.equal(formatSearchTitle('/catalog'), 'Medicine Catalog');
  assert.equal(formatSearchTitle('/catalog.html'), 'Medicine Catalog');
  assert.equal(formatSearchTitle('/orders'), 'My Orders');
  assert.equal(formatSearchTitle('/admin/medicines'), 'Medicines & Inventory');
  assert.equal(formatSearchTitle('/admin/invoices'), 'Invoices');
  assert.equal(formatSearchTitle('/admin/prescriptions.html'), 'Prescription Verification');
});

test('Search Suggest: isInternalPath filters internal files and sensitive directories', () => {
  assert.equal(isInternalPath('config'), true);
  assert.equal(isInternalPath('config/database.php'), true);
  assert.equal(isInternalPath('includes/auth.php'), true);
  assert.equal(isInternalPath('api/v1/users'), true);
  assert.equal(isInternalPath('vendor/autoload.php'), true);
  assert.equal(isInternalPath('.env'), true);
  assert.equal(isInternalPath('database/schema.sql'), true);
  assert.equal(isInternalPath('tests/unit/test.js'), true);

  // Normal user-facing terms are NOT internal
  assert.equal(isInternalPath('Paracetamol 500'), false);
  assert.equal(isInternalPath('Dolo 650'), false);
  assert.equal(isInternalPath('Medicines'), false);
  assert.equal(isInternalPath('Catalog'), false);
});
