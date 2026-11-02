import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/storage/pref_storage.dart';
import 'core/push/fcm_service.dart';
import 'app.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize SharedPreferences early for theme, locale and brand cache
  final sharedPreferences = await SharedPreferences.getInstance();

  final container = ProviderContainer(
    overrides: [
      sharedPreferencesProvider.overrideWithValue(sharedPreferences),
    ],
  );

  // Initialize FCM and notification channels safely
  final fcmService = container.read(fcmServiceProvider);
  fcmService.initialize();

  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const MediTrackApp(),
    ),
  );
}
