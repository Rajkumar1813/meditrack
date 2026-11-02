import 'package:flutter/foundation.dart';

@immutable
class BatchModel {
  final int id;
  final int medicineId;
  final String batchNumber;
  final String mfgDate;
  final String expiryDate;
  final int quantity;
  final double purchasePrice;
  final double mrp;
  final int daysUntilExpiry;
  final String? rackName;
  final String? shelfName;

  const BatchModel({
    required this.id,
    required this.medicineId,
    required this.batchNumber,
    required this.mfgDate,
    required this.expiryDate,
    required this.quantity,
    required this.purchasePrice,
    required this.mrp,
    this.daysUntilExpiry = 365,
    this.rackName,
    this.shelfName,
  });

  bool get isExpired => daysUntilExpiry <= 0;
  bool get isExpiringSoon => daysUntilExpiry > 0 && daysUntilExpiry <= 90;

  factory BatchModel.fromJson(Map<String, dynamic> json) {
    return BatchModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      medicineId: json['medicine_id'] is int ? json['medicine_id'] as int : int.tryParse(json['medicine_id']?.toString() ?? '0') ?? 0,
      batchNumber: (json['batch_number'] ?? '').toString(),
      mfgDate: (json['mfg_date'] ?? '').toString(),
      expiryDate: (json['expiry_date'] ?? '').toString(),
      quantity: json['quantity'] is int ? json['quantity'] as int : int.tryParse(json['quantity']?.toString() ?? '0') ?? 0,
      purchasePrice: (json['purchase_price'] as num?)?.toDouble() ?? 0.0,
      mrp: (json['mrp'] as num?)?.toDouble() ?? 0.0,
      daysUntilExpiry: (json['days_until_expiry'] as num?)?.toInt() ?? 365,
      rackName: json['rack_name']?.toString(),
      shelfName: json['shelf_name']?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'medicine_id': medicineId,
      'batch_number': batchNumber,
      'mfg_date': mfgDate,
      'expiry_date': expiryDate,
      'quantity': quantity,
      'purchase_price': purchasePrice,
      'mrp': mrp,
      'days_until_expiry': daysUntilExpiry,
      'rack_name': rackName,
      'shelf_name': shelfName,
    };
  }
}

@immutable
class MedicineModel {
  final int id;
  final String name;
  final String slug;
  final String category;
  final bool requiresPrescription;
  final double price;
  final double mrp;
  final List<String> salts;
  final String manufacturer;
  final int stock;
  final List<BatchModel> batches;

  const MedicineModel({
    required this.id,
    required this.name,
    required this.slug,
    required this.category,
    required this.requiresPrescription,
    required this.price,
    required this.mrp,
    required this.salts,
    required this.manufacturer,
    required this.stock,
    this.batches = const [],
  });

  bool get inStock => stock > 0;

  factory MedicineModel.fromJson(Map<String, dynamic> json) {
    List<String> parseSalts(dynamic val) {
      if (val is List) return val.map((e) => e.toString()).toList();
      if (val is String && val.isNotEmpty) {
        return val.split(',').map((s) => s.trim()).toList();
      }
      return [];
    }

    List<BatchModel> parseBatches(dynamic val) {
      if (val is List) {
        return val.map((b) => BatchModel.fromJson(b as Map<String, dynamic>)).toList();
      }
      return [];
    }

    return MedicineModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: (json['name'] ?? 'Medicine').toString(),
      slug: (json['slug'] ?? '').toString(),
      category: (json['category'] ?? 'General').toString(),
      requiresPrescription: json['requires_prescription'] == true || json['requires_prescription'] == 1,
      price: (json['price'] as num?)?.toDouble() ?? 0.0,
      mrp: (json['mrp'] as num?)?.toDouble() ?? (json['price'] as num?)?.toDouble() ?? 0.0,
      salts: parseSalts(json['salts']),
      manufacturer: (json['company'] ?? json['manufacturer'] ?? 'Standard Pharma').toString(),
      stock: json['stock'] is int ? json['stock'] as int : int.tryParse(json['stock']?.toString() ?? '0') ?? 0,
      batches: parseBatches(json['batches']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'slug': slug,
      'category': category,
      'requires_prescription': requiresPrescription,
      'price': price,
      'mrp': mrp,
      'salts': salts,
      'company': manufacturer,
      'stock': stock,
      'batches': batches.map((b) => b.toJson()).toList(),
    };
  }
}

