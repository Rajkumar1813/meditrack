import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/dashboard_stats_model.dart';
import '../models/rack_model.dart';
import '../../catalog/models/medicine_model.dart';
import '../../orders/models/order_model.dart';
import '../../prescription/models/prescription_model.dart';
import '../../../core/network/api_client.dart';

@immutable
class OwnerState {
  final DashboardStatsModel? stats;
  final List<MedicineModel> inventory;
  final List<BatchModel> expiringBatches;
  final List<BatchModel> expiredBatches;
  final List<OrderModel> orders;
  final List<PrescriptionModel> pendingRx;
  final List<RackModel> racks;
  final bool isLoading;
  final String? error;

  const OwnerState({
    this.stats,
    this.inventory = const [],
    this.expiringBatches = const [],
    this.expiredBatches = const [],
    this.orders = const [],
    this.pendingRx = const [],
    this.racks = const [],
    this.isLoading = false,
    this.error,
  });

  OwnerState copyWith({
    DashboardStatsModel? stats,
    List<MedicineModel>? inventory,
    List<BatchModel>? expiringBatches,
    List<BatchModel>? expiredBatches,
    List<OrderModel>? orders,
    List<PrescriptionModel>? pendingRx,
    List<RackModel>? racks,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return OwnerState(
      stats: stats ?? this.stats,
      inventory: inventory ?? this.inventory,
      expiringBatches: expiringBatches ?? this.expiringBatches,
      expiredBatches: expiredBatches ?? this.expiredBatches,
      orders: orders ?? this.orders,
      pendingRx: pendingRx ?? this.pendingRx,
      racks: racks ?? this.racks,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

final ownerControllerProvider = StateNotifierProvider<OwnerController, OwnerState>((ref) {
  final api = ref.watch(apiClientProvider);
  return OwnerController(api);
});

class OwnerController extends StateNotifier<OwnerState> {
  final ApiClient _api;

  OwnerController(this._api) : super(const OwnerState()) {
    fetchStats();
  }

  Future<void> fetchStats() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final res = await _api.get('/admin/dashboard/stats');

    res.when(
      success: (data) {
        if (data is Map<String, dynamic>) {
          state = state.copyWith(
            stats: DashboardStatsModel.fromJson(data),
            isLoading: false,
          );
        }
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<void> fetchInventory({String? query}) async {
    state = state.copyWith(isLoading: true, clearError: true);
    final queryParams = <String, dynamic>{};
    if (query != null && query.isNotEmpty) {
      queryParams['q'] = query;
    }

    final res = await _api.get('/admin/medicines', queryParameters: queryParams);

    res.when(
      success: (data) {
        List<MedicineModel> items = [];
        if (data is Map<String, dynamic> && data['items'] is List) {
          items = (data['items'] as List).map((j) => MedicineModel.fromJson(j as Map<String, dynamic>)).toList();
        } else if (data is List) {
          items = data.map((j) => MedicineModel.fromJson(j as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(inventory: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<bool> addMedicine(Map<String, dynamic> data) async {
    state = state.copyWith(isLoading: true, clearError: true);
    final res = await _api.post('/admin/medicines', data: data);
    state = state.copyWith(isLoading: false);

    return res.when(
      success: (itemJson) {
        if (itemJson is Map<String, dynamic>) {
          final med = MedicineModel.fromJson(itemJson);
          state = state.copyWith(inventory: [med, ...state.inventory]);
          return true;
        }
        fetchInventory();
        return true;
      },
      failure: (err) {
        state = state.copyWith(error: err.message);
        return false;
      },
    );
  }

  Future<void> fetchExpiring({int days = 30}) async {
    state = state.copyWith(isLoading: true);
    final res = await _api.get('/admin/expiring', queryParameters: {'days': days});

    res.when(
      success: (data) {
        List<BatchModel> items = [];
        if (data is Map<String, dynamic> && data['batches'] is List) {
          items = (data['batches'] as List).map((b) => BatchModel.fromJson(b as Map<String, dynamic>)).toList();
        } else if (data is List) {
          items = data.map((b) => BatchModel.fromJson(b as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(expiringBatches: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<void> fetchExpired() async {
    state = state.copyWith(isLoading: true);
    final res = await _api.get('/admin/expired');

    res.when(
      success: (data) {
        List<BatchModel> items = [];
        if (data is Map<String, dynamic> && data['batches'] is List) {
          items = (data['batches'] as List).map((b) => BatchModel.fromJson(b as Map<String, dynamic>)).toList();
        } else if (data is List) {
          items = data.map((b) => BatchModel.fromJson(b as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(expiredBatches: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<void> fetchRacks() async {
    state = state.copyWith(isLoading: true);
    final res = await _api.get('/admin/racks');

    res.when(
      success: (data) {
        List<RackModel> items = [];
        if (data is List) {
          items = data.map((r) => RackModel.fromJson(r as Map<String, dynamic>)).toList();
        } else if (data is Map<String, dynamic> && data['items'] is List) {
          items = (data['items'] as List).map((r) => RackModel.fromJson(r as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(racks: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<void> fetchOrders({String? status}) async {
    state = state.copyWith(isLoading: true);
    final queryParams = <String, dynamic>{};
    if (status != null && status.isNotEmpty && status != 'all') {
      queryParams['status'] = status;
    }

    final res = await _api.get('/admin/orders', queryParameters: queryParams);

    res.when(
      success: (data) {
        List<OrderModel> items = [];
        if (data is Map<String, dynamic> && data['items'] is List) {
          items = (data['items'] as List).map((o) => OrderModel.fromJson(o as Map<String, dynamic>)).toList();
        } else if (data is List) {
          items = data.map((o) => OrderModel.fromJson(o as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(orders: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<bool> updateOrderStatus(int orderId, String newStatus, {String? courier, String? trackingNo, String? note}) async {
    final payload = <String, dynamic>{'status': newStatus};
    if (courier != null) payload['courier'] = courier;
    if (trackingNo != null) payload['tracking_no'] = trackingNo;
    if (note != null) payload['note'] = note;

    final res = await _api.patch('/admin/orders/$orderId/status', data: payload);

    if (res.isSuccess) {
      fetchOrders();
      return true;
    }
    return false;
  }

  Future<void> fetchPendingRx() async {
    state = state.copyWith(isLoading: true);
    final res = await _api.get('/admin/prescriptions', queryParameters: {'status': 'pending'});

    res.when(
      success: (data) {
        List<PrescriptionModel> items = [];
        if (data is Map<String, dynamic> && data['items'] is List) {
          items = (data['items'] as List).map((p) => PrescriptionModel.fromJson(p as Map<String, dynamic>)).toList();
        } else if (data is List) {
          items = data.map((p) => PrescriptionModel.fromJson(p as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(pendingRx: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<bool> reviewPrescription(int rxId, String decision, {String? reason, String? validUntil}) async {
    final payload = <String, dynamic>{'status': decision};
    if (reason != null) payload['rejection_reason'] = reason;
    if (validUntil != null) payload['valid_until'] = validUntil;

    final res = await _api.patch('/admin/prescriptions/$rxId/review', data: payload);

    if (res.isSuccess) {
      fetchPendingRx();
      return true;
    }
    return false;
  }
}
