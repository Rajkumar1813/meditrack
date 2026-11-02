import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/brand/brand_tokens.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/status_chip.dart';
import '../../prescription/models/prescription_model.dart';
import '../controllers/owner_controller.dart';

class RxReviewScreen extends ConsumerStatefulWidget {
  const RxReviewScreen({super.key});

  @override
  ConsumerState<RxReviewScreen> createState() => _RxReviewScreenState();
}

class _RxReviewScreenState extends ConsumerState<RxReviewScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(ownerControllerProvider.notifier).fetchPendingRx();
    });
  }

  void _openReviewModal(PrescriptionModel rx) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _PrescriptionViewerModal(prescription: rx),
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(ownerControllerProvider);
    final theme = Theme.of(context);
    final brand = ref.watch(brandControllerProvider);

    return Scaffold(
      key: const Key('rx-review-screen'),
      appBar: AppBar(
        title: const Text('Prescription Verification'),
      ),
      body: state.isLoading && state.pendingRx.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : state.pendingRx.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.verified_outlined, size: 64, color: brand.primaryColor.withValues(alpha: 0.5)),
                      const SizedBox(height: 16),
                      Text(
                        'All caught up!',
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'No pending prescriptions awaiting verification.',
                        style: theme.textTheme.bodyMedium?.copyWith(color: theme.hintColor),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: () => ref.read(ownerControllerProvider.notifier).fetchPendingRx(),
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: state.pendingRx.length,
                    separatorBuilder: (context, index) => const SizedBox(height: 12),
                    itemBuilder: (context, idx) {
                      final rx = state.pendingRx[idx];
                      return Card3D(
                        key: Key('rx-item-${rx.id}'),
                        padding: const EdgeInsets.all(16),
                        onTap: () => _openReviewModal(rx),
                        child: Row(
                          children: [
                            Container(
                              width: 52,
                              height: 52,
                              decoration: BoxDecoration(
                                color: brand.primaryColor.withValues(alpha: 0.1),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Icon(Icons.description_outlined, color: brand.primaryColor, size: 28),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
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
                                  const SizedBox(height: 4),
                                  Text(
                                    'Submitted: ${rx.createdAt}',
                                    style: TextStyle(fontSize: 12, color: theme.hintColor),
                                  ),
                                  const SizedBox(height: 4),
                                  const Text(
                                    'Tap to inspect & review',
                                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF059669)),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
    );
  }
}

class _PrescriptionViewerModal extends ConsumerStatefulWidget {
  final PrescriptionModel prescription;

  const _PrescriptionViewerModal({required this.prescription});

  @override
  ConsumerState<_PrescriptionViewerModal> createState() => _PrescriptionViewerModalState();
}

class _PrescriptionViewerModalState extends ConsumerState<_PrescriptionViewerModal> {
  final TransformationController _transCtrl = TransformationController();
  int _rotationQuarterTurns = 0;

  void _rotateClockwise() {
    setState(() {
      _rotationQuarterTurns = (_rotationQuarterTurns + 1) % 4;
    });
  }

  void _resetZoom() {
    setState(() {
      _transCtrl.value = Matrix4.identity();
      _rotationQuarterTurns = 0;
    });
  }

  void _confirmApprove() {
    final messenger = ScaffoldMessenger.of(context);
    final now = DateTime.now();
    final defaultValidity = DateTime(now.year, now.month + 1, now.day);
    String validUntilStr = "${defaultValidity.year}-${defaultValidity.month.toString().padLeft(2, '0')}-${defaultValidity.day.toString().padLeft(2, '0')}";

    showDialog(
      context: context,
      builder: (ctx) {
        final theme = Theme.of(ctx);
        final brand = ref.read(brandControllerProvider);

        return AlertDialog(
          title: const Text('Approve Prescription'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Are you sure you want to approve Prescription #${widget.prescription.id}?',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: 12),
              Text(
                'Your pharmacist registered stamp will be applied. Validity set to 30 days ($validUntilStr).',
                style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel'),
            ),
            ElevatedButton(
              key: const Key('rx-approve-confirm-btn'),
              style: ElevatedButton.styleFrom(
                backgroundColor: brand.primaryColor,
                foregroundColor: Colors.white,
              ),
              onPressed: () async {
                final parentContext = context;
                Navigator.pop(ctx);
                Navigator.pop(parentContext);
                final ok = await ref.read(ownerControllerProvider.notifier).reviewPrescription(
                      widget.prescription.id,
                      'approved',
                      validUntil: validUntilStr,
                    );
                if (!mounted) return;
                messenger.showSnackBar(
                  SnackBar(
                    content: Text(ok ? 'Prescription approved successfully' : 'Failed to approve prescription'),
                    backgroundColor: ok ? brand.primaryColor : BrandTokens.danger,
                  ),
                );
              },
              child: const Text('Approve & Stamp'),
            ),
          ],
        );
      },
    );
  }

  void _showRejectDialog() {
    final messenger = ScaffoldMessenger.of(context);
    final reasonCtrl = TextEditingController();
    final quickReasons = [
      'Prescription is blurry / illegible',
      'Expired prescription',
      'Doctor signature / registration missing',
      'Prescription medicines do not match order',
    ];

    showDialog(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (dialogContext, setDialogState) {
            return AlertDialog(
              title: const Text('Reject Prescription'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Select reason or provide feedback:', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                    const SizedBox(height: 8),
                    ...quickReasons.map(
                      (r) => ListTile(
                        dense: true,
                        contentPadding: EdgeInsets.zero,
                        title: Text(r, style: const TextStyle(fontSize: 12)),
                        onTap: () {
                          reasonCtrl.text = r;
                          setDialogState(() {});
                        },
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      key: const Key('rx-rejection-reason-field'),
                      controller: reasonCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Rejection Reason',
                        hintText: 'Explain why the prescription cannot be filled...',
                        border: OutlineInputBorder(),
                      ),
                      maxLines: 2,
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: const Text('Cancel'),
                ),
                ElevatedButton(
                  key: const Key('rx-reject-confirm-btn'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: BrandTokens.danger,
                    foregroundColor: Colors.white,
                  ),
                  onPressed: () async {
                    if (reasonCtrl.text.trim().isEmpty) return;
                    final parentContext = context;
                    Navigator.pop(ctx);
                    Navigator.pop(parentContext);
                    final ok = await ref.read(ownerControllerProvider.notifier).reviewPrescription(
                          widget.prescription.id,
                          'rejected',
                          reason: reasonCtrl.text.trim(),
                        );
                    if (!mounted) return;
                    messenger.showSnackBar(
                      SnackBar(
                        content: Text(ok ? 'Prescription rejected' : 'Failed to reject prescription'),
                        backgroundColor: ok ? BrandTokens.warning : BrandTokens.danger,
                      ),
                    );
                  },
                  child: const Text('Reject Rx'),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final brand = ref.watch(brandControllerProvider);

    return Container(
      height: MediaQuery.of(context).size.height * 0.9,
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          // Drag handle & Header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Review Rx #${widget.prescription.id}',
                  style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                ),
                IconButton(
                  icon: const Icon(Icons.close_rounded),
                  onPressed: () => Navigator.pop(context),
                ),
              ],
            ),
          ),
          const Divider(height: 1),

          // Interactive Toolbar
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Row(
              children: [
                OutlinedButton.icon(
                  key: const Key('rx-rotate-btn'),
                  onPressed: _rotateClockwise,
                  icon: const Icon(Icons.rotate_right_rounded, size: 18),
                  label: const Text('Rotate 90°'),
                ),
                const SizedBox(width: 8),
                OutlinedButton.icon(
                  key: const Key('rx-reset-zoom-btn'),
                  onPressed: _resetZoom,
                  icon: const Icon(Icons.restart_alt_rounded, size: 18),
                  label: const Text('Reset'),
                ),
                const Spacer(),
                Text('Pinch to zoom / Pan', style: TextStyle(fontSize: 11, color: theme.hintColor)),
              ],
            ),
          ),

          // Prescription Image Canvas with InteractiveViewer
          Expanded(
            child: Container(
              margin: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.black.withValues(alpha: 0.05),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: theme.dividerColor),
              ),
              clipBehavior: Clip.antiAlias,
              child: Center(
                child: InteractiveViewer(
                  transformationController: _transCtrl,
                  minScale: 0.5,
                  maxScale: 4.0,
                  child: RotatedBox(
                    quarterTurns: _rotationQuarterTurns,
                    child: widget.prescription.fileUrl.isNotEmpty
                        ? Image.network(
                            widget.prescription.fileUrl,
                            fit: BoxFit.contain,
                            errorBuilder: (context, error, stackTrace) => _buildFallbackViewer(),
                          )
                        : _buildFallbackViewer(),
                  ),
                ),
              ),
            ),
          ),

          // Action Bar: Reject vs Approve
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: theme.cardColor,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -4),
                ),
              ],
            ),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    key: const Key('rx-reject-btn'),
                    onPressed: _showRejectDialog,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: BrandTokens.danger,
                      side: BorderSide(color: BrandTokens.danger),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Reject Rx', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    key: const Key('rx-approve-btn'),
                    onPressed: _confirmApprove,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: brand.primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Approve & Stamp', style: TextStyle(fontWeight: FontWeight.w700)),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFallbackViewer() {
    return Container(
      width: 280,
      height: 380,
      padding: const EdgeInsets.all(20),
      color: Colors.white,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('MEDICAL CLINIC', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Colors.black87)),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(color: Colors.green.shade50, borderRadius: BorderRadius.circular(4)),
                child: const Text('Rx #PREVIEW', style: TextStyle(fontSize: 10, color: Colors.green, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
          const Divider(color: Colors.black26),
          const SizedBox(height: 8),
          const Text('Dr. S. K. Gupta, M.B.B.S, M.D.', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 11, color: Colors.black87)),
          const Text('Reg No: MCI-84931-2015', style: TextStyle(fontSize: 9, color: Colors.black54)),
          const SizedBox(height: 12),
          const Text('Patient: Rohan Sharma (32 / M)', style: TextStyle(fontSize: 10, color: Colors.black87)),
          const Text('Date: 2026-11-01', style: TextStyle(fontSize: 9, color: Colors.black54)),
          const SizedBox(height: 16),
          const Text('Prescription Items:', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 10, color: Colors.black87)),
          const SizedBox(height: 4),
          const Text('1. Paracetamol 650mg — 1 tab tid x 3 days', style: TextStyle(fontSize: 10, color: Colors.black87)),
          const Text('2. Amoxicillin 500mg — 1 cap bd x 5 days', style: TextStyle(fontSize: 10, color: Colors.black87)),
          const Text('3. Pantoprazole 40mg — 1 tab od before food', style: TextStyle(fontSize: 10, color: Colors.black87)),
          const Spacer(),
          const Align(
            alignment: Alignment.bottomRight,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text('Signed: Dr. S. K. Gupta', style: TextStyle(fontStyle: FontStyle.italic, fontSize: 10, color: Colors.black87)),
                Text('Digital Rx Verification Sample', style: TextStyle(fontSize: 8, color: Colors.black38)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
