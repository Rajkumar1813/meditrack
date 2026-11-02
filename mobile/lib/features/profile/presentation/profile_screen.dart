import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/i18n/app_locale_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/status_chip.dart';
import '../../auth/controllers/auth_controller.dart';
import '../controllers/address_controller.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authControllerProvider);
    final addrState = ref.watch(addressControllerProvider);
    final themeMode = ref.watch(themeModeProvider);
    final locale = ref.watch(appLocaleProvider);
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    final user = authState.user;

    return Scaffold(
      appBar: AppBar(title: const Text('My Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // User Card
          Card3D(
            padding: const EdgeInsets.all(20),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 30,
                  backgroundColor: const Color(0xFFD1FAE5),
                  child: Text(
                    user != null && user.name.isNotEmpty ? user.name[0].toUpperCase() : 'U',
                    style: const TextStyle(color: Color(0xFF047857), fontSize: 24, fontWeight: FontWeight.w700),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user?.name ?? 'Customer', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 17)),
                      const SizedBox(height: 4),
                      Text(user?.phone ?? user?.email ?? '', style: theme.textTheme.bodySmall),
                      const SizedBox(height: 8),
                      StatusChip(status: user?.role ?? 'customer'),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Preferences Card
          Card3D(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Preferences', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                const SizedBox(height: 12),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(isDark ? Icons.dark_mode_rounded : Icons.light_mode_rounded),
                  title: const Text('Theme'),
                  subtitle: Text(themeMode == ThemeMode.dark ? 'Dark Mode' : 'Light Mode'),
                  trailing: Switch(
                    value: isDark,
                    onChanged: (_) => ref.read(themeModeProvider.notifier).toggleTheme(),
                  ),
                ),
                const Divider(),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.language_rounded),
                  title: const Text('Language'),
                  subtitle: Text(locale.languageCode == 'hi' ? 'हिंदी (Hindi)' : 'English'),
                  trailing: TextButton(
                    onPressed: () => ref.read(appLocaleProvider.notifier).toggleLocale(),
                    child: Text(locale.languageCode == 'hi' ? 'Switch to English' : 'हिंदी में बदलें'),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // Saved Addresses Card
          Card3D(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Saved Addresses', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                    TextButton.icon(
                      icon: const Icon(Icons.add, size: 16),
                      label: const Text('Add'),
                      onPressed: () => _showAddAddressDialog(context, ref),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                if (addrState.addresses.isEmpty)
                  const Text('No addresses saved yet')
                else
                  ...addrState.addresses.map((a) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: const Icon(Icons.location_on_outlined),
                        title: Text(a.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                        subtitle: Text(a.fullAddress),
                        trailing: IconButton(
                          icon: const Icon(Icons.delete_outline, size: 18),
                          onPressed: () => ref.read(addressControllerProvider.notifier).deleteAddress(a.id),
                        ),
                      )),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Logout
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: const Color(0xFFDC2626),
              side: const BorderSide(color: Color(0xFFDC2626)),
            ),
            icon: const Icon(Icons.logout_rounded),
            label: const Text('Logout'),
            onPressed: () async {
              final confirm = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Logout'),
                  content: const Text('Are you sure you want to log out of MediTrack?'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
                    TextButton(
                      onPressed: () => Navigator.pop(ctx, true),
                      child: const Text('Logout', style: TextStyle(color: Colors.red)),
                    ),
                  ],
                ),
              );

              if (confirm == true) {
                ref.read(authControllerProvider.notifier).logout();
              }
            },
          ),
          const SizedBox(height: 40),
        ],
      ),
    );
  }

  void _showAddAddressDialog(BuildContext context, WidgetRef ref) {
    final nameCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    final lineCtrl = TextEditingController();
    final cityCtrl = TextEditingController(text: 'New Delhi');
    final stateCtrl = TextEditingController(text: 'Delhi');
    final pinCtrl = TextEditingController(text: '110001');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Add Address'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Name')),
              const SizedBox(height: 8),
              TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone')),
              const SizedBox(height: 8),
              TextField(controller: lineCtrl, decoration: const InputDecoration(labelText: 'Address Line')),
              const SizedBox(height: 8),
              TextField(controller: cityCtrl, decoration: const InputDecoration(labelText: 'City')),
              const SizedBox(height: 8),
              TextField(controller: pinCtrl, decoration: const InputDecoration(labelText: 'Pincode')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              if (nameCtrl.text.isNotEmpty && lineCtrl.text.isNotEmpty) {
                ref.read(addressControllerProvider.notifier).addAddress(
                      name: nameCtrl.text,
                      phone: phoneCtrl.text,
                      addressLine: lineCtrl.text,
                      city: cityCtrl.text,
                      stateName: stateCtrl.text,
                      pincode: pinCtrl.text,
                    );
                Navigator.pop(ctx);
              }
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
  }
}
