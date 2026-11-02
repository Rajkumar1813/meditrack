import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../controllers/owner_controller.dart';

class InventoryScreen extends ConsumerStatefulWidget {
  const InventoryScreen({super.key});

  @override
  ConsumerState<InventoryScreen> createState() => _InventoryScreenState();
}

class _InventoryScreenState extends ConsumerState<InventoryScreen> {
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(ownerControllerProvider.notifier).fetchInventory();
    });
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  void _showAddMedicineDialog() {
    final nameCtrl = TextEditingController();
    final catCtrl = TextEditingController(text: 'General');
    final priceCtrl = TextEditingController();
    final mrpCtrl = TextEditingController();
    final batchNumCtrl = TextEditingController();
    final qtyCtrl = TextEditingController(text: '100');
    final mfgCtrl = TextEditingController(text: '2026-01-01');
    final expCtrl = TextEditingController(text: '2027-01-01');
    bool requiresRx = false;
    String? formError;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            title: const Text('Add New Medicine & Batch'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  if (formError != null) ...[
                    Container(
                      padding: const EdgeInsets.all(8),
                      color: const Color(0xFFFEE2E2),
                      child: Text(formError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                    ),
                    const SizedBox(height: 8),
                  ],
                  TextField(
                    key: const Key('add-med-name-input'),
                    controller: nameCtrl,
                    decoration: const InputDecoration(labelText: 'Medicine Name *'),
                  ),
                  const SizedBox(height: 8),
                  TextField(
                    controller: catCtrl,
                    decoration: const InputDecoration(labelText: 'Category *'),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          key: const Key('add-med-price-input'),
                          controller: priceCtrl,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'Selling Price *'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: TextField(
                          key: const Key('add-med-mrp-input'),
                          controller: mrpCtrl,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'MRP *'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          key: const Key('add-batch-number-input'),
                          controller: batchNumCtrl,
                          decoration: const InputDecoration(labelText: 'Batch Number *'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: TextField(
                          key: const Key('add-batch-qty-input'),
                          controller: qtyCtrl,
                          keyboardType: TextInputType.number,
                          decoration: const InputDecoration(labelText: 'Initial Qty *'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: mfgCtrl,
                          decoration: const InputDecoration(labelText: 'Mfg (YYYY-MM-DD)'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: TextField(
                          controller: expCtrl,
                          decoration: const InputDecoration(labelText: 'Expiry (YYYY-MM-DD)'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Prescription (Rx) Required'),
                    value: requiresRx,
                    onChanged: (val) => setDialogState(() => requiresRx = val),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
              ElevatedButton(
                key: const Key('confirm-add-medicine-btn'),
                onPressed: () async {
                  final name = nameCtrl.text.trim();
                  final price = double.tryParse(priceCtrl.text) ?? 0.0;
                  final mrp = double.tryParse(mrpCtrl.text) ?? price;
                  final batchNo = batchNumCtrl.text.trim();
                  final qty = int.tryParse(qtyCtrl.text) ?? 0;

                  if (name.isEmpty) {
                    setDialogState(() => formError = 'Medicine name is required.');
                    return;
                  }
                  if (price <= 0) {
                    setDialogState(() => formError = 'Price must be greater than 0.');
                    return;
                  }
                  if (mrp < price) {
                    setDialogState(() => formError = 'MRP cannot be less than selling price.');
                    return;
                  }
                  if (batchNo.isEmpty) {
                    setDialogState(() => formError = 'Batch number is required.');
                    return;
                  }

                  final messenger = ScaffoldMessenger.of(context);
                  final success = await ref.read(ownerControllerProvider.notifier).addMedicine({
                    'name': name,
                    'category': catCtrl.text,
                    'price': price,
                    'mrp': mrp,
                    'requires_prescription': requiresRx ? 1 : 0,
                    'batch_number': batchNo,
                    'quantity': qty,
                    'mfg_date': mfgCtrl.text,
                    'expiry_date': expCtrl.text,
                    'purchase_price': price * 0.7,
                  });

                  if (success && mounted) {
                    if (ctx.mounted) Navigator.pop(ctx);
                    messenger.showSnackBar(
                      SnackBar(content: Text('Medicine "$name" added successfully')),
                    );
                  }
                },
                child: const Text('Add Medicine'),
              ),
            ],
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ownerState = ref.watch(ownerControllerProvider);
    final brand = ref.watch(brandControllerProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Inventory & Medicines')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchCtrl,
              decoration: InputDecoration(
                hintText: 'Search medicines by name, generic, or batch...',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchCtrl.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchCtrl.clear();
                          ref.read(ownerControllerProvider.notifier).fetchInventory();
                        },
                      )
                    : null,
              ),
              onSubmitted: (val) {
                ref.read(ownerControllerProvider.notifier).fetchInventory(query: val);
              },
            ),
          ),
          Expanded(
            child: RefreshIndicator.adaptive(
              onRefresh: () => ref.read(ownerControllerProvider.notifier).fetchInventory(),
              child: ownerState.inventory.isEmpty
                  ? (ownerState.isLoading
                      ? const Center(child: CircularProgressIndicator.adaptive())
                      : const Center(child: Text('No medicines found in inventory.')))
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      itemCount: ownerState.inventory.length,
                      separatorBuilder: (context, index) => const SizedBox(height: 10),
                      itemBuilder: (context, i) {
                        final med = ownerState.inventory[i];
                        return Card3D(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      med.name,
                                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                    decoration: BoxDecoration(
                                      color: med.stock > 10 ? const Color(0xFFD1FAE5) : const Color(0xFFFEE2E2),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      '${med.stock} units',
                                      style: TextStyle(
                                        color: med.stock > 10 ? const Color(0xFF047857) : const Color(0xFFDC2626),
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${med.category} • Price: ${brand.currencySymbol}${med.price.toStringAsFixed(2)} • MRP: ${brand.currencySymbol}${med.mrp.toStringAsFixed(2)}',
                                style: theme.textTheme.bodySmall,
                              ),
                              if (med.batches.isNotEmpty) ...[
                                const SizedBox(height: 8),
                                Text(
                                  'Batches: ${med.batches.map((b) => b.batchNumber).join(', ')}',
                                  style: theme.textTheme.bodySmall?.copyWith(fontSize: 11, color: Colors.grey),
                                ),
                              ],
                            ],
                          ),
                        );
                      },
                    ),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        key: const Key('add-medicine-fab'),
        icon: const Icon(Icons.add),
        label: const Text('Add Medicine'),
        onPressed: _showAddMedicineDialog,
      ),
    );
  }
}
