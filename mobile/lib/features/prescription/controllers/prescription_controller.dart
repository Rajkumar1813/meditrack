import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import '../models/prescription_model.dart';
import '../../../core/network/api_client.dart';

@immutable
class PrescriptionState {
  final List<PrescriptionModel> prescriptions;
  final bool isLoading;
  final String? error;

  const PrescriptionState({
    this.prescriptions = const [],
    this.isLoading = false,
    this.error,
  });

  PrescriptionState copyWith({
    List<PrescriptionModel>? prescriptions,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return PrescriptionState(
      prescriptions: prescriptions ?? this.prescriptions,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

final prescriptionControllerProvider = StateNotifierProvider<PrescriptionController, PrescriptionState>((ref) {
  final api = ref.watch(apiClientProvider);
  return PrescriptionController(api);
});

class PrescriptionController extends StateNotifier<PrescriptionState> {
  final ApiClient _api;

  PrescriptionController(this._api) : super(const PrescriptionState()) {
    fetchPrescriptions();
  }

  Future<void> fetchPrescriptions() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final res = await _api.get('/prescriptions');

    res.when(
      success: (data) {
        List<PrescriptionModel> items = [];
        if (data is Map<String, dynamic> && data['items'] is List) {
          items = (data['items'] as List)
              .map((p) => PrescriptionModel.fromJson(p as Map<String, dynamic>))
              .toList();
        } else if (data is List) {
          items = data.map((p) => PrescriptionModel.fromJson(p as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(prescriptions: items, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<PrescriptionModel?> uploadPrescription(File file) async {
    state = state.copyWith(isLoading: true, clearError: true);

    final fileName = file.path.split(Platform.pathSeparator).last;
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(file.path, filename: fileName),
    });

    final res = await _api.post('/prescriptions', data: formData);
    state = state.copyWith(isLoading: false);

    return res.when(
      success: (data) {
        if (data is Map<String, dynamic>) {
          final rxJson = data['prescription'] is Map<String, dynamic> ? data['prescription'] as Map<String, dynamic> : data;
          final rx = PrescriptionModel.fromJson(rxJson);
          state = state.copyWith(prescriptions: [rx, ...state.prescriptions]);
          return rx;
        }
        return null;
      },
      failure: (err) {
        state = state.copyWith(error: err.message);
        return null;
      },
    );
  }
}

