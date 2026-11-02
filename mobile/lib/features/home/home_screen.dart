import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/brand/brand_tokens.dart';
import '../../core/brand/brand_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_locale_controller.dart';
import '../../shared/widgets/card_3d.dart';
import '../../shared/widgets/status_chip.dart';
import '../../shared/widgets/offline_banner.dart';
import '../auth/controllers/auth_controller.dart';

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final brand = ref.watch(brandControllerProvider);
    final authState = ref.watch(authControllerProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final currentLocale = ref.watch(appLocaleProvider);

    final user = authState.user;

    return Scaffold(
      key: const Key('customer-home-screen'),
      appBar: AppBar(
        title: Text(brand.name),
        actions: [
          // Language switcher button
          IconButton(
            key: const Key('toggle-language-btn'),
            icon: Text(
              currentLocale.languageCode.toUpperCase(),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            tooltip: 'Language',
            onPressed: () {
              ref.read(appLocaleProvider.notifier).toggleLocale();
            },
          ),
          // Theme switcher button
          IconButton(
            key: const Key('toggle-theme-btn'),
            icon: Icon(isDark ? Icons.light_mode_rounded : Icons.dark_mode_rounded),
            tooltip: 'Theme',
            onPressed: () {
              ref.read(themeModeProvider.notifier).toggleTheme();
            },
          ),
          // Logout button
          IconButton(
            key: const Key('customer-logout-btn'),
            icon: const Icon(Icons.logout_rounded),
            tooltip: 'Logout',
            onPressed: () {
              ref.read(authControllerProvider.notifier).logout();
            },
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            const OfflineBanner(),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: [
                  // Greeting & Role Badge
                  Card3D(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Hello, ${user?.name ?? "Customer"}',
                              style: theme.textTheme.titleLarge?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            StatusChip(
                              key: const Key('customer-role-badge'),
                              status: user?.role ?? 'customer',
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          brand.tagline,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.textTheme.bodySmall?.color,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Quick Action Cards
                  Text(
                    'Quick Services',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: Card3D(
                          padding: const EdgeInsets.all(16),
                          onTap: () {},
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: brand.primaryColor.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Icon(Icons.medication_liquid_rounded, color: brand.primaryColor),
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'Order Medicines',
                                style: TextStyle(fontWeight: FontWeight.w700),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Explore catalog',
                                style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Card3D(
                          padding: const EdgeInsets.all(16),
                          onTap: () {},
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: BrandTokens.accent.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.document_scanner_rounded, color: BrandTokens.accent),
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'Upload Rx',
                                style: TextStyle(fontWeight: FontWeight.w700),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Doctor prescription',
                                style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: Card3D(
                          padding: const EdgeInsets.all(16),
                          onTap: () {},
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: BrandTokens.info.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.receipt_long_rounded, color: BrandTokens.info),
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'My Orders',
                                style: TextStyle(fontWeight: FontWeight.w700),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Track fulfillment',
                                style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Card3D(
                          padding: const EdgeInsets.all(16),
                          onTap: () {},
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                padding: const EdgeInsets.all(10),
                                decoration: BoxDecoration(
                                  color: const Color(0xFF8B5CF6).withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.replay_rounded, color: Color(0xFF8B5CF6)),
                              ),
                              const SizedBox(height: 12),
                              const Text(
                                'Refill Reminders',
                                style: TextStyle(fontWeight: FontWeight.w700),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Automated reminders',
                                style: theme.textTheme.bodySmall?.copyWith(fontSize: 11),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

