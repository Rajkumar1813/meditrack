import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/address_model.dart';
import '../../../core/network/api_client.dart';

@immutable
class AddressState {
  final List<AddressModel> addresses;
  final bool isLoading;
  final String? error;

  const AddressState({
    this.addresses = const [],
    this.isLoading = false,
    this.error,
  });

  AddressModel? get defaultAddress {
    try {
      return addresses.firstWhere((a) => a.isDefault);
    } catch (_) {
      return addresses.isNotEmpty ? addresses.first : null;
    }
  }

  AddressState copyWith({
    List<AddressModel>? addresses,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return AddressState(
      addresses: addresses ?? this.addresses,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

final addressControllerProvider = StateNotifierProvider<AddressController, AddressState>((ref) {
  final api = ref.watch(apiClientProvider);
  return AddressController(api);
});

class AddressController extends StateNotifier<AddressState> {
  final ApiClient _api;

  AddressController(this._api) : super(const AddressState()) {
    fetchAddresses();
  }

  Future<void> fetchAddresses() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final res = await _api.get('/addresses');

    res.when(
      success: (data) {
        List<AddressModel> list = [];
        if (data is List) {
          list = data.map((a) => AddressModel.fromJson(a as Map<String, dynamic>)).toList();
        } else if (data is Map<String, dynamic> && data['items'] is List) {
          list = (data['items'] as List)
              .map((a) => AddressModel.fromJson(a as Map<String, dynamic>))
              .toList();
        }
        state = state.copyWith(addresses: list, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<AddressModel?> addAddress({
    required String name,
    required String phone,
    required String addressLine,
    required String city,
    required String stateName,
    required String pincode,
    bool isDefault = false,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);

    final res = await _api.post('/addresses', data: {
      'name': name,
      'phone': phone,
      'address_line': addressLine,
      'city': city,
      'state': stateName,
      'pincode': pincode,
      'is_default': isDefault ? 1 : 0,
    });

    state = state.copyWith(isLoading: false);

    return res.when(
      success: (data) {
        if (data is Map<String, dynamic>) {
          final addr = AddressModel.fromJson(data);
          state = state.copyWith(addresses: [...state.addresses, addr]);
          return addr;
        }
        return null;
      },
      failure: (err) {
        state = state.copyWith(error: err.message);
        return null;
      },
    );
  }

  Future<void> deleteAddress(int id) async {
    await _api.delete('/addresses/$id');
    state = state.copyWith(addresses: state.addresses.where((a) => a.id != id).toList());
  }
}

