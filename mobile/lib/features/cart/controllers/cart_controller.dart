import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/cart_item_model.dart';
import '../../catalog/models/medicine_model.dart';
import '../../../core/network/api_client.dart';

@immutable
class CartState {
  final List<CartItemModel> items;
  final bool isLoading;
  final String? error;

  const CartState({
    this.items = const [],
    this.isLoading = false,
    this.error,
  });

  int get totalCount => items.fold(0, (sum, i) => sum + i.quantity);
  double get subtotal => items.fold(0.0, (sum, i) => sum + i.totalPrice);
  double get totalMrp => items.fold(0.0, (sum, i) => sum + i.totalMrp);
  double get totalSavings => (totalMrp - subtotal).clamp(0, double.infinity);
  double get deliveryFee => subtotal >= 500 || items.isEmpty ? 0.0 : 40.0;
  double get estimatedGst => (subtotal * 0.12);
  double get total => subtotal + deliveryFee;
  bool get requiresPrescription => items.any((i) => i.requiresPrescription);

  CartState copyWith({
    List<CartItemModel>? items,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return CartState(
      items: items ?? this.items,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

final cartControllerProvider = StateNotifierProvider<CartController, CartState>((ref) {
  final api = ref.watch(apiClientProvider);
  return CartController(api);
});

class CartController extends StateNotifier<CartState> {
  final ApiClient _api;

  CartController(this._api) : super(const CartState()) {
    fetchCart();
  }

  Future<void> fetchCart() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final res = await _api.get('/cart');

    res.when(
      success: (data) {
        List<CartItemModel> items = [];
        if (data is Map<String, dynamic> && data['items'] is List) {
          items = (data['items'] as List)
              .map((i) => CartItemModel.fromJson(i as Map<String, dynamic>))
              .toList();
        } else if (data is List) {
          items = data.map((i) => CartItemModel.fromJson(i as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(items: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<bool> addItem(MedicineModel medicine, {int quantity = 1}) async {
    state = state.copyWith(isLoading: true, clearError: true);

    // Optimistic UI update
    final existingIdx = state.items.indexWhere((i) => i.medicineId == medicine.id);
    List<CartItemModel> updatedItems;
    if (existingIdx >= 0) {
      final existing = state.items[existingIdx];
      updatedItems = List.from(state.items);
      updatedItems[existingIdx] = existing.copyWith(quantity: existing.quantity + quantity);
    } else {
      updatedItems = [
        ...state.items,
        CartItemModel(
          medicineId: medicine.id,
          medicineName: medicine.name,
          quantity: quantity,
          price: medicine.price,
          mrp: medicine.mrp,
          requiresPrescription: medicine.requiresPrescription,
          stockAvailable: medicine.stock,
        ),
      ];
    }
    state = state.copyWith(items: updatedItems, isLoading: false);

    final res = await _api.post('/cart', data: {
      'medicine_id': medicine.id,
      'quantity': quantity,
    });

    if (res.isFailure) {
      // Revert if API fails
      fetchCart();
      return false;
    }
    return true;
  }

  Future<void> updateQuantity(int medicineId, int quantity) async {
    if (quantity <= 0) {
      await removeItem(medicineId);
      return;
    }

    final updated = state.items.map((i) {
      if (i.medicineId == medicineId) {
        return i.copyWith(quantity: quantity);
      }
      return i;
    }).toList();

    state = state.copyWith(items: updated);

    await _api.put('/cart/$medicineId', data: {'quantity': quantity});
  }

  Future<void> removeItem(int medicineId) async {
    final updated = state.items.where((i) => i.medicineId != medicineId).toList();
    state = state.copyWith(items: updated);

    await _api.delete('/cart/$medicineId');
  }

  Future<void> clearCart() async {
    state = state.copyWith(items: []);
    await _api.delete('/cart');
  }
}

