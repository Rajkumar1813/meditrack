import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/brand/brand_tokens.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/status_chip.dart';
import '../../orders/models/order_model.dart';
import '../controllers/owner_controller.dart';

class OwnerOrdersScreen extends ConsumerStatefulWidget {
  const OwnerOrdersScreen({super.key});

  @override
  ConsumerState<OwnerOrdersScreen> createState() => _OwnerOrdersScreenState();
}

class _OwnerOrdersScreenState extends ConsumerState<OwnerOrdersScreen> {
  String _selectedStatus = 'all';

  final List<String> _statuses = [
    'all',
    'pending',
    'confirmed',
    'packed',
    'shipped',
    'delivered',
    'cancelled',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(ownerControllerProvider.notifier).fetchOrders();
    });
  }

  void _onFilterChanged(String status) {
    setState(() => _selectedStatus = status);
    ref.read(ownerControllerProvider.notifier).fetchOrders(
          status: status == 'all' ? null : status,
        );
  }

  void _showStatusDialog(OrderModel order) {
    String newStatus = order.status;
    final courierCtrl = TextEditingController(text: order.courierName ?? '');
    final trackingCtrl = TextEditingController(text: order.trackingNumber ?? '');
    final noteCtrl = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (modalContext, setModalState) {
            final theme = Theme.of(modalContext);
            final brand = ref.read(brandControllerProvider);

            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(modalContext).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Update Order #${order.id}',
                        style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close_rounded),
                        onPressed: () => Navigator.pop(modalContext),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  const Text('Select Next Status:', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    key: const Key('owner-status-dropdown'),
                    initialValue: _statuses.contains(newStatus) && newStatus != 'all' ? newStatus : 'confirmed',
                    items: const [
                      DropdownMenuItem(value: 'pending', child: Text('Pending Approval')),
                      DropdownMenuItem(value: 'confirmed', child: Text('Confirmed')),
                      DropdownMenuItem(value: 'packed', child: Text('Packed')),
                      DropdownMenuItem(value: 'shipped', child: Text('Shipped')),
                      DropdownMenuItem(value: 'delivered', child: Text('Delivered')),
                      DropdownMenuItem(value: 'cancelled', child: Text('Cancelled')),
                    ],
                    onChanged: (val) {
                      if (val != null) {
                        setModalState(() => newStatus = val);
                      }
                    },
                    decoration: InputDecoration(
                      filled: true,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  if (newStatus == 'shipped') ...[
                    const SizedBox(height: 12),
                    TextField(
                      key: const Key('owner-courier-field'),
                      controller: courierCtrl,
                      decoration: InputDecoration(
                        labelText: 'Courier Partner',
                        hintText: 'e.g. Delhivery, Bluedart, Shadowfax',
                        filled: true,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextField(
                      key: const Key('owner-tracking-field'),
                      controller: trackingCtrl,
                      decoration: InputDecoration(
                        labelText: 'Tracking / AWB Number',
                        hintText: 'e.g. DEL-9823412',
                        filled: true,
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ],
                  const SizedBox(height: 10),
                  TextField(
                    key: const Key('owner-note-field'),
                    controller: noteCtrl,
                    decoration: InputDecoration(
                      labelText: 'Status Transition Note (Optional)',
                      hintText: 'Add an internal note or customer comment',
                      filled: true,
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      key: const Key('owner-save-status-btn'),
                      onPressed: () async {
                        Navigator.pop(ctx);
                        final success = await ref.read(ownerControllerProvider.notifier).updateOrderStatus(
                              order.id,
                              newStatus,
                              courier: courierCtrl.text.trim().isNotEmpty ? courierCtrl.text.trim() : null,
                              trackingNo: trackingCtrl.text.trim().isNotEmpty ? trackingCtrl.text.trim() : null,
                              note: noteCtrl.text.trim().isNotEmpty ? noteCtrl.text.trim() : null,
                            );
                        if (!mounted) return;
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              success
                                  ? 'Order #${order.id} status updated to $newStatus'
                                  : 'Failed to update order status',
                            ),
                            backgroundColor: success ? brand.primaryColor : BrandTokens.danger,
                          ),
                        );
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: brand.primaryColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Confirm Status Update', style: TextStyle(fontWeight: FontWeight.w700)),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(ownerControllerProvider);
    final theme = Theme.of(context);
    final brand = ref.watch(brandControllerProvider);

    return Scaffold(
      key: const Key('owner-orders-screen'),
      appBar: AppBar(
        title: const Text('Orders Management'),
      ),
      body: Column(
        children: [
          // Filter Chips Horizontal Scroll
          Container(
            height: 52,
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: _statuses.length,
              separatorBuilder: (context, index) => const SizedBox(width: 8),
              itemBuilder: (context, i) {
                final s = _statuses[i];
                final isSelected = _selectedStatus == s;
                return ChoiceChip(
                  key: Key('filter-status-$s'),
                  label: Text(
                    s[0].toUpperCase() + s.substring(1),
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                      color: isSelected ? Colors.white : theme.textTheme.bodyMedium?.color,
                    ),
                  ),
                  selected: isSelected,
                  selectedColor: brand.primaryColor,
                  onSelected: (_) => _onFilterChanged(s),
                );
              },
            ),
          ),
          const Divider(height: 1),

          Expanded(
            child: state.isLoading && state.orders.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : state.orders.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.inventory_2_outlined, size: 56, color: theme.disabledColor),
                            const SizedBox(height: 12),
                            Text(
                              'No ${_selectedStatus == 'all' ? '' : _selectedStatus} orders found',
                              style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => ref.read(ownerControllerProvider.notifier).fetchOrders(
                              status: _selectedStatus == 'all' ? null : _selectedStatus,
                            ),
                        child: ListView.separated(
                          padding: const EdgeInsets.all(16),
                          itemCount: state.orders.length,
                          separatorBuilder: (context, index) => const SizedBox(height: 12),
                          itemBuilder: (context, idx) {
                            final ord = state.orders[idx];
                            return Card3D(
                              key: Key('owner-order-card-${ord.id}'),
                              padding: const EdgeInsets.all(16),
                              onTap: () => _showStatusDialog(ord),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(
                                        '#${ord.orderNumber.isNotEmpty ? ord.orderNumber : ord.id}',
                                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                                      ),
                                      StatusChip(status: ord.status),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Row(
                                    children: [
                                      Icon(Icons.person_outline_rounded, size: 14, color: theme.hintColor),
                                      const SizedBox(width: 4),
                                      Text(
                                        ord.customerName,
                                        style: TextStyle(fontSize: 13, color: theme.hintColor),
                                      ),
                                      const Spacer(),
                                      Text(
                                        '₹${ord.total.toStringAsFixed(2)}',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 15,
                                          color: brand.primaryColor,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 6),
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Text(
                                        '${ord.items.length} item(s) • ${ord.paymentMethod.toUpperCase()}',
                                        style: TextStyle(fontSize: 12, color: theme.hintColor),
                                      ),
                                      Text(
                                        ord.formattedDate,
                                        style: TextStyle(fontSize: 11, color: theme.hintColor),
                                      ),
                                    ],
                                  ),
                                  if (ord.courierName != null && ord.courierName!.isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          const Icon(Icons.local_shipping_outlined, size: 13),
                                          const SizedBox(width: 4),
                                          Text(
                                            '${ord.courierName}: ${ord.trackingNumber ?? 'N/A'}',
                                            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
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
          ),
        ],
      ),
    );
  }
}
