import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import '../brand/brand_tokens.dart';
import '../storage/pref_storage.dart';

final themeModeProvider = StateNotifierProvider<ThemeModeNotifier, ThemeMode>((ref) {
  final prefs = ref.watch(prefStorageProvider);
  return ThemeModeNotifier(prefs);
});

class ThemeModeNotifier extends StateNotifier<ThemeMode> {
  final PrefStorage _prefs;

  ThemeModeNotifier(this._prefs) : super(_loadInitialTheme(_prefs));

  static ThemeMode _loadInitialTheme(PrefStorage prefs) {
    final mode = prefs.getThemeMode();
    switch (mode) {
      case 'light':
        return ThemeMode.light;
      case 'dark':
        return ThemeMode.dark;
      default:
        return ThemeMode.system;
    }
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    state = mode;
    final str = mode == ThemeMode.light
        ? 'light'
        : mode == ThemeMode.dark
            ? 'dark'
            : 'system';
    await _prefs.setThemeMode(str);
  }

  void toggleTheme() {
    if (state == ThemeMode.dark) {
      setThemeMode(ThemeMode.light);
    } else {
      setThemeMode(ThemeMode.dark);
    }
  }
}

class AppTheme {
  static ThemeData light({Color? dynamicPrimary}) {
    final primary = dynamicPrimary ?? BrandTokens.primary[600] ?? const Color(0xFF059669);

    final base = ThemeData.light();
    final textTheme = GoogleFonts.interTextTheme(base.textTheme);

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      primaryColor: primary,
      scaffoldBackgroundColor: BrandTokens.lightBackground,
      colorScheme: ColorScheme.light(
        primary: primary,
        secondary: BrandTokens.accent,
        surface: BrandTokens.lightSurface,
        error: BrandTokens.danger,
        onPrimary: Colors.white,
        onSurface: BrandTokens.lightText,
      ),
      textTheme: textTheme.apply(
        bodyColor: BrandTokens.lightText,
        displayColor: BrandTokens.lightText,
      ),
      cardTheme: CardThemeData(
        color: BrandTokens.lightSurface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusCard),
          side: const BorderSide(color: BrandTokens.lightBorder, width: 1),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: BrandTokens.lightSurface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: const BorderSide(color: BrandTokens.lightBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: const BorderSide(color: BrandTokens.lightBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: BorderSide(color: primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: const BorderSide(color: BrandTokens.danger),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(BrandTokens.radiusButton),
          ),
          textStyle: GoogleFonts.inter(
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          side: BorderSide(color: primary),
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(BrandTokens.radiusButton),
          ),
          textStyle: GoogleFonts.inter(
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
        ),
      ),
    );
  }

  static ThemeData dark({Color? dynamicPrimary}) {
    final primary = dynamicPrimary ?? BrandTokens.primary[500] ?? const Color(0xFF10B981);

    final base = ThemeData.dark();
    final textTheme = GoogleFonts.interTextTheme(base.textTheme);

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      primaryColor: primary,
      scaffoldBackgroundColor: BrandTokens.darkBackground,
      colorScheme: ColorScheme.dark(
        primary: primary,
        secondary: BrandTokens.accent,
        surface: BrandTokens.darkSurface,
        error: BrandTokens.danger,
        onPrimary: Colors.black,
        onSurface: BrandTokens.darkText,
      ),
      textTheme: textTheme.apply(
        bodyColor: BrandTokens.darkText,
        displayColor: BrandTokens.darkText,
      ),
      cardTheme: CardThemeData(
        color: BrandTokens.darkSurface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusCard),
          side: const BorderSide(color: BrandTokens.darkBorder, width: 1),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: BrandTokens.darkSurface,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: const BorderSide(color: BrandTokens.darkBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: const BorderSide(color: BrandTokens.darkBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: BorderSide(color: primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
          borderSide: const BorderSide(color: BrandTokens.danger),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.black,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(BrandTokens.radiusButton),
          ),
          textStyle: GoogleFonts.inter(
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          side: BorderSide(color: primary),
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(BrandTokens.radiusButton),
          ),
          textStyle: GoogleFonts.inter(
            fontWeight: FontWeight.w600,
            fontSize: 16,
          ),
        ),
      ),
    );
  }
}

