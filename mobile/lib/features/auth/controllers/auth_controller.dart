import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/user_model.dart';
import '../../../core/storage/secure_storage.dart';
import '../../../core/network/api_client.dart';
import '../../../core/push/fcm_service.dart';

@immutable
class AuthState {
  final bool isAuthenticated;
  final UserModel? user;
  final bool isLoading;
  final String? activeIdentifier;
  final String activeMode; // 'phone' or 'email'
  final int resendCooldown;
  final String? errorMessage;
  final bool isOtpSent;

  const AuthState({
    this.isAuthenticated = false,
    this.user,
    this.isLoading = false,
    this.activeIdentifier,
    this.activeMode = 'phone',
    this.resendCooldown = 0,
    this.errorMessage,
    this.isOtpSent = false,
  });

  AuthState copyWith({
    bool? isAuthenticated,
    UserModel? user,
    bool? isLoading,
    String? activeIdentifier,
    String? activeMode,
    int? resendCooldown,
    String? errorMessage,
    bool? isOtpSent,
    bool clearError = false,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      user: user ?? this.user,
      isLoading: isLoading ?? this.isLoading,
      activeIdentifier: activeIdentifier ?? this.activeIdentifier,
      activeMode: activeMode ?? this.activeMode,
      resendCooldown: resendCooldown ?? this.resendCooldown,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      isOtpSent: isOtpSent ?? this.isOtpSent,
    );
  }
}

final authControllerProvider = StateNotifierProvider<AuthController, AuthState>((ref) {
  final storage = ref.watch(secureStorageProvider);
  final apiClient = ref.watch(apiClientProvider);
  final fcmService = ref.watch(fcmServiceProvider);
  return AuthController(storage, apiClient, fcmService);
});

class AuthController extends StateNotifier<AuthState> {
  final SecureStorage _storage;
  final ApiClient _apiClient;
  final FcmService _fcmService;
  Timer? _cooldownTimer;

  AuthController(this._storage, this._apiClient, this._fcmService) : super(const AuthState()) {
    init();
  }

  Future<void> init() async {
    final token = await _storage.getAccessToken();
    final userMap = await _storage.getUser();

    if (token != null && token.isNotEmpty && userMap != null) {
      final user = UserModel.fromJson(userMap);
      state = state.copyWith(
        isAuthenticated: true,
        user: user,
      );
    }
  }

  void setMode(String mode) {
    state = state.copyWith(activeMode: mode, clearError: true);
  }

  void resetOtpFlow() {
    _cooldownTimer?.cancel();
    state = state.copyWith(
      isOtpSent: false,
      activeIdentifier: null,
      resendCooldown: 0,
      clearError: true,
      isLoading: false,
    );
  }

  void _startCooldown(int seconds) {
    _cooldownTimer?.cancel();
    state = state.copyWith(resendCooldown: seconds);
    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (state.resendCooldown <= 1) {
        state = state.copyWith(resendCooldown: 0);
        timer.cancel();
      } else {
        state = state.copyWith(resendCooldown: state.resendCooldown - 1);
      }
    });
  }

  Future<bool> requestOtp(String rawIdentifier, {String? mode}) async {
    final activeMode = mode ?? state.activeMode;
    String cleanIdentifier = rawIdentifier.trim();

    if (activeMode == 'phone') {
      final digits = cleanIdentifier.replaceAll(RegExp(r'\D'), '');
      if (digits.length < 10) {
        state = state.copyWith(errorMessage: 'Please enter a valid 10-digit mobile number');
        return false;
      }
      cleanIdentifier = digits.length == 10 ? '+91$digits' : '+$digits';
    } else {
      if (!RegExp(r'^[^@]+@[^@]+\.[^@]+').hasMatch(cleanIdentifier)) {
        state = state.copyWith(errorMessage: 'Please enter a valid email address');
        return false;
      }
      cleanIdentifier = cleanIdentifier.toLowerCase();
    }

    state = state.copyWith(isLoading: true, clearError: true);

    final result = await _apiClient.post(
      '/auth/otp/request',
      data: {
        'identifier': cleanIdentifier,
        'type': activeMode,
      },
    );

    state = state.copyWith(isLoading: false);

    return result.when(
      success: (data) {
        final cooldown = (data is Map<String, dynamic> && data['cooldown_seconds'] is int)
            ? data['cooldown_seconds'] as int
            : 60;

        state = state.copyWith(
          isOtpSent: true,
          activeIdentifier: cleanIdentifier,
          activeMode: activeMode,
          clearError: true,
        );

        _startCooldown(cooldown);
        return true;
      },
      failure: (error) {
        state = state.copyWith(errorMessage: error.message);
        return false;
      },
    );
  }

  Future<bool> resendOtp() async {
    if (state.resendCooldown > 0 || state.activeIdentifier == null) return false;
    return await requestOtp(state.activeIdentifier!, mode: state.activeMode);
  }

  Future<bool> verifyOtp(String code) async {
    final cleanCode = code.trim();
    if (cleanCode.length != 6) {
      state = state.copyWith(errorMessage: 'Please enter the complete 6-digit OTP');
      return false;
    }

    state = state.copyWith(isLoading: true, clearError: true);

    final result = await _apiClient.post(
      '/auth/otp/verify',
      data: {
        'identifier': state.activeIdentifier,
        'code': cleanCode,
        'client': 'mobile',
      },
    );

    state = state.copyWith(isLoading: false);

    return result.when(
      success: (data) async {
        if (data is Map<String, dynamic>) {
          final userJson = data['user'] as Map<String, dynamic>?;
          final tokens = data['tokens'] as Map<String, dynamic>?;

          if (userJson != null && tokens != null) {
            final user = UserModel.fromJson(userJson);
            final accessToken = tokens['access_token']?.toString() ?? '';
            final refreshToken = tokens['refresh_token']?.toString() ?? '';

            await _storage.setAccessToken(accessToken);
            if (refreshToken.isNotEmpty) {
              await _storage.setRefreshToken(refreshToken);
            }
            await _storage.setUser(userJson);

            state = state.copyWith(
              isAuthenticated: true,
              user: user,
              isOtpSent: false,
              clearError: true,
            );

            // Register FCM device token
            final fcmToken = await _storage.getDeviceToken();
            if (fcmToken != null && fcmToken.isNotEmpty) {
              _fcmService.registerDeviceToken(fcmToken);
            }

            return true;
          }
        }
        state = state.copyWith(errorMessage: 'Invalid response from server.');
        return false;
      },
      failure: (error) {
        state = state.copyWith(errorMessage: error.message);
        return false;
      },
    );
  }

  Future<void> logout() async {
    state = state.copyWith(isLoading: true);
    try {
      await _apiClient.post('/auth/logout');
    } catch (_) {}
    await _storage.clearAuth();
    _cooldownTimer?.cancel();
    state = const AuthState();
  }

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    super.dispose();
  }
}

