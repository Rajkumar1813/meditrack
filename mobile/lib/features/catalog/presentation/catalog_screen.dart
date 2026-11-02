import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/primary_button.dart';
import '../controllers/catalog_controller.dart';
import '../models/medicine_model.dart';
import '../../cart/controllers/cart_controller.dart';

class CatalogScreen extends ConsumerStatefulWidget {
  final String? initialQuery;

  const CatalogScreen({super.key, this.initialQuery});

  @override
  ConsumerState<CatalogScreen> createState() => _CatalogScreenState();
}

class _CatalogScreenState extends ConsumerState<CatalogScreen> {
  final ScrollController _scrollController = ScrollController();
  bool _isGridView = false;

  @override
  void initState() {
    super.initState();
    if (widget.initialQuery != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        ref.read(catalogControllerProvider.notifier).setSearchQuery(widget.initialQuery!);
      });
    }
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      ref.read(catalogControllerProvider.notifier).fetchMedicines();
    }
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  void _showFilterSheet() {
    final catalogState = ref.read(catalogControllerProvider);
    final notifier = ref.read(catalogControllerProvider.notifier);

    const categories = [
      'All',
      'Antibiotics',
      'Cardiology',
      'Pain Relief',
      'Diabetes',
      'Vitamins & Supplements',
      'Dermatology',
      'Respiratory',
    ];

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) {
        return StatefulBuilder(
          builder: (context, setSheetState) {
            return Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Filter Catalog', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                      IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
                    ],
                  ),
                  const SizedBox(height: 16),
                  const Text('Category', style: TextStyle(fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: categories.map((cat) {
                      final isSelected = (cat == 'All' && catalogState.selectedCategory == null) ||
                          catalogState.selectedCategory == cat;
                      return ChoiceChip(
                        label: Text(cat),
                        selected: isSelected,
                        onSelected: (_) {
                          notifier.setCategory(cat == 'All' ? null : cat);
                          Navigator.pop(context);
                        },
                      );
                    }).toList(),
                  ),
                  const SizedBox(height: 16),
                  SwitchListTile(
                    title: const Text('Prescription (Rx) Required Only'),
                    value: catalogState.rxOnly,
                    onChanged: (_) {
                      notifier.toggleRxFilter();
                      Navigator.pop(context);
                    },
                  ),
                  SwitchListTile(
                    title: const Text('In-Stock Only'),
                    value: catalogState.inStockOnly,
                    onChanged: (_) {
                      notifier.toggleInStockFilter();
                      Navigator.pop(context);
                    },
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final catalogState = ref.watch(catalogControllerProvider);
    final brand = ref.watch(brandControllerProvider);
    final cartState = ref.watch(cartControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: Text(catalogState.selectedCategory ?? 'Medicines Catalog'),
        actions: [
          IconButton(
            icon: Icon(_isGridView ? Icons.view_list_rounded : Icons.grid_view_rounded),
            onPressed: () => setState(() => _isGridView = !_isGridView),
          ),
          IconButton(
            icon: const Icon(Icons.tune_rounded),
            onPressed: _showFilterSheet,
          ),
          Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                key: const Key('catalog-cart-btn'),
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
      body: RefreshIndicator.adaptive(
        onRefresh: () async {
          await ref.read(catalogControllerProvider.notifier).fetchMedicines(isRefresh: true);
        },
        child: catalogState.medicines.isEmpty
            ? (catalogState.isLoading
                ? const Center(child: CircularProgressIndicator.adaptive())
                : Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.medication_outlined, size: 64, color: Colors.grey),
                        const SizedBox(height: 16),
                        const Text('No medicines found matching filters.'),
                        const SizedBox(height: 12),
                        OutlinedButton(
                          onPressed: () {
                            ref.read(catalogControllerProvider.notifier).setCategory(null);
                          },
                          child: const Text('Clear Filters'),
                        ),
                      ],
                    ),
                  ))
            : (_isGridView
                ? GridView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      childAspectRatio: 0.72,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                    ),
                    itemCount: catalogState.medicines.length,
                    itemBuilder: (context, i) => _buildMedicineGridItem(catalogState.medicines[i], brand.currencySymbol),
                  )
                : ListView.separated(
                    controller: _scrollController,
                    padding: const EdgeInsets.all(16),
                    itemCount: catalogState.medicines.length,
                    separatorBuilder: (context, index) => const SizedBox(height: 12),
                    itemBuilder: (context, i) => _buildMedicineListItem(catalogState.medicines[i], brand.currencySymbol),
                  )),
      ),
    );
  }

  Widget _buildMedicineListItem(MedicineModel med, String currency) {
    return Card3D(
      padding: const EdgeInsets.all(16),
      onTap: () => context.push('/medicine/${med.id}'),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFD1FAE5),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.medication_rounded, color: Color(0xFF047857)),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        med.name,
                        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15),
                      ),
                    ),
                    if (med.requiresPrescription)
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEE2E2),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: const Text(
                          'Rx',
                          style: TextStyle(color: Color(0xFFDC2626), fontSize: 10, fontWeight: FontWeight.w700),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  med.salts.isNotEmpty ? med.salts.join(', ') : med.category,
                  style: Theme.of(context).textTheme.bodySmall,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(
                      children: [
                        Text(
                          '$currency${med.price.toStringAsFixed(2)}',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                        ),
                        if (med.mrp > med.price) ...[
                          const SizedBox(width: 6),
                          Text(
                            '$currency${med.mrp.toStringAsFixed(2)}',
                            style: TextStyle(
                              decoration: TextDecoration.lineThrough,
                              color: Theme.of(context).textTheme.bodySmall?.color,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ],
                    ),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                        minimumSize: Size.zero,
                      ),
                      onPressed: () {
                        ref.read(cartControllerProvider.notifier).addItem(med);
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text('${med.name} added to cart'),
                            duration: const Duration(seconds: 1),
                          ),
                        );
                      },
                      child: const Text('Add', style: TextStyle(fontSize: 12)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMedicineGridItem(MedicineModel med, String currency) {
    return Card3D(
      padding: const EdgeInsets.all(12),
      onTap: () => context.push('/medicine/${med.id}'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: const Color(0xFFD1FAE5),
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(Icons.medication_rounded, size: 28, color: Color(0xFF047857)),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            med.name,
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          Text(
            med.salts.isNotEmpty ? med.salts.first : med.category,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 11),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const Spacer(),
          Text(
            '$currency${med.price.toStringAsFixed(2)}',
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
          ),
          const SizedBox(height: 8),
          PrimaryButton(
            label: 'Add',
            height: 34,
            onPressed: () {
              ref.read(cartControllerProvider.notifier).addItem(med);
            },
          ),
        ],
      ),
    );
  }
}
