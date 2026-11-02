import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import 'brand_tokens.dart';
import '../storage/pref_storage.dart';
import '../network/api_client.dart';

@immutable
class BrandState {
  final String name;
  final String tagline;
  final String adminTagline;
  final String currencySymbol;
  final String supportEmail;
  final String supportPhone;
  final Color primaryColor;
  final Color accentColor;
  final String? etag;

  const BrandState({
    required this.name,
    required this.tagline,
    required this.adminTagline,
    required this.currencySymbol,
    required this.supportEmail,
    required this.supportPhone,
    required this.primaryColor,
    required this.accentColor,
    this.etag,
  });

  factory BrandState.fromTokens() {
    return BrandState(
      name: BrandTokens.appName,
      tagline: BrandTokens.tagline,
      adminTagline: BrandTokens.adminTagline,
      currencySymbol: BrandTokens.currencySymbol,
      supportEmail: BrandTokens.supportEmail,
      supportPhone: BrandTokens.supportPhone,
      primaryColor: BrandTokens.primary[600] ?? const Color(0xFF059669),
      accentColor: BrandTokens.accent,
    );
  }

  factory BrandState.fromJson(Map<String, dynamic> json, {String? etag}) {
    Color parseHex(dynamic hex, Color fallback) {
      if (hex == null || hex is! String || !hex.startsWith('#')) return fallback;
      final clean = hex.replaceFirst('#', '');
      final val = int.tryParse('FF$clean', radix: 16);
      return val != null ? Color(val) : fallback;
    }

    final app = json['app'] as Map<String, dynamic>? ?? json;
    final colors = json['colors'] as Map<String, dynamic>? ?? {};
    final primaryMap = colors['primary'] as Map<String, dynamic>? ?? {};

    return BrandState(
      name: (app['name'] ?? app['appName'] ?? BrandTokens.appName).toString(),
      tagline: (app['tagline'] ?? BrandTokens.tagline).toString(),
      adminTagline: (app['adminTagline'] ?? BrandTokens.adminTagline).toString(),
      currencySymbol: (app['currencySymbol'] ?? app['currency_symbol'] ?? BrandTokens.currencySymbol).toString(),
      supportEmail: (app['supportEmail'] ?? BrandTokens.supportEmail).toString(),
      supportPhone: (app['supportPhone'] ?? BrandTokens.supportPhone).toString(),
      primaryColor: parseHex(primaryMap['600'], BrandTokens.primary[600] ?? const Color(0xFF059669)),
      accentColor: parseHex(colors['accent'], BrandTokens.accent),
      etag: etag,
    );
  }

  BrandState copyWith({
    String? name,
    String? tagline,
    String? adminTagline,
    String? currencySymbol,
    String? supportEmail,
    String? supportPhone,
    Color? primaryColor,
    Color? accentColor,
    String? etag,
  }) {
    return BrandState(
      name: name ?? this.name,
      tagline: tagline ?? this.tagline,
      adminTagline: adminTagline ?? this.adminTagline,
      currencySymbol: currencySymbol ?? this.currencySymbol,
      supportEmail: supportEmail ?? this.supportEmail,
      supportPhone: supportPhone ?? this.supportPhone,
      primaryColor: primaryColor ?? this.primaryColor,
      accentColor: accentColor ?? this.accentColor,
      etag: etag ?? this.etag,
    );
  }
}

final brandControllerProvider = StateNotifierProvider<BrandController, BrandState>((ref) {
  final prefs = ref.watch(prefStorageProvider);
  final dio = ref.watch(dioProvider);
  return BrandController(prefs, dio);
});

class BrandController extends StateNotifier<BrandState> {
  final PrefStorage _prefs;
  final Dio _dio;

  BrandController(this._prefs, this._dio) : super(BrandState.fromTokens()) {
    _init();
  }

  void _init() {
    final cached = _prefs.getCachedBrand();
    final cachedETag = _prefs.getBrandETag();
    if (cached != null) {
      state = BrandState.fromJson(cached, etag: cachedETag);
    }
    // Refresh brand asynchronously from API
    refreshBrand();
  }

  Future<void> refreshBrand() async {
    try {
      final currentETag = _prefs.getBrandETag();
      final headers = <String, dynamic>{};
      if (currentETag != null && currentETag.isNotEmpty) {
        headers['If-None-Match'] = currentETag;
      }

      final response = await _dio.get(
        '/brand',
        options: Options(
          headers: headers,
          validateStatus: (status) => status != null && (status == 200 || status == 304),
        ),
      );

      if (response.statusCode == 304) {
        // Not modified; keep cached state
        return;
      }

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? (response.data['data'] ?? response.data) as Map<String, dynamic>
            : null;

        if (data != null) {
          final newETag = response.headers.value('etag');
          await _prefs.setCachedBrand(data);
          if (newETag != null) {
            await _prefs.setBrandETag(newETag);
          }
          state = BrandState.fromJson(data, etag: newETag);
        }
      }
    } catch (_) {
      // Offline or network error: gracefully retain cached/token values
    }
  }
}

