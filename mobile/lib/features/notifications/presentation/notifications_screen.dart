import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../shared/widgets/card_3d.dart';
import '../controllers/notifications_controller.dart';

class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifState = ref.watch(notificationsControllerProvider);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.read(notificationsControllerProvider.notifier).fetchNotifications(),
        child: notifState.notifications.isEmpty
            ? (notifState.isLoading
                ? const Center(child: CircularProgressIndicator.adaptive())
                : const Center(child: Text('No notifications yet.')))
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: notifState.notifications.length,
                separatorBuilder: (context, index) => const SizedBox(height: 10),
                itemBuilder: (context, i) {
                  final notif = notifState.notifications[i];
                  return Card3D(
                    padding: const EdgeInsets.all(14),
                    onTap: () {
                      ref.read(notificationsControllerProvider.notifier).markAsRead(notif.id);
                      if (notif.deepLink != null && notif.deepLink!.isNotEmpty) {
                        context.push(notif.deepLink!);
                      }
                    },
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: notif.isRead ? Colors.grey.withValues(alpha: 0.1) : const Color(0xFFD1FAE5),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            Icons.notifications_outlined,
                            size: 20,
                            color: notif.isRead ? Colors.grey : const Color(0xFF047857),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                notif.title,
                                style: TextStyle(
                                  fontWeight: notif.isRead ? FontWeight.w600 : FontWeight.w800,
                                  fontSize: 14,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(notif.message, style: theme.textTheme.bodySmall),
                              const SizedBox(height: 6),
                              Text(
                                notif.createdAt,
                                style: theme.textTheme.bodySmall?.copyWith(fontSize: 10, color: Colors.grey),
                              ),
                            ],
                          ),
                        ),
                        if (!notif.isRead)
                          Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Color(0xFF059669),
                              shape: BoxShape.circle,
                            ),
                          ),
                      ],
                    ),
                  );
                },
              ),
      ),
    );
  }
}
