import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'core/brand/brand_controller.dart';
import 'core/theme/app_theme.dart';
import 'core/i18n/app_locale_controller.dart';
import 'core/i18n/generated/app_localizations.dart';
import 'router/app_router.dart';

class MediTrackApp extends ConsumerWidget {
  const MediTrackApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(themeModeProvider);
    final locale = ref.watch(appLocaleProvider);
    final brand = ref.watch(brandControllerProvider);
    final router = ref.watch(appRouterProvider);

    return MaterialApp.router(
      title: brand.name,
      debugShowCheckedModeBanner: false,
      routerConfig: router,
      theme: AppTheme.light(dynamicPrimary: brand.primaryColor),
      darkTheme: AppTheme.dark(dynamicPrimary: brand.primaryColor),
      themeMode: themeMode,
      locale: locale,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
    );
  }
}

