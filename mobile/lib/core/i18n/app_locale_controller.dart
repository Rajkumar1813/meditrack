import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../storage/pref_storage.dart';

final appLocaleProvider = StateNotifierProvider<AppLocaleNotifier, Locale>((ref) {
  final prefs = ref.watch(prefStorageProvider);
  return AppLocaleNotifier(prefs);
});

class AppLocaleNotifier extends StateNotifier<Locale> {
  final PrefStorage _prefs;

  AppLocaleNotifier(this._prefs) : super(Locale(_prefs.getLocale()));

  Future<void> setLocale(String languageCode) async {
    final code = languageCode == 'hi' ? 'hi' : 'en';
    state = Locale(code);
    await _prefs.setLocale(code);
  }

  void toggleLocale() {
    if (state.languageCode == 'en') {
      setLocale('hi');
    } else {
      setLocale('en');
    }
  }
}

