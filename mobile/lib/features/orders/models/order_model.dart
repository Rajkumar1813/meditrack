import 'package:flutter/foundation.dart';
import '../../profile/models/address_model.dart';

@immutable
class OrderItemModel {
  final int medicineId;
  final String medicineName;
  final int quantity;
  final double unitPrice;
  final double totalPrice;
  final String? batchNumber;
  final String? expiryDate;

  const OrderItemModel({
    required this.medicineId,
    required this.medicineName,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    this.batchNumber,
    this.expiryDate,
  });

  factory OrderItemModel.fromJson(Map<String, dynamic> json) {
    return OrderItemModel(
      medicineId: json['medicine_id'] is int ? json['medicine_id'] as int : int.tryParse(json['medicine_id']?.toString() ?? '0') ?? 0,
      medicineName: (json['medicine_name'] ?? json['name'] ?? 'Medicine').toString(),
      quantity: json['quantity'] is int ? json['quantity'] as int : int.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
      unitPrice: (json['unit_price'] as num?)?.toDouble() ?? (json['price'] as num?)?.toDouble() ?? 0.0,
      totalPrice: (json['total_price'] as num?)?.toDouble() ?? (json['total'] as num?)?.toDouble() ?? 0.0,
      batchNumber: json['batch_number']?.toString(),
      expiryDate: json['expiry_date']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'medicine_id': medicineId,
      'medicine_name': medicineName,
      'quantity': quantity,
      'unit_price': unitPrice,
      'total_price': totalPrice,
      'batch_number': batchNumber,
      'expiry_date': expiryDate,
    };
  }
}

@immutable
class OrderModel {
  final int id;
  final String orderNumber;
  final String status;
  final String paymentMethod;
  final String paymentStatus;
  final double total;
  final double subtotal;
  final double gstAmount;
  final double deliveryFee;
  final String createdAt;
  final List<OrderItemModel> items;
  final AddressModel? address;
  final String? courier;
  final String? trackingNumber;
  final String? invoiceUrl;
  final bool rxRequired;
  final String? rxStatus;

  const OrderModel({
    required this.id,
    required this.orderNumber,
    required this.status,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.total,
    required this.subtotal,
    required this.gstAmount,
    required this.deliveryFee,
    required this.createdAt,
    this.items = const [],
    this.address,
    this.courier,
    this.trackingNumber,
    this.invoiceUrl,
    this.rxRequired = false,
    this.rxStatus,
  });

  bool get isCancellable =>
      status.toLowerCase() == 'pending' ||
      status.toLowerCase() == 'confirmed' ||
      status.toLowerCase() == 'packed';

  String get customerName => address?.name ?? 'Customer';
  String get formattedDate => createdAt.length >= 10 ? createdAt.substring(0, 10) : createdAt;
  String? get courierName => courier;

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    List<OrderItemModel> parseItems(dynamic val) {
      if (val is List) {
        return val.map((i) => OrderItemModel.fromJson(i as Map<String, dynamic>)).toList();
      }
      return [];
    }

    return OrderModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      orderNumber: (json['order_number'] ?? 'ORD-${json['id']}').toString(),
      status: (json['status'] ?? 'pending').toString(),
      paymentMethod: (json['payment_method'] ?? 'cod').toString(),
      paymentStatus: (json['payment_status'] ?? 'pending').toString(),
      total: (json['total'] as num?)?.toDouble() ?? 0.0,
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0.0,
      gstAmount: (json['gst_amount'] as num?)?.toDouble() ?? 0.0,
      deliveryFee: (json['delivery_fee'] as num?)?.toDouble() ?? 0.0,
      createdAt: (json['created_at'] ?? '').toString(),
      items: parseItems(json['items']),
      address: json['address'] is Map<String, dynamic>
          ? AddressModel.fromJson(json['address'] as Map<String, dynamic>)
          : null,
      courier: json['courier']?.toString(),
      trackingNumber: json['tracking_no']?.toString() ?? json['tracking_number']?.toString(),
      invoiceUrl: json['invoice_url']?.toString(),
      rxRequired: json['rx_required'] == true || json['rx_required'] == 1,
      rxStatus: json['rx_status']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'order_number': orderNumber,
      'status': status,
      'payment_method': paymentMethod,
      'payment_status': paymentStatus,
      'total': total,
      'subtotal': subtotal,
      'gst_amount': gstAmount,
      'delivery_fee': deliveryFee,
      'created_at': createdAt,
      'items': items.map((i) => i.toJson()).toList(),
      'address': address?.toJson(),
      'courier': courier,
      'tracking_no': trackingNumber,
      'invoice_url': invoiceUrl,
      'rx_required': rxRequired,
      'rx_status': rxStatus,
    };
  }
}
