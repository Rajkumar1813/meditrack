import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/status_chip.dart';
import '../controllers/orders_controller.dart';

class OrdersScreen extends ConsumerWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersState = ref.watch(ordersControllerProvider);
    final brand = ref.watch(brandControllerProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('My Orders')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.read(ordersControllerProvider.notifier).fetchOrders(isRefresh: true),
        child: ordersState.orders.isEmpty
            ? (ordersState.isLoading
                ? const Center(child: CircularProgressIndicator.adaptive())
                : Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.receipt_long_outlined, size: 64, color: Colors.grey),
                        const SizedBox(height: 16),
                        const Text('No orders placed yet', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        const Text('Order medicines to track their status here.'),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: () => context.push('/catalog'),
                          child: const Text('Browse Medicines'),
                        ),
                      ],
                    ),
                  ))
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: ordersState.orders.length,
                separatorBuilder: (context, index) => const SizedBox(height: 12),
                itemBuilder: (context, i) {
                  final order = ordersState.orders[i];
                  return Card3D(
                    padding: const EdgeInsets.all(16),
                    onTap: () => context.push('/orders/${order.id}'),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              order.orderNumber,
                              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                            ),
                            StatusChip(status: order.status),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Placed on ${order.createdAt}',
                          style: theme.textTheme.bodySmall,
                        ),
                        const Divider(height: 20),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              '${order.items.length} item(s) • ${order.paymentMethod.toUpperCase()}',
                              style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                            ),
                            Text(
                              '${brand.currencySymbol}${order.total.toStringAsFixed(2)}',
                              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF059669)),
                            ),
                          ],
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
