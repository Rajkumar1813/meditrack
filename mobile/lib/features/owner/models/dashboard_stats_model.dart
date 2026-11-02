import 'package:flutter/foundation.dart';

@immutable
class ExpiryDistribution {
  final int safe;
  final int expiring90d;
  final int expiring30d;
  final int expired;

  const ExpiryDistribution({
    required this.safe,
    required this.expiring90d,
    required this.expiring30d,
    required this.expired,
  });

  factory ExpiryDistribution.fromJson(Map<String, dynamic> json) {
    return ExpiryDistribution(
      safe: (json['safe'] as num?)?.toInt() ?? 0,
      expiring90d: (json['expiring_90d'] as num?)?.toInt() ?? 0,
      expiring30d: (json['expiring_30d'] as num?)?.toInt() ?? 0,
      expired: (json['expired'] as num?)?.toInt() ?? 0,
    );
  }
}

@immutable
class CategoryDistribution {
  final String category;
  final int count;

  const CategoryDistribution({required this.category, required this.count});

  factory CategoryDistribution.fromJson(Map<String, dynamic> json) {
    return CategoryDistribution(
      category: (json['category'] ?? 'General').toString(),
      count: (json['count'] as num?)?.toInt() ?? 0,
    );
  }
}

@immutable
class DashboardStatsModel {
  final int totalMedicines;
  final int totalUnits;
  final int lowStockCount;
  final int expiring30Count;
  final int expiredCount;
  final double totalInventoryValue;
  final int ordersToday;
  final double revenueToday;
  final List<CategoryDistribution> categoryDistribution;
  final ExpiryDistribution expiryDistribution;

  const DashboardStatsModel({
    required this.totalMedicines,
    required this.totalUnits,
    required this.lowStockCount,
    required this.expiring30Count,
    required this.expiredCount,
    required this.totalInventoryValue,
    required this.ordersToday,
    required this.revenueToday,
    this.categoryDistribution = const [],
    this.expiryDistribution = const ExpiryDistribution(safe: 0, expiring90d: 0, expiring30d: 0, expired: 0),
  });

  factory DashboardStatsModel.fromJson(Map<String, dynamic> json) {
    List<CategoryDistribution> parseCat(dynamic val) {
      if (val is List) {
        return val.map((c) => CategoryDistribution.fromJson(c as Map<String, dynamic>)).toList();
      }
      return [];
    }

    return DashboardStatsModel(
      totalMedicines: (json['total_medicines'] as num?)?.toInt() ?? 0,
      totalUnits: (json['total_units'] as num?)?.toInt() ?? 0,
      lowStockCount: (json['low_stock_count'] as num?)?.toInt() ?? 0,
      expiring30Count: (json['expiring_30_count'] as num?)?.toInt() ?? 0,
      expiredCount: (json['expired_count'] as num?)?.toInt() ?? 0,
      totalInventoryValue: (json['total_inventory_value'] as num?)?.toDouble() ?? 0.0,
      ordersToday: (json['orders_today'] as num?)?.toInt() ?? 0,
      revenueToday: (json['revenue_today'] as num?)?.toDouble() ?? 0.0,
      categoryDistribution: parseCat(json['category_distribution']),
      expiryDistribution: json['expiry_distribution'] is Map<String, dynamic>
          ? ExpiryDistribution.fromJson(json['expiry_distribution'] as Map<String, dynamic>)
          : const ExpiryDistribution(safe: 0, expiring90d: 0, expiring30d: 0, expired: 0),
    );
  }
}

