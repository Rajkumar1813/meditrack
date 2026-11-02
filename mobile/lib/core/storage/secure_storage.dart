import 'dart:convert';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final secureStorageProvider = Provider<SecureStorage>((ref) {
  return SecureStorage(const FlutterSecureStorage(
    aOptions: AndroidOptions(resetOnError: true),
  ));
});

class SecureStorage {
  final FlutterSecureStorage _storage;

  SecureStorage(this._storage);

  static const _keyAccessToken = 'meditrack_access_token';
  static const _keyRefreshToken = 'meditrack_refresh_token';
  static const _keyUser = 'meditrack_current_user';
  static const _keyDeviceToken = 'meditrack_device_token';

  Future<String?> getAccessToken() async {
    return await _storage.read(key: _keyAccessToken);
  }

  Future<void> setAccessToken(String token) async {
    await _storage.write(key: _keyAccessToken, value: token);
  }

  Future<String?> getRefreshToken() async {
    return await _storage.read(key: _keyRefreshToken);
  }

  Future<void> setRefreshToken(String token) async {
    await _storage.write(key: _keyRefreshToken, value: token);
  }

  Future<Map<String, dynamic>?> getUser() async {
    final raw = await _storage.read(key: _keyUser);
    if (raw == null || raw.isEmpty) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<void> setUser(Map<String, dynamic> user) async {
    await _storage.write(key: _keyUser, value: jsonEncode(user));
  }

  Future<String?> getDeviceToken() async {
    return await _storage.read(key: _keyDeviceToken);
  }

  Future<void> setDeviceToken(String token) async {
    await _storage.write(key: _keyDeviceToken, value: token);
  }

  Future<void> clearAuth() async {
    await _storage.delete(key: _keyAccessToken);
    await _storage.delete(key: _keyRefreshToken);
    await _storage.delete(key: _keyUser);
  }

  Future<void> clearAll() async {
    await _storage.deleteAll();
  }
}
