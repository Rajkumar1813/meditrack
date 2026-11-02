import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/medicine_model.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/pref_storage.dart';

@immutable
class CatalogState {
  final List<MedicineModel> medicines;
  final bool isLoading;
  final bool isRefreshing;
  final String? error;
  final String? nextCursor;
  final bool hasMore;
  final String? selectedCategory;
  final bool rxOnly;
  final bool inStockOnly;
  final String searchQuery;

  const CatalogState({
    this.medicines = const [],
    this.isLoading = false,
    this.isRefreshing = false,
    this.error,
    this.nextCursor,
    this.hasMore = true,
    this.selectedCategory,
    this.rxOnly = false,
    this.inStockOnly = false,
    this.searchQuery = '',
  });

  CatalogState copyWith({
    List<MedicineModel>? medicines,
    bool? isLoading,
    bool? isRefreshing,
    String? error,
    String? nextCursor,
    bool? hasMore,
    String? selectedCategory,
    bool? rxOnly,
    bool? inStockOnly,
    String? searchQuery,
    bool clearCategory = false,
    bool clearError = false,
  }) {
    return CatalogState(
      medicines: medicines ?? this.medicines,
      isLoading: isLoading ?? this.isLoading,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      error: clearError ? null : (error ?? this.error),
      nextCursor: nextCursor ?? this.nextCursor,
      hasMore: hasMore ?? this.hasMore,
      selectedCategory: clearCategory ? null : (selectedCategory ?? this.selectedCategory),
      rxOnly: rxOnly ?? this.rxOnly,
      inStockOnly: inStockOnly ?? this.inStockOnly,
      searchQuery: searchQuery ?? this.searchQuery,
    );
  }
}

final catalogControllerProvider = StateNotifierProvider<CatalogController, CatalogState>((ref) {
  final api = ref.watch(apiClientProvider);
  final prefs = ref.watch(prefStorageProvider);
  return CatalogController(api, prefs);
});

class CatalogController extends StateNotifier<CatalogState> {
  final ApiClient _api;
  final PrefStorage _prefs;
  static const _cacheKey = 'meditrack_cached_catalog';

  CatalogController(this._api, this._prefs) : super(const CatalogState()) {
    _loadCache();
    fetchMedicines();
  }

  void _loadCache() {
    try {
      final raw = _prefs.getPrefString(_cacheKey);
      if (raw != null && raw.isNotEmpty) {
        final decoded = jsonDecode(raw) as List;
        final cached = decoded.map((j) => MedicineModel.fromJson(j as Map<String, dynamic>)).toList();
        if (cached.isNotEmpty) {
          state = state.copyWith(medicines: cached);
        }
      }
    } catch (_) {}
  }

  Future<void> fetchMedicines({bool isRefresh = false}) async {
    if (state.isLoading && !isRefresh) return;

    state = state.copyWith(
      isLoading: !isRefresh,
      isRefreshing: isRefresh,
      clearError: true,
    );

    final queryParams = <String, dynamic>{
      'limit': 15,
    };
    if (state.selectedCategory != null) {
      queryParams['category'] = state.selectedCategory;
    }
    if (state.rxOnly) {
      queryParams['rx_required'] = 1;
    }
    if (state.inStockOnly) {
      queryParams['in_stock'] = 1;
    }
    if (state.searchQuery.isNotEmpty) {
      queryParams['q'] = state.searchQuery;
    }
    if (!isRefresh && state.nextCursor != null) {
      queryParams['cursor'] = state.nextCursor;
    }

    final res = await _api.get('/catalog', queryParameters: queryParams);

    res.when(
      success: (data) {
        List<MedicineModel> items = [];
        String? nextCursor;
        bool hasMore = false;

        if (data is Map<String, dynamic>) {
          final rawItems = data['items'] ?? data['medicines'] ?? [];
          if (rawItems is List) {
            items = rawItems.map((j) => MedicineModel.fromJson(j as Map<String, dynamic>)).toList();
          }
          nextCursor = data['next_cursor']?.toString();
          hasMore = data['has_more'] == true || nextCursor != null;
        } else if (data is List) {
          items = data.map((j) => MedicineModel.fromJson(j as Map<String, dynamic>)).toList();
        }

        final combined = isRefresh ? items : [...state.medicines, ...items];
        if (combined.isNotEmpty) {
          _prefs.setPrefString(_cacheKey, jsonEncode(combined.map((m) => m.toJson()).toList()));
        }
        state = state.copyWith(
          medicines: combined,
          nextCursor: nextCursor,
          hasMore: hasMore,
          isLoading: false,
          isRefreshing: false,
        );
      },
      failure: (error) {
        state = state.copyWith(
          isLoading: false,
          isRefreshing: false,
          error: error.message,
        );
      },
    );
  }

  void setCategory(String? category) {
    if (category == null) {
      state = state.copyWith(clearCategory: true);
    } else {
      state = state.copyWith(selectedCategory: category);
    }
    fetchMedicines(isRefresh: true);
  }

  void toggleRxFilter() {
    state = state.copyWith(rxOnly: !state.rxOnly);
    fetchMedicines(isRefresh: true);
  }

  void toggleInStockFilter() {
    state = state.copyWith(inStockOnly: !state.inStockOnly);
    fetchMedicines(isRefresh: true);
  }

  void setSearchQuery(String query) {
    state = state.copyWith(searchQuery: query);
    fetchMedicines(isRefresh: true);
  }
}
