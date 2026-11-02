import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../storage/secure_storage.dart';
import '../network/api_client.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  debugPrint('FCM background message received: ${message.messageId}');
}

final fcmServiceProvider = Provider<FcmService>((ref) {
  final storage = ref.watch(secureStorageProvider);
  final apiClient = ref.watch(apiClientProvider);
  return FcmService(storage, apiClient);
});

class FcmService {
  final SecureStorage _storage;
  final ApiClient _apiClient;
  final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();

  void Function(String route)? onNotificationTapped;

  FcmService(this._storage, this._apiClient);

  Future<void> initialize({void Function(String route)? onNavigate}) async {
    onNotificationTapped = onNavigate;

    try {
      // 1. Initialize local notifications
      const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
      const initSettings = InitializationSettings(android: androidSettings);

      await _localNotifications.initialize(
        settings: initSettings,
        onDidReceiveNotificationResponse: (response) {
          final payload = response.payload;
          if (payload != null && payload.isNotEmpty) {
            _handlePayload(payload);
          }
        },
      );

      // Create high importance Android notification channel
      const androidChannel = AndroidNotificationChannel(
        'meditrack_notifications',
        'MediTrack Notifications',
        description: 'Updates regarding orders, refills, and prescriptions',
        importance: Importance.high,
      );

      await _localNotifications
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(androidChannel);

      // 2. Initialize Firebase Messaging if Firebase is configured
      if (Firebase.apps.isNotEmpty) {
        FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

        final messaging = FirebaseMessaging.instance;
        final settings = await messaging.requestPermission(
          alert: true,
          badge: true,
          sound: true,
        );

        if (settings.authorizationStatus == AuthorizationStatus.authorized) {
          // ignore: deprecated_member_use
          final token = await messaging.getToken();
          if (token != null) {
            await registerDeviceToken(token);
          }

          // ignore: deprecated_member_use
          messaging.onTokenRefresh.listen((newToken) {
            registerDeviceToken(newToken);
          });
        }

        // Foreground messages
        FirebaseMessaging.onMessage.listen((RemoteMessage message) {
          _showForegroundNotification(message);
        });

        // App opened from background notification tap
        FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
          if (message.data.isNotEmpty) {
            _handlePayload(jsonEncode(message.data));
          }
        });

        // App opened from terminated state
        final initialMessage = await messaging.getInitialMessage();
        if (initialMessage != null && initialMessage.data.isNotEmpty) {
          _handlePayload(jsonEncode(initialMessage.data));
        }
      }
    } catch (e) {
      debugPrint('FCM initialization warning (running without google-services.json): $e');
    }
  }

  Future<void> registerDeviceToken(String token) async {
    final cached = await _storage.getDeviceToken();
    if (cached == token) return; // Already registered

    try {
      final res = await _apiClient.post('/devices', data: {
        'token': token,
        'platform': 'android',
      });

      if (res.isSuccess) {
        await _storage.setDeviceToken(token);
      }
    } catch (e) {
      debugPrint('Failed to register device token on backend: $e');
    }
  }

  Future<void> unregisterDevice() async {
    try {
      await _apiClient.delete('/devices');
      await _storage.setDeviceToken('');
    } catch (_) {}
  }

  Future<void> _showForegroundNotification(RemoteMessage message) async {
    final notification = message.notification;
    final title = notification?.title ?? 'MediTrack';
    final body = notification?.body ?? '';

    const androidDetails = AndroidNotificationDetails(
      'meditrack_notifications',
      'MediTrack Notifications',
      channelDescription: 'Updates regarding orders, refills, and prescriptions',
      importance: Importance.high,
      priority: Priority.high,
      icon: '@mipmap/ic_launcher',
    );

    const platformDetails = NotificationDetails(android: androidDetails);

    await _localNotifications.show(
      id: message.hashCode,
      title: title,
      body: body,
      notificationDetails: platformDetails,
      payload: jsonEncode(message.data),
    );
  }

  void _handlePayload(String rawPayload) {
    try {
      final data = jsonDecode(rawPayload) as Map<String, dynamic>;
      final targetUrl = data['url']?.toString() ??
          data['deep_link']?.toString() ??
          (data['order_id'] != null ? '/orders/${data['order_id']}' : null);

      if (targetUrl != null && onNotificationTapped != null) {
        onNotificationTapped!(targetUrl);
      }
    } catch (_) {}
  }
}
