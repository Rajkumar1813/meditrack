import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/primary_button.dart';
import '../models/medicine_model.dart';
import '../../cart/controllers/cart_controller.dart';

class MedicineDetailScreen extends ConsumerStatefulWidget {
  final int medicineId;

  const MedicineDetailScreen({super.key, required this.medicineId});

  @override
  ConsumerState<MedicineDetailScreen> createState() => _MedicineDetailScreenState();
}

class _MedicineDetailScreenState extends ConsumerState<MedicineDetailScreen> {
  MedicineModel? _medicine;
  List<MedicineModel> _alternatives = [];
  bool _isLoading = true;
  int _quantity = 1;

  @override
  void initState() {
    super.initState();
    _fetchDetail();
  }

  Future<void> _fetchDetail() async {
    setState(() => _isLoading = true);
    final api = ref.read(apiClientProvider);

    final res = await api.get('/medicines/${widget.medicineId}');

    if (!mounted) return;

    res.when(
      success: (data) {
        if (data is Map<String, dynamic>) {
          final med = MedicineModel.fromJson(data['medicine'] is Map<String, dynamic> ? data['medicine'] as Map<String, dynamic> : data);
          List<MedicineModel> alts = [];
          if (data['alternatives'] is List) {
            alts = (data['alternatives'] as List)
                .map((a) => MedicineModel.fromJson(a as Map<String, dynamic>))
                .toList();
          }
          setState(() {
            _medicine = med;
            _alternatives = alts;
            _isLoading = false;
          });
        }
      },
      failure: (_) {
        setState(() => _isLoading = false);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final brand = ref.watch(brandControllerProvider);
    final theme = Theme.of(context);

    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(),
        body: const Center(child: CircularProgressIndicator.adaptive()),
      );
    }

    if (_medicine == null) {
      return Scaffold(
        appBar: AppBar(),
        body: const Center(child: Text('Medicine not found')),
      );
    }

    final med = _medicine!;
    final earliestBatch = med.batches.isNotEmpty ? med.batches.first : null;

    return Scaffold(
      appBar: AppBar(
        title: Text(med.name),
        actions: [
          IconButton(
            icon: const Icon(Icons.shopping_bag_outlined),
            onPressed: () => context.push('/cart'),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          // Hero 3D Card
          Card3D(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        color: const Color(0xFFD1FAE5),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: const Icon(Icons.medication_rounded, size: 36, color: Color(0xFF047857)),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            med.name,
                            style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            med.manufacturer,
                            style: theme.textTheme.bodyMedium?.copyWith(color: theme.textTheme.bodySmall?.color),
                          ),
                          const SizedBox(height: 8),
                          if (med.requiresPrescription)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFEE2E2),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: const Text(
                                'Prescription (Rx) Required',
                                style: TextStyle(
                                  color: Color(0xFFDC2626),
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                const Divider(),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${brand.currencySymbol}${med.price.toStringAsFixed(2)}',
                          style: theme.textTheme.headlineSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                            color: brand.primaryColor,
                          ),
                        ),
                        if (med.mrp > med.price) ...[
                          const SizedBox(height: 2),
                          Text(
                            'MRP ${brand.currencySymbol}${med.mrp.toStringAsFixed(2)} (Save ${(med.mrp - med.price).toStringAsFixed(2)})',
                            style: theme.textTheme.bodySmall?.copyWith(fontSize: 12),
                          ),
                        ],
                      ],
                    ),
                    Row(
                      children: [
                        IconButton.outlined(
                          icon: const Icon(Icons.remove),
                          onPressed: _quantity > 1 ? () => setState(() => _quantity--) : null,
                        ),
                        Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          child: Text(
                            '$_quantity',
                            style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                          ),
                        ),
                        IconButton.outlined(
                          icon: const Icon(Icons.add),
                          onPressed: () => setState(() => _quantity++),
                        ),
                      ],
                    ),
                  ],
                ),
                if (earliestBatch != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: theme.brightness == Brightness.dark
                          ? const Color(0xFF1E293B)
                          : const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.verified_outlined, size: 16, color: Color(0xFF059669)),
                        const SizedBox(width: 8),
                        Text(
                          'Batch ${earliestBatch.batchNumber} • Expiry: ${earliestBatch.expiryDate}',
                          style: theme.textTheme.bodySmall?.copyWith(fontWeight: FontWeight.w600),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Composition Salts
          Card3D(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Active Salt Composition', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                const SizedBox(height: 10),
                if (med.salts.isNotEmpty)
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: med.salts.map((s) => Chip(label: Text(s))).toList(),
                  )
                else
                  const Text('No salt details available'),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Therapeutic Alternatives
          if (_alternatives.isNotEmpty) ...[
            Text(
              'Same-Salt Generic Alternatives',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 12),
            ..._alternatives.map((alt) => Card3D(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  onTap: () => context.push('/medicine/${alt.id}'),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(alt.name, style: const TextStyle(fontWeight: FontWeight.w700)),
                          Text(alt.manufacturer, style: theme.textTheme.bodySmall),
                        ],
                      ),
                      Text(
                        '${brand.currencySymbol}${alt.price.toStringAsFixed(2)}',
                        style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF059669)),
                      ),
                    ],
                  ),
                )),
            const SizedBox(height: 20),
          ],
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: PrimaryButton(
            label: 'Add $_quantity to Cart • ${brand.currencySymbol}${(med.price * _quantity).toStringAsFixed(2)}',
            onPressed: () {
              ref.read(cartControllerProvider.notifier).addItem(med, quantity: _quantity);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('${med.name} added to cart')),
              );
            },
          ),
        ),
      ),
    );
  }
}
