import 'package:flutter/material.dart';
import '../brand/brand_tokens.dart';

class Shadows3D {
  static const List<BoxShadow> soft = [
    BoxShadow(
      color: Color(0x0F0F172A),
      blurRadius: 2,
      offset: Offset(0, 1),
    ),
    BoxShadow(
      color: Color(0x2E059669),
      blurRadius: 24,
      spreadRadius: -8,
      offset: Offset(0, 8),
    ),
  ];

  static const List<BoxShadow> lift = [
    BoxShadow(
      color: Color(0x1A000000),
      blurRadius: 25,
      spreadRadius: -5,
      offset: Offset(0, 20),
    ),
    BoxShadow(
      color: Color(0x1A000000),
      blurRadius: 10,
      spreadRadius: -6,
      offset: Offset(0, 8),
    ),
  ];

  static const List<BoxShadow> floating = [
    BoxShadow(
      color: Color(0x33059669),
      blurRadius: 30,
      spreadRadius: -4,
      offset: Offset(0, 16),
    ),
  ];

  static List<BoxShadow> softDark = [
    const BoxShadow(
      color: Color(0x40000000),
      blurRadius: 8,
      offset: Offset(0, 4),
    ),
    BoxShadow(
      color: (BrandTokens.primary[500] ?? const Color(0xFF10B981)).withValues(alpha: 0.15),
      blurRadius: 24,
      spreadRadius: -8,
      offset: const Offset(0, 8),
    ),
  ];
}

