import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import '../models/order_model.dart';
import '../../../core/network/api_client.dart';
import '../../cart/controllers/cart_controller.dart';

@immutable
class OrdersState {
  final List<OrderModel> orders;
  final bool isLoading;
  final bool isRefreshing;
  final String? error;

  const OrdersState({
    this.orders = const [],
    this.isLoading = false,
    this.isRefreshing = false,
    this.error,
  });

  OrdersState copyWith({
    List<OrderModel>? orders,
    bool? isLoading,
    bool? isRefreshing,
    String? error,
    bool clearError = false,
  }) {
    return OrdersState(
      orders: orders ?? this.orders,
      isLoading: isLoading ?? this.isLoading,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

final ordersControllerProvider = StateNotifierProvider<OrdersController, OrdersState>((ref) {
  final api = ref.watch(apiClientProvider);
  final cartNotifier = ref.watch(cartControllerProvider.notifier);
  return OrdersController(api, cartNotifier);
});

class OrdersController extends StateNotifier<OrdersState> {
  final ApiClient _api;
  final CartController _cartNotifier;

  OrdersController(this._api, this._cartNotifier) : super(const OrdersState()) {
    fetchOrders();
  }

  Future<void> fetchOrders({bool isRefresh = false}) async {
    state = state.copyWith(
      isLoading: !isRefresh,
      isRefreshing: isRefresh,
      clearError: true,
    );

    final res = await _api.get('/orders');

    res.when(
      success: (data) {
        List<OrderModel> items = [];
        if (data is Map<String, dynamic> && data['items'] is List) {
          items = (data['items'] as List)
              .map((o) => OrderModel.fromJson(o as Map<String, dynamic>))
              .toList();
        } else if (data is List) {
          items = data.map((o) => OrderModel.fromJson(o as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(orders: items, isLoading: false, isRefreshing: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, isRefreshing: false, error: err.message);
      },
    );
  }

  Future<OrderModel?> createOrder({
    required int addressId,
    required String paymentMethod,
    int? prescriptionId,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);

    // Generate random Idempotency-Key
    final idempotencyKey = 'mobile-${DateTime.now().millisecondsSinceEpoch}-${Random().nextInt(999999)}';

    final orderPayload = <String, dynamic>{
      'address_id': addressId,
      'payment_method': paymentMethod,
    };
    if (prescriptionId != null) {
      orderPayload['prescription_id'] = prescriptionId;
    }

    final res = await _api.post(
      '/orders',
      data: orderPayload,
      options: Options(headers: {
        'Idempotency-Key': idempotencyKey,
      }),
    );

    state = state.copyWith(isLoading: false);

    return res.when(
      success: (data) {
        if (data is Map<String, dynamic>) {
          final orderJson = data['order'] is Map<String, dynamic> ? data['order'] as Map<String, dynamic> : data;
          final order = OrderModel.fromJson(orderJson);
          state = state.copyWith(orders: [order, ...state.orders]);
          _cartNotifier.clearCart();
          return order;
        }
        return null;
      },
      failure: (err) {
        state = state.copyWith(error: err.message);
        return null;
      },
    );
  }

  Future<bool> cancelOrder(int orderId) async {
    state = state.copyWith(isLoading: true, clearError: true);
    final res = await _api.post('/orders/$orderId/cancel');
    state = state.copyWith(isLoading: false);

    if (res.isSuccess) {
      final updated = state.orders.map((o) {
        if (o.id == orderId) {
          return OrderModel(
            id: o.id,
            orderNumber: o.orderNumber,
            status: 'cancelled',
            paymentMethod: o.paymentMethod,
            paymentStatus: o.paymentStatus,
            total: o.total,
            subtotal: o.subtotal,
            gstAmount: o.gstAmount,
            deliveryFee: o.deliveryFee,
            createdAt: o.createdAt,
            items: o.items,
            address: o.address,
            courier: o.courier,
            trackingNumber: o.trackingNumber,
            invoiceUrl: o.invoiceUrl,
            rxRequired: o.rxRequired,
            rxStatus: o.rxStatus,
          );
        }
        return o;
      }).toList();
      state = state.copyWith(orders: updated);
      return true;
    }
    return false;
  }

  Future<bool> reorder(int orderId) async {
    state = state.copyWith(isLoading: true);
    final res = await _api.post('/orders/$orderId/reorder');
    state = state.copyWith(isLoading: false);

    if (res.isSuccess) {
      await _cartNotifier.fetchCart();
      return true;
    }
    return false;
  }
}
