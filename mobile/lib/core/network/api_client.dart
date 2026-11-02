import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import '../storage/secure_storage.dart';
import 'auth_interceptor.dart';
import 'rate_limit_interceptor.dart';
import 'api_result.dart';

final apiBaseUrlProvider = Provider<String>((ref) {
  // Default to Android Emulator host loopback; can be overridden in tests or config
  return const String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8080/api/v1');
});

final isOnlineProvider = StreamProvider<bool>((ref) {
  return Connectivity().onConnectivityChanged.map((results) {
    if (results.isEmpty) return false;
    return results.any((r) => r != ConnectivityResult.none);
  });
});

final dioProvider = Provider<Dio>((ref) {
  final baseUrl = ref.watch(apiBaseUrlProvider);
  final storage = ref.watch(secureStorageProvider);
  final rateLimitNotifier = ref.watch(rateLimitProvider.notifier);

  final baseOptions = BaseOptions(
    baseUrl: baseUrl,
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 15),
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  );

  final refreshDio = Dio(baseOptions);

  final dio = Dio(baseOptions);
  dio.interceptors.addAll([
    AuthInterceptor(storage, refreshDio),
    RateLimitInterceptor(rateLimitNotifier),
  ]);

  return dio;
});

final apiClientProvider = Provider<ApiClient>((ref) {
  final dio = ref.watch(dioProvider);
  return ApiClient(dio);
});

class ApiClient {
  final Dio _dio;

  ApiClient(this._dio);

  Future<ApiResult<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? fromJson,
  }) async {
    return _request(() => _dio.get(path, queryParameters: queryParameters, options: options), fromJson);
  }

  Future<ApiResult<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? fromJson,
  }) async {
    return _request(() => _dio.post(path, data: data, queryParameters: queryParameters, options: options), fromJson);
  }

  Future<ApiResult<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? fromJson,
  }) async {
    return _request(() => _dio.put(path, data: data, queryParameters: queryParameters, options: options), fromJson);
  }

  Future<ApiResult<T>> patch<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? fromJson,
  }) async {
    return _request(() => _dio.patch(path, data: data, queryParameters: queryParameters, options: options), fromJson);
  }

  Future<ApiResult<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? fromJson,
  }) async {
    return _request(() => _dio.delete(path, data: data, queryParameters: queryParameters, options: options), fromJson);
  }

  Future<ApiResult<T>> _request<T>(
    Future<Response> Function() requestFn,
    T Function(dynamic data)? fromJson,
  ) async {
    try {
      final res = await requestFn();
      final body = res.data;

      if (body is Map<String, dynamic> && body.containsKey('data')) {
        final data = body['data'];
        if (fromJson != null) {
          return ApiResult.success(fromJson(data));
        }
        return ApiResult.success(data as T);
      }

      if (fromJson != null) {
        return ApiResult.success(fromJson(body));
      }
      return ApiResult.success(body as T);
    } on DioException catch (dioErr) {
      if (dioErr.type == DioExceptionType.connectionError ||
          dioErr.type == DioExceptionType.connectionTimeout) {
        return ApiResult.failure(ApiError.offline());
      }
      return ApiResult.failure(
        ApiError.fromResponse(dioErr.response?.statusCode, dioErr.response?.data),
      );
    } catch (e) {
      return ApiResult.failure(ApiError.unexpected(e));
    }
  }
}

