import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/status_chip.dart';
import '../controllers/prescription_controller.dart';

class PrescriptionsScreen extends ConsumerStatefulWidget {
  const PrescriptionsScreen({super.key});

  @override
  ConsumerState<PrescriptionsScreen> createState() => _PrescriptionsScreenState();
}

class _PrescriptionsScreenState extends ConsumerState<PrescriptionsScreen> {
  final ImagePicker _picker = ImagePicker();

  Future<void> _pickAndUpload(ImageSource source) async {
    try {
      final picked = await _picker.pickImage(source: source, imageQuality: 80);
      if (picked != null) {
        final rxCtrl = ref.read(prescriptionControllerProvider.notifier);
        final uploaded = await rxCtrl.uploadPrescription(File(picked.path));

        if (uploaded != null && mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Prescription uploaded successfully')),
          );
        }
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final rxState = ref.watch(prescriptionControllerProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('My Prescriptions')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.read(prescriptionControllerProvider.notifier).fetchPrescriptions(),
        child: rxState.prescriptions.isEmpty
            ? (rxState.isLoading
                ? const Center(child: CircularProgressIndicator.adaptive())
                : Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.document_scanner_outlined, size: 64, color: Colors.grey),
                        const SizedBox(height: 16),
                        const Text('No prescriptions uploaded yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        const Text('Upload doctor prescriptions to quickly order Rx medicines.'),
                        const SizedBox(height: 20),
                        ElevatedButton.icon(
                          icon: const Icon(Icons.upload_file),
                          label: const Text('Upload Prescription'),
                          onPressed: () => _showPickerSheet(),
                        ),
                      ],
                    ),
                  ))
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: rxState.prescriptions.length,
                separatorBuilder: (context, index) => const SizedBox(height: 12),
                itemBuilder: (context, i) {
                  final rx = rxState.prescriptions[i];
                  return Card3D(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Prescription #${rx.id}',
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                            ),
                            StatusChip(status: rx.status),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Uploaded on ${rx.createdAt}',
                          style: theme.textTheme.bodySmall,
                        ),
                        if (rx.isApproved && rx.pharmacistName != null) ...[
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: const Color(0xFFD1FAE5),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.verified, size: 16, color: Color(0xFF047857)),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    'Approved by ${rx.pharmacistName} (Reg #${rx.pharmacistRegNo ?? ""})',
                                    style: const TextStyle(color: Color(0xFF065F46), fontSize: 12, fontWeight: FontWeight.w600),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                        if (rx.isRejected && rx.rejectionReason != null) ...[
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEE2E2),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.error_outline, size: 16, color: Color(0xFFDC2626)),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    'Rejected: ${rx.rejectionReason}',
                                    style: const TextStyle(color: Color(0xFF991B1B), fontSize: 12, fontWeight: FontWeight.w600),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ],
                    ),
                  );
                },
              ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        icon: const Icon(Icons.add_a_photo_outlined),
        label: const Text('Upload Rx'),
        onPressed: _showPickerSheet,
      ),
    );
  }

  void _showPickerSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text('Upload Prescription', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.camera_alt_outlined),
                    label: const Text('Camera'),
                    onPressed: () {
                      Navigator.pop(ctx);
                      _pickAndUpload(ImageSource.camera);
                    },
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: OutlinedButton.icon(
                    icon: const Icon(Icons.photo_library_outlined),
                    label: const Text('Gallery'),
                    onPressed: () {
                      Navigator.pop(ctx);
                      _pickAndUpload(ImageSource.gallery);
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
