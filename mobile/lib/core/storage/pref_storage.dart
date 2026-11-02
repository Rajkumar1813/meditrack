import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

final sharedPreferencesProvider = Provider<SharedPreferences>((ref) {
  throw UnimplementedError('Initialize sharedPreferencesProvider in main()');
});

final prefStorageProvider = Provider<PrefStorage>((ref) {
  final prefs = ref.watch(sharedPreferencesProvider);
  return PrefStorage(prefs);
});

class PrefStorage {
  final SharedPreferences _prefs;

  PrefStorage(this._prefs);

  static const _keyThemeMode = 'meditrack_theme_mode';
  static const _keyLocale = 'meditrack_locale';
  static const _keyBrandJson = 'meditrack_brand_cached_json';
  static const _keyBrandETag = 'meditrack_brand_etag';

  String getThemeMode() {
    return _prefs.getString(_keyThemeMode) ?? 'system';
  }

  Future<void> setThemeMode(String mode) async {
    await _prefs.setString(_keyThemeMode, mode);
  }

  String getLocale() {
    return _prefs.getString(_keyLocale) ?? 'en';
  }

  Future<void> setLocale(String locale) async {
    await _prefs.setString(_keyLocale, locale);
  }

  String? getBrandETag() {
    return _prefs.getString(_keyBrandETag);
  }

  Future<void> setBrandETag(String etag) async {
    await _prefs.setString(_keyBrandETag, etag);
  }

  Map<String, dynamic>? getCachedBrand() {
    final raw = _prefs.getString(_keyBrandJson);
    if (raw == null || raw.isEmpty) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<void> setCachedBrand(Map<String, dynamic> brandMap) async {
    await _prefs.setString(_keyBrandJson, jsonEncode(brandMap));
  }
}

