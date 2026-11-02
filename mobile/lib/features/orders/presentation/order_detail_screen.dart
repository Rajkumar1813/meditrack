import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../core/network/api_client.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/status_chip.dart';
import '../../../shared/widgets/primary_button.dart';
import '../controllers/orders_controller.dart';
import '../models/order_model.dart';

class OrderDetailScreen extends ConsumerStatefulWidget {
  final int orderId;

  const OrderDetailScreen({super.key, required this.orderId});

  @override
  ConsumerState<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends ConsumerState<OrderDetailScreen> {
  OrderModel? _order;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _fetchDetail();
  }

  Future<void> _fetchDetail() async {
    setState(() => _isLoading = true);
    final api = ref.read(apiClientProvider);
    final res = await api.get('/orders/${widget.orderId}');

    if (!mounted) return;

    res.when(
      success: (data) {
        if (data is Map<String, dynamic>) {
          final orderJson = data['order'] is Map<String, dynamic> ? data['order'] as Map<String, dynamic> : data;
          setState(() {
            _order = OrderModel.fromJson(orderJson);
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
        appBar: AppBar(title: const Text('Order Details')),
        body: const Center(child: CircularProgressIndicator.adaptive()),
      );
    }

    if (_order == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Order Details')),
        body: const Center(child: Text('Order not found')),
      );
    }

    final order = _order!;

    return Scaffold(
      appBar: AppBar(
        title: Text(order.orderNumber),
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Sharing order details...')),
              );
            },
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // 1. Status & Timeline Stepper Card
          Card3D(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text('Status', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                    StatusChip(status: order.status),
                  ],
                ),
                const SizedBox(height: 16),
                _buildTimelineStepper(order.status),
                if (order.trackingNumber != null && order.trackingNumber!.isNotEmpty) ...[
                  const Divider(height: 24),
                  Row(
                    children: [
                      const Icon(Icons.local_shipping_outlined, size: 20, color: Color(0xFF2563EB)),
                      const SizedBox(width: 8),
                      Text(
                        '${order.courier ?? "Courier"}: ${order.trackingNumber}',
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),

          // 2. Items List
          Card3D(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Ordered Medicines', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                const SizedBox(height: 12),
                if (order.items.isEmpty)
                  const Text('1x Standard Medicine Package')
                else
                  ...order.items.map((item) => Padding(
                        padding: const EdgeInsets.symmetric(vertical: 6),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                '${item.quantity}x ${item.medicineName}',
                                style: const TextStyle(fontWeight: FontWeight.w600),
                              ),
                            ),
                            Text(
                              '${brand.currencySymbol}${item.totalPrice.toStringAsFixed(2)}',
                              style: const TextStyle(fontWeight: FontWeight.w700),
                            ),
                          ],
                        ),
                      )),
                const Divider(height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total Amount Paid', style: TextStyle(fontWeight: FontWeight.w700)),
                    Text(
                      '${brand.currencySymbol}${order.total.toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF059669)),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // 3. Delivery Address Snapshot
          if (order.address != null) ...[
            Card3D(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Delivery Address', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                  const SizedBox(height: 8),
                  Text(
                    '${order.address!.name} • ${order.address!.phone}',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                  const SizedBox(height: 4),
                  Text(order.address!.fullAddress, style: theme.textTheme.bodySmall),
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],

          // 4. GST Tax Invoice Button
          OutlinedButton.icon(
            icon: const Icon(Icons.picture_as_pdf_outlined),
            label: const Text('Download GST Tax Invoice (PDF)'),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Downloading tax invoice PDF...')),
              );
            },
          ),
          const SizedBox(height: 12),

          // 5. 1-Tap Reorder Button
          PrimaryButton(
            label: '1-Tap Reorder',
            icon: Icons.replay_rounded,
            onPressed: () async {
              final ok = await ref.read(ordersControllerProvider.notifier).reorder(order.id);
              if (ok && context.mounted) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Items added to cart! Redirecting...')),
                );
                context.push('/cart');
              }
            },
          ),
          const SizedBox(height: 12),

          // 6. Cancel Order (if eligible)
          if (order.isCancellable) ...[
            TextButton(
              onPressed: () async {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    title: const Text('Cancel Order'),
                    content: const Text('Are you sure you want to cancel this order? FEFO stock will be released immediately.'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('No')),
                      TextButton(
                        onPressed: () => Navigator.pop(ctx, true),
                        child: const Text('Yes, Cancel', style: TextStyle(color: Colors.red)),
                      ),
                    ],
                  ),
                );

                if (confirm == true && mounted) {
                  final ok = await ref.read(ordersControllerProvider.notifier).cancelOrder(order.id);
                  if (ok) {
                    _fetchDetail();
                  }
                }
              },
              child: const Text('Cancel Order', style: TextStyle(color: Color(0xFFDC2626))),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildTimelineStepper(String currentStatus) {
    const steps = ['pending', 'confirmed', 'packed', 'shipped', 'delivered'];
    final lowerStatus = currentStatus.toLowerCase();

    if (lowerStatus == 'cancelled') {
      return Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFEE2E2),
          borderRadius: BorderRadius.circular(8),
        ),
        child: const Row(
          children: [
            Icon(Icons.cancel_outlined, color: Color(0xFFDC2626)),
            SizedBox(width: 8),
            Text(
              'Order Cancelled',
              style: TextStyle(color: Color(0xFFB91C1C), fontWeight: FontWeight.w700),
            ),
          ],
        ),
      );
    }

    final currentIndex = steps.indexOf(lowerStatus);
    final activeIndex = currentIndex >= 0 ? currentIndex : 0;

    return Row(
      children: List.generate(steps.length * 2 - 1, (i) {
        if (i.isOdd) {
          final stepBefore = i ~/ 2;
          final isCompleted = stepBefore < activeIndex;
          return Expanded(
            child: Container(
              height: 3,
              color: isCompleted ? const Color(0xFF059669) : const Color(0xFFE2E8F0),
            ),
          );
        } else {
          final stepIndex = i ~/ 2;
          final isCompleted = stepIndex <= activeIndex;
          return Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(
              color: isCompleted ? const Color(0xFF059669) : const Color(0xFFE2E8F0),
              shape: BoxShape.circle,
            ),
            child: isCompleted
                ? const Icon(Icons.check, size: 14, color: Colors.white)
                : Center(
                    child: Text(
                      '${stepIndex + 1}',
                      style: const TextStyle(fontSize: 10, color: Colors.grey, fontWeight: FontWeight.w700),
                    ),
                  ),
          );
        }
      }),
    );
  }
}
