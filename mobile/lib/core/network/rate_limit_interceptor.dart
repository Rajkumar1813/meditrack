import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final rateLimitProvider = StateNotifierProvider<RateLimitNotifier, int>((ref) {
  return RateLimitNotifier();
});

class RateLimitNotifier extends StateNotifier<int> {
  Timer? _timer;

  RateLimitNotifier() : super(0);

  void triggerRateLimit(int seconds) {
    _timer?.cancel();
    state = seconds > 0 ? seconds : 60;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (state <= 1) {
        state = 0;
        timer.cancel();
      } else {
        state = state - 1;
      }
    });
  }

  void reset() {
    _timer?.cancel();
    state = 0;
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }
}

class RateLimitInterceptor extends Interceptor {
  final RateLimitNotifier _notifier;

  RateLimitInterceptor(this._notifier);

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 429) {
      int retrySeconds = 60;

      final headerVal = err.response?.headers.value('retry-after');
      if (headerVal != null) {
        retrySeconds = int.tryParse(headerVal) ?? 60;
      } else if (err.response?.data is Map<String, dynamic>) {
        final data = err.response!.data as Map<String, dynamic>;
        final errObj = data['error'];
        if (errObj is Map<String, dynamic> && errObj['retry_after'] is int) {
          retrySeconds = errObj['retry_after'] as int;
        }
      }

      _notifier.triggerRateLimit(retrySeconds);
    }
    super.onError(err, handler);
  }
}

