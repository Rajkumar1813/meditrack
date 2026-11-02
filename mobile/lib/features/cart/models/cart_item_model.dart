import 'package:flutter/foundation.dart';

@immutable
class CartItemModel {
  final int medicineId;
  final String medicineName;
  final int quantity;
  final double price;
  final double mrp;
  final bool requiresPrescription;
  final int stockAvailable;

  const CartItemModel({
    required this.medicineId,
    required this.medicineName,
    required this.quantity,
    required this.price,
    required this.mrp,
    required this.requiresPrescription,
    required this.stockAvailable,
  });

  double get totalPrice => price * quantity;
  double get totalMrp => mrp * quantity;
  double get totalSavings => (mrp > price ? (mrp - price) * quantity : 0.0);

  CartItemModel copyWith({
    int? quantity,
  }) {
    return CartItemModel(
      medicineId: medicineId,
      medicineName: medicineName,
      quantity: quantity ?? this.quantity,
      price: price,
      mrp: mrp,
      requiresPrescription: requiresPrescription,
      stockAvailable: stockAvailable,
    );
  }

  factory CartItemModel.fromJson(Map<String, dynamic> json) {
    return CartItemModel(
      medicineId: json['medicine_id'] is int ? json['medicine_id'] as int : int.tryParse(json['medicine_id']?.toString() ?? '0') ?? 0,
      medicineName: (json['medicine_name'] ?? json['name'] ?? 'Medicine').toString(),
      quantity: json['quantity'] is int ? json['quantity'] as int : int.tryParse(json['quantity']?.toString() ?? '1') ?? 1,
      price: (json['price'] as num?)?.toDouble() ?? 0.0,
      mrp: (json['mrp'] as num?)?.toDouble() ?? 0.0,
      requiresPrescription: json['requires_prescription'] == true || json['requires_prescription'] == 1,
      stockAvailable: json['stock_available'] is int ? json['stock_available'] as int : int.tryParse(json['stock_available']?.toString() ?? '99') ?? 99,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'medicine_id': medicineId,
      'medicine_name': medicineName,
      'quantity': quantity,
      'price': price,
      'mrp': mrp,
      'requires_prescription': requiresPrescription,
      'stock_available': stockAvailable,
    };
  }
}
