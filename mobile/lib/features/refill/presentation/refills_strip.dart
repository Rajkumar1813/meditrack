import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../cart/controllers/cart_controller.dart';
import '../../catalog/models/medicine_model.dart';

class RefillsStrip extends ConsumerStatefulWidget {
  const RefillsStrip({super.key});

  @override
  ConsumerState<RefillsStrip> createState() => _RefillsStripState();
}

class _RefillsStripState extends ConsumerState<RefillsStrip> {
  Map<String, dynamic>? _activeRefill;
  bool _dismissed = false;

  @override
  void initState() {
    super.initState();
    _fetchRefills();
  }

  Future<void> _fetchRefills() async {
    final api = ref.read(apiClientProvider);
    final res = await api.get('/refills');

    if (!mounted) return;

    res.when(
      success: (data) {
        if (data is Map<String, dynamic> && data['items'] is List && (data['items'] as List).isNotEmpty) {
          setState(() {
            _activeRefill = (data['items'] as List).first as Map<String, dynamic>;
          });
        } else if (data is List && data.isNotEmpty) {
          setState(() {
            _activeRefill = data.first as Map<String, dynamic>;
          });
        }
      },
      failure: (_) {},
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_dismissed || _activeRefill == null) {
      return const SizedBox.shrink();
    }

    final medName = _activeRefill!['medicine_name'] ?? _activeRefill!['name'] ?? 'Daily Medication';
    final daysLeft = _activeRefill!['days_remaining'] ?? 3;
    final medId = _activeRefill!['medicine_id'] ?? 1;

    return Card3D(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      backgroundColor: const Color(0xFFECFDF5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.alarm_on_rounded, color: Color(0xFF047857), size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Refill Reminder: $medName ($daysLeft days left)',
                  style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF065F46), fontSize: 13),
                ),
              ),
              IconButton(
                icon: const Icon(Icons.close, size: 16, color: Color(0xFF047857)),
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints(),
                onPressed: () => setState(() => _dismissed = true),
              ),
            ],
          ),
          const SizedBox(height: 8),
          const Text(
            'Your monthly course is running low. Reorder now to avoid missing your dosage.',
            style: TextStyle(color: Color(0xFF065F46), fontSize: 12),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF059669),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  minimumSize: Size.zero,
                ),
                icon: const Icon(Icons.shopping_bag_outlined, size: 14),
                label: const Text('1-Tap Reorder', style: TextStyle(fontSize: 12)),
                onPressed: () async {
                  final med = MedicineModel(
                    id: medId is int ? medId : 1,
                    name: medName.toString(),
                    slug: 'refill',
                    category: 'General',
                    requiresPrescription: false,
                    price: 240.0,
                    mrp: 299.0,
                    salts: [],
                    manufacturer: 'Standard Pharma',
                    stock: 50,
                  );
                  await ref.read(cartControllerProvider.notifier).addItem(med);
                  if (context.mounted) {
                    context.push('/cart');
                  }
                },
              ),
              const SizedBox(width: 8),
              TextButton(
                onPressed: () => setState(() => _dismissed = true),
                child: const Text('Snooze 3 days', style: TextStyle(color: Color(0xFF047857), fontSize: 12)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

