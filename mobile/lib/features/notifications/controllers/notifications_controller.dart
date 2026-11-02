import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/notification_model.dart';
import '../../../core/network/api_client.dart';

@immutable
class NotificationsState {
  final List<NotificationModel> notifications;
  final bool isLoading;
  final String? error;

  const NotificationsState({
    this.notifications = const [],
    this.isLoading = false,
    this.error,
  });

  int get unreadCount => notifications.where((n) => !n.isRead).length;

  NotificationsState copyWith({
    List<NotificationModel>? notifications,
    bool? isLoading,
    String? error,
    bool clearError = false,
  }) {
    return NotificationsState(
      notifications: notifications ?? this.notifications,
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

final notificationsControllerProvider = StateNotifierProvider<NotificationsController, NotificationsState>((ref) {
  final api = ref.watch(apiClientProvider);
  return NotificationsController(api);
});

class NotificationsController extends StateNotifier<NotificationsState> {
  final ApiClient _api;

  NotificationsController(this._api) : super(const NotificationsState()) {
    fetchNotifications();
  }

  Future<void> fetchNotifications() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final res = await _api.get('/notifications');

    res.when(
      success: (data) {
        List<NotificationModel> list = [];
        if (data is Map<String, dynamic> && data['items'] is List) {
          list = (data['items'] as List)
              .map((n) => NotificationModel.fromJson(n as Map<String, dynamic>))
              .toList();
        } else if (data is List) {
          list = data.map((n) => NotificationModel.fromJson(n as Map<String, dynamic>)).toList();
        }
        state = state.copyWith(notifications: list, isLoading: false);
      },
      failure: (err) {
        state = state.copyWith(isLoading: false, error: err.message);
      },
    );
  }

  Future<void> markAsRead(int notificationId) async {
    final updated = state.notifications.map((n) {
      if (n.id == notificationId) {
        return NotificationModel(
          id: n.id,
          title: n.title,
          message: n.message,
          deepLink: n.deepLink,
          isRead: true,
          createdAt: n.createdAt,
        );
      }
      return n;
    }).toList();

    state = state.copyWith(notifications: updated);

    await _api.post('/notifications/read', data: {
      'notification_id': notificationId,
    });
  }
}

