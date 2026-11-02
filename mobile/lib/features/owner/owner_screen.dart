import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/brand/brand_tokens.dart';
import '../../core/brand/brand_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_locale_controller.dart';
import '../../shared/widgets/card_3d.dart';
import '../../shared/widgets/status_chip.dart';
import '../../shared/widgets/stat_card.dart';
import '../../shared/widgets/offline_banner.dart';
import '../auth/controllers/auth_controller.dart';
import 'controllers/owner_controller.dart';
import 'models/dashboard_stats_model.dart';

class OwnerScreen extends ConsumerWidget {
  const OwnerScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final brand = ref.watch(brandControllerProvider);
    final authState = ref.watch(authControllerProvider);
    final ownerState = ref.watch(ownerControllerProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final currentLocale = ref.watch(appLocaleProvider);

    final user = authState.user;
    final stats = ownerState.stats;

    final totalMeds = stats?.totalMedicines ?? 1248;
    final totalUnits = stats?.totalUnits ?? 14290;
    final lowStock = stats?.lowStockCount ?? 6;
    final expiring30 = stats?.expiring30Count ?? 3;
    final ordersToday = stats?.ordersToday ?? 14;
    final revenueToday = stats?.revenueToday ?? 12450.0;

    return Scaffold(
      key: const Key('owner-home-screen'),
      appBar: AppBar(
        title: Text('${brand.name} OS'),
        actions: [
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
          IconButton(
            key: const Key('owner-toggle-theme-btn'),
            icon: Icon(isDark ? Icons.light_mode_rounded : Icons.dark_mode_rounded),
            tooltip: 'Theme',
            onPressed: () {
              ref.read(themeModeProvider.notifier).toggleTheme();
            },
          ),
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
              child: RefreshIndicator(
                onRefresh: () => ref.read(ownerControllerProvider.notifier).fetchStats(),
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

                    // Primary KPI Cards
                    Text(
                      'Store & Inventory Overview',
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: StatCard(
                            title: 'Total Medicines',
                            value: '$totalMeds',
                            icon: Icons.inventory_2_rounded,
                            color: brand.primaryColor,
                            subtitle: 'Active catalog',
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: StatCard(
                            title: 'Units in Stock',
                            value: '$totalUnits',
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
                        Expanded(
                          child: StatCard(
                            title: 'Low Stock',
                            value: '$lowStock items',
                            icon: Icons.warning_amber_rounded,
                            color: BrandTokens.warning,
                            subtitle: 'Below threshold',
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: StatCard(
                            title: 'Expiring in 30d',
                            value: '$expiring30 batches',
                            icon: Icons.event_busy_rounded,
                            color: BrandTokens.danger,
                            subtitle: 'Urgent action',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: StatCard(
                            title: 'Orders Today',
                            value: '$ordersToday',
                            icon: Icons.shopping_bag_outlined,
                            color: const Color(0xFF8B5CF6),
                            subtitle: 'Online + In-store',
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: StatCard(
                            title: 'Revenue Today',
                            value: '₹${revenueToday.toStringAsFixed(0)}',
                            icon: Icons.currency_rupee_rounded,
                            color: const Color(0xFF059669),
                            subtitle: 'GST inclusive',
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 24),

                    // Visual Analytics: Category Donut & Expiry Distribution
                    Text(
                      'Analytics & Breakdown',
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 12),
                    _CategoryDonutCard(
                      categories: stats?.categoryDistribution ?? const [
                        CategoryDistribution(category: 'Tablets', count: 450),
                        CategoryDistribution(category: 'Syrups', count: 280),
                        CategoryDistribution(category: 'Capsules', count: 210),
                        CategoryDistribution(category: 'Injections', count: 120),
                        CategoryDistribution(category: 'Topicals', count: 90),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _ExpiryDistributionCard(
                      distribution: stats?.expiryDistribution ??
                          const ExpiryDistribution(safe: 1100, expiring90d: 95, expiring30d: 38, expired: 15),
                    ),
                    const SizedBox(height: 24),

                    // Quick Operations Menu
                    Text(
                      'Operations',
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 12),
                    _buildOperationTile(
                      key: 'owner-op-inventory',
                      icon: Icons.medication_rounded,
                      color: const Color(0xFF059669),
                      title: 'Medicines & Batches',
                      subtitle: 'Search, FEFO allocation, pricing & racks',
                      onTap: () => context.push('/owner/inventory'),
                    ),
                    const SizedBox(height: 10),
                    _buildOperationTile(
                      key: 'owner-op-expiring',
                      icon: Icons.timer_outlined,
                      color: const Color(0xFFEA580C),
                      title: 'Expiring & Expired Batches',
                      subtitle: '30d / 90d window, discounts & dead-stock dispose',
                      onTap: () => context.push('/owner/expiring'),
                    ),
                    const SizedBox(height: 10),
                    _buildOperationTile(
                      key: 'owner-op-racks',
                      icon: Icons.grid_view_rounded,
                      color: const Color(0xFF0D9488),
                      title: 'Racks & Shelves',
                      subtitle: 'Physical storage locations & mapping',
                      onTap: () => context.push('/owner/racks'),
                    ),
                    const SizedBox(height: 10),
                    _buildOperationTile(
                      key: 'owner-op-orders',
                      icon: Icons.local_shipping_rounded,
                      color: const Color(0xFF2563EB),
                      title: 'Orders Pipeline',
                      subtitle: 'Pending, packing, courier tracking & dispatch',
                      onTap: () => context.push('/owner/orders'),
                    ),
                    const SizedBox(height: 10),
                    _buildOperationTile(
                      key: 'owner-op-rx',
                      icon: Icons.verified_user_rounded,
                      color: const Color(0xFFF59E0B),
                      title: 'Prescription Verification',
                      subtitle: 'Pharmacist stamp, zoom inspector & approval',
                      onTap: () => context.push('/owner/rx-review'),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOperationTile({
    required String key,
    required IconData icon,
    required Color color,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Card3D(
      key: Key(key),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      onTap: onTap,
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                const SizedBox(height: 2),
                Text(subtitle, style: const TextStyle(fontSize: 12)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, size: 20),
        ],
      ),
    );
  }
}

class _CategoryDonutCard extends StatelessWidget {
  final List<CategoryDistribution> categories;

  const _CategoryDonutCard({required this.categories});

  static const List<Color> _palette = [
    Color(0xFF059669),
    Color(0xFF2563EB),
    Color(0xFFF59E0B),
    Color(0xFF8B5CF6),
    Color(0xFF0D9488),
    Color(0xFFF43F5E),
  ];

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final total = categories.fold<int>(0, (sum, c) => sum + c.count);

    return Card3D(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Medicines by Category', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
              Text('$total total', style: TextStyle(fontSize: 12, color: theme.hintColor)),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              // Custom Donut Canvas
              SizedBox(
                width: 110,
                height: 110,
                child: CustomPaint(
                  painter: _DonutChartPainter(
                    categories: categories,
                    colors: _palette,
                    total: total,
                  ),
                ),
              ),
              const SizedBox(width: 20),
              // Legend
              Expanded(
                child: Column(
                  children: List.generate(math.min(categories.length, 5), (i) {
                    final cat = categories[i];
                    final color = _palette[i % _palette.length];
                    final pct = total > 0 ? (cat.count / total * 100).toStringAsFixed(0) : '0';

                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(
                        children: [
                          Container(
                            width: 10,
                            height: 10,
                            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              cat.category,
                              style: const TextStyle(fontSize: 12),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                          Text(
                            '${cat.count} ($pct%)',
                            style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: theme.hintColor),
                          ),
                        ],
                      ),
                    );
                  }),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DonutChartPainter extends CustomPainter {
  final List<CategoryDistribution> categories;
  final List<Color> colors;
  final int total;

  _DonutChartPainter({
    required this.categories,
    required this.colors,
    required this.total,
  });

  @override
  void paint(Canvas canvas, Size size) {
    if (total == 0 || categories.isEmpty) return;

    final center = Offset(size.width / 2, size.height / 2);
    final radius = math.min(size.width, size.height) / 2 - 8;
    const strokeWidth = 16.0;

    final rect = Rect.fromCircle(center: center, radius: radius);
    double startAngle = -math.pi / 2;

    for (int i = 0; i < categories.length; i++) {
      final sweepAngle = (categories[i].count / total) * 2 * math.pi;
      final paint = Paint()
        ..color = colors[i % colors.length]
        ..style = PaintingStyle.stroke
        ..strokeWidth = strokeWidth
        ..strokeCap = StrokeCap.butt;

      canvas.drawArc(rect, startAngle, sweepAngle, false, paint);
      startAngle += sweepAngle;
    }
  }

  @override
  bool shouldRepaint(covariant _DonutChartPainter oldDelegate) => true;
}

class _ExpiryDistributionCard extends StatelessWidget {
  final ExpiryDistribution distribution;

  const _ExpiryDistributionCard({required this.distribution});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final total = distribution.safe + distribution.expiring90d + distribution.expiring30d + distribution.expired;

    return Card3D(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Expiry Health Monitor', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
              Text('$total batches', style: TextStyle(fontSize: 12, color: theme.hintColor)),
            ],
          ),
          const SizedBox(height: 14),

          _buildProgressBar('Safe (>90 days)', distribution.safe, total, const Color(0xFF059669)),
          const SizedBox(height: 8),
          _buildProgressBar('Expiring 30-90 days', distribution.expiring90d, total, const Color(0xFFF59E0B)),
          const SizedBox(height: 8),
          _buildProgressBar('Urgent (<30 days)', distribution.expiring30d, total, const Color(0xFFEA580C)),
          const SizedBox(height: 8),
          _buildProgressBar('Expired (Disposal needed)', distribution.expired, total, BrandTokens.danger),
        ],
      ),
    );
  }

  Widget _buildProgressBar(String label, int count, int total, Color color) {
    final pct = total > 0 ? (count / total).clamp(0.0, 1.0) : 0.0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: const TextStyle(fontSize: 12)),
            Text(
              '$count batches',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: color),
            ),
          ],
        ),
        const SizedBox(height: 4),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: pct,
            backgroundColor: color.withValues(alpha: 0.12),
            valueColor: AlwaysStoppedAnimation<Color>(color),
            minHeight: 6,
          ),
        ),
      ],
    );
  }
}
