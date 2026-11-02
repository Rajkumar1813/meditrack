import 'package:flutter/foundation.dart';

@immutable
class NotificationModel {
  final int id;
  final String title;
  final String message;
  final String? deepLink;
  final bool isRead;
  final String createdAt;

  const NotificationModel({
    required this.id,
    required this.title,
    required this.message,
    this.deepLink,
    this.isRead = false,
    required this.createdAt,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) {
    return NotificationModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      title: (json['title'] ?? 'MediTrack Notification').toString(),
      message: (json['message'] ?? '').toString(),
      deepLink: json['deep_link']?.toString() ?? json['url']?.toString(),
      isRead: json['is_read'] == true || json['is_read'] == 1,
      createdAt: (json['created_at'] ?? '').toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'title': title,
      'message': message,
      'deep_link': deepLink,
      'is_read': isRead,
      'created_at': createdAt,
    };
  }
}

