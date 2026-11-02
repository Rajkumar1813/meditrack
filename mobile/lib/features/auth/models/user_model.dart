import 'package:flutter/foundation.dart';

@immutable
class UserModel {
  final int id;
  final String name;
  final String? email;
  final String? phone;
  final String role;
  final bool isBlocked;

  const UserModel({
    required this.id,
    required this.name,
    this.email,
    this.phone,
    required this.role,
    this.isBlocked = false,
  });

  bool get isCustomer => role.toLowerCase() == 'customer';
  bool get isAdmin => role.toLowerCase() == 'admin';
  bool get isPharmacist => role.toLowerCase() == 'pharmacist';
  bool get isStaff => role.toLowerCase() == 'staff';
  bool get isOwnerOrStaff => isAdmin || isPharmacist || isStaff;

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: (json['name'] ?? 'User').toString(),
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      role: (json['role'] ?? 'customer').toString(),
      isBlocked: json['is_blocked'] == true || json['is_blocked'] == 1,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'role': role,
      'is_blocked': isBlocked,
    };
  }

  UserModel copyWith({
    int? id,
    String? name,
    String? email,
    String? phone,
    String? role,
    bool? isBlocked,
  }) {
    return UserModel(
      id: id ?? this.id,
      name: name ?? this.name,
      email: email ?? this.email,
      phone: phone ?? this.phone,
      role: role ?? this.role,
      isBlocked: isBlocked ?? this.isBlocked,
    );
  }
}

