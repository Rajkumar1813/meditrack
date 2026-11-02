import 'package:flutter/foundation.dart';

@immutable
sealed class ApiResult<T> {
  const ApiResult();

  const factory ApiResult.success(T data) = ApiSuccess<T>;
  const factory ApiResult.failure(ApiError error) = ApiFailure<T>;

  bool get isSuccess => this is ApiSuccess<T>;
  bool get isFailure => this is ApiFailure<T>;

  T? get dataOrNull => switch (this) {
        ApiSuccess<T>(data: final d) => d,
        ApiFailure<T>() => null,
      };

  ApiError? get errorOrNull => switch (this) {
        ApiSuccess<T>() => null,
        ApiFailure<T>(error: final e) => e,
      };

  R when<R>({
    required R Function(T data) success,
    required R Function(ApiError error) failure,
  }) {
    return switch (this) {
      ApiSuccess<T>(data: final d) => success(d),
      ApiFailure<T>(error: final e) => failure(e),
    };
  }
}

class ApiSuccess<T> extends ApiResult<T> {
  final T data;
  const ApiSuccess(this.data);
}

class ApiFailure<T> extends ApiResult<T> {
  final ApiError error;
  const ApiFailure(this.error);
}

@immutable
class ApiError {
  final String code;
  final String message;
  final int? statusCode;
  final int? retryAfterSeconds;
  final Map<String, List<String>>? fieldErrors;

  const ApiError({
    required this.code,
    required this.message,
    this.statusCode,
    this.retryAfterSeconds,
    this.fieldErrors,
  });

  factory ApiError.fromResponse(int? statusCode, dynamic data) {
    if (data is Map<String, dynamic>) {
      final err = data['error'] is Map<String, dynamic> ? data['error'] as Map<String, dynamic> : data;
      final code = (err['code'] ?? 'API_ERROR').toString();
      final msg = (err['message'] ?? data['message'] ?? 'An error occurred. Please try again.').toString();
      final retryAfter = err['retry_after'] is int ? err['retry_after'] as int : null;

      Map<String, List<String>>? fields;
      if (err['fields'] is Map<String, dynamic>) {
        fields = (err['fields'] as Map<String, dynamic>).map((key, value) {
          if (value is List) {
            return MapEntry(key, value.map((e) => e.toString()).toList());
          }
          return MapEntry(key, [value.toString()]);
        });
      }

      return ApiError(
        code: code,
        message: msg,
        statusCode: statusCode,
        retryAfterSeconds: retryAfter,
        fieldErrors: fields,
      );
    }

    return ApiError(
      code: statusCode == 429 ? 'RATE_LIMITED' : 'HTTP_$statusCode',
      message: 'Server error ($statusCode)',
      statusCode: statusCode,
    );
  }

  factory ApiError.offline() {
    return const ApiError(
      code: 'OFFLINE',
      message: 'No internet connection. Please check your network.',
      statusCode: 0,
    );
  }

  factory ApiError.unexpected([Object? error]) {
    return ApiError(
      code: 'UNEXPECTED',
      message: error?.toString() ?? 'Unexpected application error',
    );
  }
}
