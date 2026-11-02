import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/brand/brand_tokens.dart';
import '../../core/brand/brand_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_locale_controller.dart';
import '../../shared/widgets/card_3d.dart';
import '../../shared/widgets/status_chip.dart';
import '../../shared/widgets/stat_card.dart';
import '../../shared/widgets/offline_banner.dart';
import '../auth/controllers/auth_controller.dart';

class OwnerScreen extends ConsumerWidget {
  const OwnerScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final brand = ref.watch(brandControllerProvider);
    final authState = ref.watch(authControllerProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final currentLocale = ref.watch(appLocaleProvider);

    final user = authState.user;

    return Scaffold(
      key: const Key('owner-home-screen'),
      appBar: AppBar(
        title: Text('${brand.name} OS'),
        actions: [
          // Language switcher button
          IconButton(
            key: const Key('owner-toggle-language-btn'),
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
            key: const Key('owner-toggle-theme-btn'),
            icon: Icon(isDark ? Icons.light_mode_rounded : Icons.dark_mode_rounded),
            tooltip: 'Theme',
            onPressed: () {
              ref.read(themeModeProvider.notifier).toggleTheme();
            },
          ),
          // Logout button
          IconButton(
            key: const Key('owner-logout-btn'),
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
                  // Pharmacy Owner / Pharmacist Header
                  Card3D(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Flexible(
                              child: Text(
                                user?.name ?? 'Pharmacist',
                                style: theme.textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                            StatusChip(
                              key: const Key('owner-role-badge'),
                              status: user?.role ?? 'pharmacist',
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          brand.adminTagline,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.textTheme.bodySmall?.color,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // 4 Primary KPI Cards
                  Text(
                    'Inventory & Store Overview',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: StatCard(
                          title: 'Total Medicines',
                          value: '1,248',
                          icon: Icons.inventory_2_rounded,
                          color: brand.primaryColor,
                          subtitle: 'Active catalog',
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: StatCard(
                          title: 'Units in Stock',
                          value: '14,290',
                          icon: Icons.layers_rounded,
                          color: BrandTokens.info,
                          subtitle: 'FEFO managed',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      const Expanded(
                        child: StatCard(
                          title: 'Low Stock',
                          value: '6 items',
                          icon: Icons.warning_amber_rounded,
                          color: BrandTokens.warning,
                          subtitle: 'Below threshold',
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: StatCard(
                          title: 'Expiring in 30d',
                          value: '3 batches',
                          icon: Icons.event_busy_rounded,
                          color: BrandTokens.danger,
                          subtitle: 'Urgent action',
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),

                  // Quick Navigation Controls
                  Text(
                    'Operations',
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  Card3D(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    onTap: () {},
                    child: const Row(
                      children: [
                        Icon(Icons.medication_rounded, color: Color(0xFF059669)),
                        SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Medicines & Batches', style: TextStyle(fontWeight: FontWeight.w700)),
                              Text('FEFO allocation, pricing & racks', style: TextStyle(fontSize: 12)),
                            ],
                          ),
                        ),
                        Icon(Icons.chevron_right_rounded),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Card3D(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    onTap: () {},
                    child: const Row(
                      children: [
                        Icon(Icons.local_shipping_rounded, color: Color(0xFF2563EB)),
                        SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Orders Pipeline', style: TextStyle(fontWeight: FontWeight.w700)),
                              Text('Pending, packing & dispatch', style: TextStyle(fontSize: 12)),
                            ],
                          ),
                        ),
                        Icon(Icons.chevron_right_rounded),
                      ],
                    ),
                  ),
                  const SizedBox(height: 10),
                  Card3D(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    onTap: () {},
                    child: const Row(
                      children: [
                        Icon(Icons.verified_user_rounded, color: Color(0xFFF59E0B)),
                        SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('Prescription Verification', style: TextStyle(fontWeight: FontWeight.w700)),
                              Text('Pharmacist stamp & approval', style: TextStyle(fontSize: 12)),
                            ],
                          ),
                        ),
                        Icon(Icons.chevron_right_rounded),
                      ],
                    ),
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

