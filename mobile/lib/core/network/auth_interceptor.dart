import 'dart:async';
import 'package:dio/dio.dart';
import '../storage/secure_storage.dart';

class AuthInterceptor extends Interceptor {
  final SecureStorage _storage;
  final Dio _refreshDio;
  Completer<String?>? _refreshCompleter;

  AuthInterceptor(this._storage, this._refreshDio);

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await _storage.getAccessToken();
    if (token != null && token.isNotEmpty && !options.headers.containsKey('Authorization')) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    final statusCode = err.response?.statusCode;
    final isRefreshCall = err.requestOptions.path.contains('/auth/refresh');
    final isVerifyCall = err.requestOptions.path.contains('/auth/otp/verify');
    final isAlreadyRetried = err.requestOptions.extra['_retry'] == true;

    if (statusCode == 401 && !isRefreshCall && !isVerifyCall && !isAlreadyRetried) {
      err.requestOptions.extra['_retry'] = true;

      try {
        final newToken = await _refreshToken();
        if (newToken != null && newToken.isNotEmpty) {
          err.requestOptions.headers['Authorization'] = 'Bearer $newToken';
          final response = await _refreshDio.fetch(err.requestOptions);
          return handler.resolve(response);
        }
      } catch (e) {
        await _storage.clearAuth();
        return handler.next(err);
      }
    }

    return handler.next(err);
  }

  Future<String?> _refreshToken() async {
    if (_refreshCompleter != null) {
      return _refreshCompleter!.future;
    }

    _refreshCompleter = Completer<String?>();

    try {
      final currentRefresh = await _storage.getRefreshToken();
      if (currentRefresh == null || currentRefresh.isEmpty) {
        _refreshCompleter!.complete(null);
        return null;
      }

      final res = await _refreshDio.post(
        '/auth/refresh',
        data: {
          'refresh_token': currentRefresh,
          'client': 'mobile',
        },
      );

      if (res.statusCode == 200 && res.data is Map<String, dynamic>) {
        final data = res.data as Map<String, dynamic>;
        final payload = data['data'] is Map<String, dynamic> ? data['data'] as Map<String, dynamic> : data;
        final tokens = payload['tokens'] is Map<String, dynamic> ? payload['tokens'] as Map<String, dynamic> : payload;

        final newAccess = tokens['access_token']?.toString();
        final newRefresh = tokens['refresh_token']?.toString();

        if (newAccess != null) {
          await _storage.setAccessToken(newAccess);
          if (newRefresh != null && newRefresh.isNotEmpty) {
            await _storage.setRefreshToken(newRefresh);
          }
          if (payload['user'] is Map<String, dynamic>) {
            await _storage.setUser(payload['user'] as Map<String, dynamic>);
          }
          _refreshCompleter!.complete(newAccess);
          return newAccess;
        }
      }

      _refreshCompleter!.complete(null);
      return null;
    } catch (e) {
      _refreshCompleter!.completeError(e);
      rethrow;
    } finally {
      _refreshCompleter = null;
    }
  }
}

