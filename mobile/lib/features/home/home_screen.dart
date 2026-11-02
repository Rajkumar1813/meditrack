import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/brand/brand_tokens.dart';
import '../../core/brand/brand_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/i18n/app_locale_controller.dart';
import '../../shared/widgets/card_3d.dart';
import '../../shared/widgets/status_chip.dart';
import '../../shared/widgets/offline_banner.dart';
import '../auth/controllers/auth_controller.dart';
import '../cart/controllers/cart_controller.dart';
import '../catalog/controllers/catalog_controller.dart';
import '../catalog/presentation/search_suggest_modal.dart';
import '../refill/presentation/refills_strip.dart';

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  int _currentNavIndex = 0;

  @override
  Widget build(BuildContext context) {
    final brand = ref.watch(brandControllerProvider);
    final authState = ref.watch(authControllerProvider);
    final cartState = ref.watch(cartControllerProvider);
    final catalogState = ref.watch(catalogControllerProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final currentLocale = ref.watch(appLocaleProvider);

    final user = authState.user;

    const categories = [
      {'name': 'Antibiotics', 'icon': Icons.healing_rounded},
      {'name': 'Cardiology', 'icon': Icons.favorite_rounded},
      {'name': 'Pain Relief', 'icon': Icons.local_hospital_rounded},
      {'name': 'Diabetes', 'icon': Icons.water_drop_rounded},
      {'name': 'Vitamins', 'icon': Icons.wb_sunny_rounded},
      {'name': 'Dermatology', 'icon': Icons.face_rounded},
    ];

    return Scaffold(
      key: const Key('customer-home-screen'),
      appBar: AppBar(
        title: Text(brand.name),
        actions: [
          IconButton(
            key: const Key('toggle-language-btn'),
            icon: Text(
              currentLocale.languageCode.toUpperCase(),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
            tooltip: 'Language',
            onPressed: () => ref.read(appLocaleProvider.notifier).toggleLocale(),
          ),
          IconButton(
            key: const Key('toggle-theme-btn'),
            icon: Icon(isDark ? Icons.light_mode_rounded : Icons.dark_mode_rounded),
            tooltip: 'Theme',
            onPressed: () => ref.read(themeModeProvider.notifier).toggleTheme(),
          ),
          IconButton(
            key: const Key('notifications-btn'),
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () => context.push('/notifications'),
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                key: const Key('home-cart-btn'),
                icon: const Icon(Icons.shopping_bag_outlined),
                onPressed: () => context.push('/cart'),
              ),
              if (cartState.totalCount > 0)
                Positioned(
                  top: 8,
                  right: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: const BoxDecoration(
                      color: Color(0xFFDC2626),
                      shape: BoxShape.circle,
                    ),
                    constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                    child: Text(
                      '${cartState.totalCount}',
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
        ],
      ),
      body: SafeArea(
        child: Column(
          children: [
            const OfflineBanner(),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  // Search Bar Trigger
                  GestureDetector(
                    key: const Key('home-search-bar'),
                    onTap: () {
                      showModalBottomSheet(
                        context: context,
                        isScrollControlled: true,
                        useSafeArea: true,
                        builder: (_) => const SearchSuggestModal(),
                      );
                    },
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      decoration: BoxDecoration(
                        color: isDark ? const Color(0xFF1E293B) : Colors.white,
                        borderRadius: BorderRadius.circular(BrandTokens.radiusInput),
                        border: Border.all(color: isDark ? const Color(0xFF334155) : const Color(0xFFE2E8F0)),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.04),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.search_rounded, color: Colors.grey),
                          const SizedBox(width: 12),
                          Text(
                            'Search medicines, salts, or brands...',
                            style: TextStyle(
                              color: isDark ? Colors.grey[400] : Colors.grey[600],
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Refills strip (if any)
                  const RefillsStrip(),

                  // Greeting & Role Card
                  Card3D(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Hello, ${user?.name ?? "Customer"}',
                              style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
                            ),
                            StatusChip(
                              key: const Key('customer-role-badge'),
                              status: user?.role ?? 'customer',
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
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

                  // Categories Carousel
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Top Categories', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                      TextButton(
                        onPressed: () => context.push('/catalog'),
                        child: const Text('View All'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    height: 90,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: categories.length,
                      separatorBuilder: (context, index) => const SizedBox(width: 12),
                      itemBuilder: (context, i) {
                        final cat = categories[i];
                        return GestureDetector(
                          onTap: () {
                            ref.read(catalogControllerProvider.notifier).setCategory(cat['name'] as String);
                            context.push('/catalog');
                          },
                          child: Column(
                            children: [
                              Container(
                                width: 56,
                                height: 56,
                                decoration: BoxDecoration(
                                  color: brand.primaryColor.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(16),
                                ),
                                child: Icon(cat['icon'] as IconData, color: brand.primaryColor),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                cat['name'] as String,
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Quick Action Cards
                  Row(
                    children: [
                      Expanded(
                        child: Card3D(
                          padding: const EdgeInsets.all(16),
                          onTap: () => context.push('/prescriptions'),
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
                              const Text('Upload Rx', style: TextStyle(fontWeight: FontWeight.w700)),
                              const SizedBox(height: 4),
                              Text('Doctor prescription', style: theme.textTheme.bodySmall?.copyWith(fontSize: 11)),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Card3D(
                          padding: const EdgeInsets.all(16),
                          onTap: () => context.push('/orders'),
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
                              const Text('My Orders', style: TextStyle(fontWeight: FontWeight.w700)),
                              const SizedBox(height: 4),
                              Text('Track fulfillment', style: theme.textTheme.bodySmall?.copyWith(fontSize: 11)),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Featured Medicines Strip
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Featured Medicines', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                      TextButton(
                        onPressed: () => context.push('/catalog'),
                        child: const Text('See All'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  if (catalogState.medicines.isNotEmpty) ...[
                    ...catalogState.medicines.take(4).map((med) => Card3D(
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.all(14),
                          onTap: () => context.push('/medicine/${med.id}'),
                          child: Row(
                            children: [
                              Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: const Color(0xFFD1FAE5),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: const Icon(Icons.medication_rounded, color: Color(0xFF047857)),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(med.name, style: const TextStyle(fontWeight: FontWeight.w700)),
                                    Text(med.category, style: theme.textTheme.bodySmall?.copyWith(fontSize: 11)),
                                  ],
                                ),
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                    '${brand.currencySymbol}${med.price.toStringAsFixed(2)}',
                                    style: const TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF059669)),
                                  ),
                                  const SizedBox(height: 4),
                                  InkWell(
                                    onTap: () {
                                      ref.read(cartControllerProvider.notifier).addItem(med);
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(content: Text('${med.name} added to cart')),
                                      );
                                    },
                                    child: Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: brand.primaryColor,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: const Text('Add', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        )),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentNavIndex,
        type: BottomNavigationBarType.fixed,
        selectedItemColor: brand.primaryColor,
        unselectedItemColor: isDark ? Colors.grey[400] : Colors.grey[600],
        onTap: (index) {
          setState(() => _currentNavIndex = index);
          switch (index) {
            case 0:
              break;
            case 1:
              context.push('/catalog');
              break;
            case 2:
              context.push('/prescriptions');
              break;
            case 3:
              context.push('/orders');
              break;
            case 4:
              context.push('/profile');
              break;
          }
        },
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.store_mall_directory_rounded), label: 'Store'),
          BottomNavigationBarItem(icon: Icon(Icons.medication_rounded), label: 'Catalog'),
          BottomNavigationBarItem(icon: Icon(Icons.document_scanner_rounded), label: 'Rx'),
          BottomNavigationBarItem(icon: Icon(Icons.receipt_long_rounded), label: 'Orders'),
          BottomNavigationBarItem(icon: Icon(Icons.person_outline_rounded), label: 'Profile'),
        ],
      ),
    );
  }
}
