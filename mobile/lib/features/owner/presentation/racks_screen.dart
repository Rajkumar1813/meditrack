import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../shared/widgets/card_3d.dart';
import '../controllers/owner_controller.dart';
import '../models/rack_model.dart';

class RacksScreen extends ConsumerStatefulWidget {
  const RacksScreen({super.key});

  @override
  ConsumerState<RacksScreen> createState() => _RacksScreenState();
}

class _RacksScreenState extends ConsumerState<RacksScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(ownerControllerProvider.notifier).fetchRacks();
    });
  }

  void _showRackMedicinesSheet(RackModel rack) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Medicines in ${rack.name}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
              ],
            ),
            const SizedBox(height: 12),
            const Text('Shelves in this rack:'),
            const SizedBox(height: 8),
            ...rack.shelves.map((s) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.shelves, color: Color(0xFF059669)),
                  title: Text(s.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text('Capacity: ${s.capacity} units'),
                )),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ownerState = ref.watch(ownerControllerProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Racks & Locations')),
      body: RefreshIndicator.adaptive(
        onRefresh: () => ref.read(ownerControllerProvider.notifier).fetchRacks(),
        child: ownerState.racks.isEmpty
            ? (ownerState.isLoading
                ? const Center(child: CircularProgressIndicator.adaptive())
                : const Center(child: Text('No racks configured.')))
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: ownerState.racks.length,
                separatorBuilder: (context, index) => const SizedBox(height: 12),
                itemBuilder: (context, i) {
                  final rack = ownerState.racks[i];
                  return Card3D(
                    padding: const EdgeInsets.all(16),
                    onTap: () => _showRackMedicinesSheet(rack),
                    child: Row(
                      children: [
                        Container(
                          width: 48,
                          height: 48,
                          decoration: BoxDecoration(
                            color: const Color(0xFFD1FAE5),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(Icons.shelves, color: Color(0xFF047857)),
                        ),
                        const SizedBox(width: 16),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(rack.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                              const SizedBox(height: 4),
                              Text('Zone: ${rack.zone ?? "Main Dispensary"} • ${rack.shelves.length} shelves'),
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right),
                      ],
                    ),
                  );
                },
              ),
      ),
    );
  }
}
