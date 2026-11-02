import 'package:flutter/foundation.dart';

@immutable
class ShelfModel {
  final int id;
  final int rackId;
  final String name;
  final int capacity;

  const ShelfModel({
    required this.id,
    required this.rackId,
    required this.name,
    required this.capacity,
  });

  factory ShelfModel.fromJson(Map<String, dynamic> json) {
    return ShelfModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      rackId: json['rack_id'] is int ? json['rack_id'] as int : int.tryParse(json['rack_id']?.toString() ?? '0') ?? 0,
      name: (json['name'] ?? 'Shelf').toString(),
      capacity: (json['capacity'] as num?)?.toInt() ?? 50,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'rack_id': rackId,
      'name': name,
      'capacity': capacity,
    };
  }
}

@immutable
class RackModel {
  final int id;
  final String name;
  final String? zone;
  final List<ShelfModel> shelves;

  const RackModel({
    required this.id,
    required this.name,
    this.zone,
    this.shelves = const [],
  });

  factory RackModel.fromJson(Map<String, dynamic> json) {
    List<ShelfModel> parseShelves(dynamic val) {
      if (val is List) {
        return val.map((s) => ShelfModel.fromJson(s as Map<String, dynamic>)).toList();
      }
      return [];
    }

    return RackModel(
      id: json['id'] is int ? json['id'] as int : int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      name: (json['name'] ?? 'Rack').toString(),
      zone: json['zone']?.toString(),
      shelves: parseShelves(json['shelves']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'zone': zone,
      'shelves': shelves.map((s) => s.toJson()).toList(),
    };
  }
}

