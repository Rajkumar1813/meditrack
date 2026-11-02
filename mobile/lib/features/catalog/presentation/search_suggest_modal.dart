import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/utils/debouncer.dart';
import '../../../core/network/api_client.dart';
import '../models/medicine_model.dart';

class SearchSuggestModal extends ConsumerStatefulWidget {
  const SearchSuggestModal({super.key});

  @override
  ConsumerState<SearchSuggestModal> createState() => _SearchSuggestModalState();
}

class _SearchSuggestModalState extends ConsumerState<SearchSuggestModal> {
  final TextEditingController _searchCtrl = TextEditingController();
  final Debouncer _debouncer = Debouncer(delay: const Duration(milliseconds: 250));
  List<MedicineModel> _medicineMatches = [];
  List<String> _saltMatches = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _searchCtrl.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchCtrl.removeListener(_onSearchChanged);
    _searchCtrl.dispose();
    _debouncer.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    final query = _searchCtrl.text.trim();
    if (query.isEmpty) {
      setState(() {
        _medicineMatches = [];
        _saltMatches = [];
        _isLoading = false;
      });
      return;
    }

    _debouncer.run(() async {
      setState(() => _isLoading = true);
      final api = ref.read(apiClientProvider);
      final res = await api.get('/search', queryParameters: {'q': query});

      if (!mounted) return;

      res.when(
        success: (data) {
          List<MedicineModel> meds = [];
          List<String> salts = [];

          if (data is Map<String, dynamic>) {
            final rawMeds = data['medicines'] ?? data['items'] ?? [];
            if (rawMeds is List) {
              meds = rawMeds.map((m) => MedicineModel.fromJson(m as Map<String, dynamic>)).toList();
            }
            final rawSalts = data['salts'] ?? [];
            if (rawSalts is List) {
              salts = rawSalts.map((s) => s.toString()).toList();
            }
          }
          setState(() {
            _medicineMatches = meds;
            _saltMatches = salts;
            _isLoading = false;
          });
        },
        failure: (_) {
          setState(() => _isLoading = false);
        },
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        titleSpacing: 0,
        title: TextField(
          key: const Key('search-suggest-input'),
          controller: _searchCtrl,
          autofocus: true,
          decoration: InputDecoration(
            hintText: 'Search medicines, salts or brands...',
            border: InputBorder.none,
            enabledBorder: InputBorder.none,
            focusedBorder: InputBorder.none,
            suffixIcon: _searchCtrl.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.clear),
                    onPressed: () => _searchCtrl.clear(),
                  )
                : null,
          ),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator.adaptive())
          : _searchCtrl.text.isEmpty
              ? Center(
                  child: Text(
                    'Type at least 2 letters to search...',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.textTheme.bodySmall?.color,
                    ),
                  ),
                )
              : ListView(
                  children: [
                    if (_saltMatches.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                        child: Text(
                          'Composition Salts',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: theme.colorScheme.primary,
                          ),
                        ),
                      ),
                      ..._saltMatches.map((salt) => ListTile(
                            leading: const Icon(Icons.science_outlined, size: 20),
                            title: Text(salt),
                            trailing: const Icon(Icons.chevron_right, size: 18),
                            onTap: () {
                              context.pop();
                              context.push('/catalog?q=${Uri.encodeComponent(salt)}');
                            },
                          )),
                      const Divider(),
                    ],
                    if (_medicineMatches.isNotEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                        child: Text(
                          'Medicines',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: theme.colorScheme.primary,
                          ),
                        ),
                      ),
                      ..._medicineMatches.map((med) => ListTile(
                            leading: const Icon(Icons.medication_rounded, size: 20),
                            title: Text(med.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                            subtitle: Text(
                              '${med.category} • ₹${med.price.toStringAsFixed(2)}',
                              style: theme.textTheme.bodySmall,
                            ),
                            trailing: med.requiresPrescription
                                ? Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFEE2E2),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: const Text(
                                      'Rx',
                                      style: TextStyle(
                                        color: Color(0xFFDC2626),
                                        fontSize: 10,
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  )
                                : null,
                            onTap: () {
                              context.pop();
                              context.push('/medicine/${med.id}');
                            },
                          )),
                    ] else if (_saltMatches.isEmpty) ...[
                      Padding(
                        padding: const EdgeInsets.all(32),
                        child: Center(
                          child: Text(
                            'No medicines found for "${_searchCtrl.text}"',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: theme.textTheme.bodySmall?.color,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
    );
  }
}

