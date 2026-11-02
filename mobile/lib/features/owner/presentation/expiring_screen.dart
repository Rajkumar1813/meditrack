import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../controllers/owner_controller.dart';
import '../../catalog/models/medicine_model.dart';

class ExpiringScreen extends ConsumerStatefulWidget {
  const ExpiringScreen({super.key});

  @override
  ConsumerState<ExpiringScreen> createState() => _ExpiringScreenState();
}

class _ExpiringScreenState extends ConsumerState<ExpiringScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 3, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(ownerControllerProvider.notifier).fetchExpiring(days: 30);
      ref.read(ownerControllerProvider.notifier).fetchExpired();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ownerState = ref.watch(ownerControllerProvider);
    final brand = ref.watch(brandControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Expiry Management'),
        bottom: TabBar(
          controller: _tabController,
          onTap: (index) {
            if (index == 0) {
              ref.read(ownerControllerProvider.notifier).fetchExpiring(days: 30);
            } else if (index == 1) {
              ref.read(ownerControllerProvider.notifier).fetchExpiring(days: 90);
            } else {
              ref.read(ownerControllerProvider.notifier).fetchExpired();
            }
          },
          tabs: const [
            Tab(text: '30 Days'),
            Tab(text: '90 Days'),
            Tab(text: 'Expired'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // 30 Days
          _buildBatchesList(ownerState.expiringBatches, brand.currencySymbol, isUrgent: true),
          // 90 Days
          _buildBatchesList(ownerState.expiringBatches, brand.currencySymbol, isUrgent: false),
          // Expired
          _buildBatchesList(ownerState.expiredBatches, brand.currencySymbol, isExpired: true),
        ],
      ),
    );
  }

  Widget _buildBatchesList(List<BatchModel> batches, String currency, {bool isUrgent = false, bool isExpired = false}) {
    if (batches.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isExpired ? Icons.check_circle_outline_rounded : Icons.verified_user_outlined,
              size: 64,
              color: const Color(0xFF059669),
            ),
            const SizedBox(height: 16),
            Text(
              isExpired ? 'Zero expired batches in inventory!' : 'No batches expiring in this window.',
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
            ),
          ],
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: batches.length,
      separatorBuilder: (context, index) => const SizedBox(height: 12),
      itemBuilder: (context, i) {
        final b = batches[i];
        final alertColor = isExpired
            ? const Color(0xFFDC2626)
            : (isUrgent ? const Color(0xFFEA580C) : const Color(0xFFD97706));

        return Card3D(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Batch ${b.batchNumber}',
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: alertColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      isExpired ? 'EXPIRED' : '${b.daysUntilExpiry} days left',
                      style: TextStyle(color: alertColor, fontSize: 11, fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text('Expiry Date: ${b.expiryDate} • Stock: ${b.quantity} units'),
              Text('Purchase Price: $currency${b.purchasePrice.toStringAsFixed(2)} • MRP: $currency${b.mrp.toStringAsFixed(2)}'),
              const Divider(height: 20),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  if (!isExpired) ...[
                    OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        minimumSize: Size.zero,
                      ),
                      onPressed: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Applied 20% discount on batch ${b.batchNumber}')),
                        );
                      },
                      child: const Text('Discount', style: TextStyle(fontSize: 12)),
                    ),
                    const SizedBox(width: 8),
                  ],
                  OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      foregroundColor: const Color(0xFFDC2626),
                      side: const BorderSide(color: Color(0xFFDC2626)),
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      minimumSize: Size.zero,
                    ),
                    onPressed: () {
                      ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text(isExpired ? 'Disposal logged for ${b.batchNumber}' : 'Return initiated for ${b.batchNumber}')),
                      );
                    },
                    child: Text(isExpired ? 'Dispose' : 'Return to Vendor', style: const TextStyle(fontSize: 12)),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
