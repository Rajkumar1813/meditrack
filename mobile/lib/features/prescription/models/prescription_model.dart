import 'package:flutter/foundation.dart';

@immutable
class PrescriptionModel {
  final int id;
  final String status;
  final String fileUrl;
  final String createdAt;
  final String? rejectionReason;
  final String? pharmacistName;
  final String? pharmacistRegNo;
  final String? validUntil;

  const PrescriptionModel({
    required this.id,
    required this.status,
    required this.fileUrl,
    required this.createdAt,
    this.rejectionReason,
    this.pharmacistName,
    this.pharmacistRegNo,
    this.validUntil,
  });

  bool get isPending => status.toLowerCase() == 'pending';
  bool get isApproved => status.toLowerCase() == 'approved';
  bool get isRejected => status.toLowerCase() == 'rejected';

  factory PrescriptionModel.fromJson(Map<String, dynamic> json) {
    return PrescriptionModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      status: (json['status'] ?? 'pending').toString(),
      fileUrl: (json['file_url'] ?? json['signed_url'] ?? '').toString(),
      createdAt: (json['created_at'] ?? '').toString(),
      rejectionReason: json['rejection_reason']?.toString(),
      pharmacistName: json['pharmacist_name']?.toString(),
      pharmacistRegNo: json['pharmacist_reg_no']?.toString(),
      validUntil: json['valid_until']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'status': status,
      'file_url': fileUrl,
      'created_at': createdAt,
      'rejection_reason': rejectionReason,
      'pharmacist_name': pharmacistName,
      'pharmacist_reg_no': pharmacistRegNo,
      'valid_until': validUntil,
    };
  }
}

