import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/brand/brand_tokens.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../core/network/rate_limit_interceptor.dart';
import '../../../core/i18n/generated/app_localizations.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/primary_button.dart';
import '../../../shared/widgets/offline_banner.dart';
import '../controllers/auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final TextEditingController _identifierController = TextEditingController();

  @override
  void dispose() {
    _identifierController.dispose();
    super.dispose();
  }

  void _handleSendOtp() async {
    final authCtrl = ref.read(authControllerProvider.notifier);
    final success = await authCtrl.requestOtp(_identifierController.text);
    if (success && mounted) {
      context.push('/otp');
    }
  }

  @override
  Widget build(BuildContext context) {
    final brand = ref.watch(brandControllerProvider);
    final authState = ref.watch(authControllerProvider);
    final rateLimitSeconds = ref.watch(rateLimitProvider);
    final isRateLimited = rateLimitSeconds > 0;
    final theme = Theme.of(context);
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      body: SafeArea(
        child: Column(
          children: [
            const OfflineBanner(),
            Expanded(
              child: Center(
                child: SingleChildScrollView(
                  padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 420),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // App Brand Header
                        Center(
                          child: Container(
                            width: 64,
                            height: 64,
                            decoration: BoxDecoration(
                              color: brand.primaryColor.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Icon(
                              Icons.local_pharmacy_rounded,
                              size: 36,
                              color: brand.primaryColor,
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        Text(
                          brand.name,
                          style: theme.textTheme.headlineMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.5,
                          ),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 6),
                        Text(
                          brand.tagline,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: theme.textTheme.bodySmall?.color,
                          ),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 32),

                        // Rate limit countdown banner
                        if (isRateLimited) ...[
                          Container(
                            key: const Key('rate-limit-banner'),
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEF3C7),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: const Color(0xFFF59E0B)),
                            ),
                            child: Row(
                              children: [
                                const Icon(Icons.timer_outlined, size: 20, color: Color(0xFFB45309)),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    l10n?.rateLimitMessage(rateLimitSeconds) ??
                                        'Too many requests. Please wait ${rateLimitSeconds}s.',
                                    style: const TextStyle(
                                      color: Color(0xFF92400E),
                                      fontWeight: FontWeight.w600,
                                      fontSize: 13,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 16),
                        ],

                        // Main 3D Card
                        Card3D(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Text(
                                l10n?.loginTitle ?? 'Welcome to MediTrack',
                                style: theme.textTheme.titleLarge?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              const SizedBox(height: 6),
                              Text(
                                l10n?.loginSubtitle ?? 'Enter your mobile number or email to receive a 6-digit OTP',
                                style: theme.textTheme.bodySmall,
                              ),
                              const SizedBox(height: 20),

                              // Mode Tabs (Phone / Email)
                              Container(
                                padding: const EdgeInsets.all(4),
                                decoration: BoxDecoration(
                                  color: theme.brightness == Brightness.dark
                                      ? const Color(0xFF1E293B)
                                      : const Color(0xFFF1F5F9),
                                  borderRadius: BorderRadius.circular(BrandTokens.radiusButton),
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: _buildTabButton(
                                        key: const Key('tab-phone'),
                                        label: l10n?.phoneMode ?? 'Mobile Number',
                                        isSelected: authState.activeMode == 'phone',
                                        onTap: () {
                                          ref.read(authControllerProvider.notifier).setMode('phone');
                                          _identifierController.clear();
                                        },
                                      ),
                                    ),
                                    Expanded(
                                      child: _buildTabButton(
                                        key: const Key('tab-email'),
                                        label: l10n?.emailMode ?? 'Email',
                                        isSelected: authState.activeMode == 'email',
                                        onTap: () {
                                          ref.read(authControllerProvider.notifier).setMode('email');
                                          _identifierController.clear();
                                        },
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(height: 20),

                              // Identifier Input
                              TextField(
                                key: const Key('login-identifier-input'),
                                controller: _identifierController,
                                keyboardType: authState.activeMode == 'phone'
                                    ? TextInputType.phone
                                    : TextInputType.emailAddress,
                                decoration: InputDecoration(
                                  hintText: authState.activeMode == 'phone'
                                      ? (l10n?.phonePlaceholder ?? '10-digit mobile number')
                                      : (l10n?.emailPlaceholder ?? 'name@example.com'),
                                  prefixIcon: authState.activeMode == 'phone'
                                      ? Container(
                                          key: const Key('phone-prefix'),
                                          padding: const EdgeInsets.symmetric(horizontal: 14),
                                          alignment: Alignment.center,
                                          width: 60,
                                          child: Text(
                                            '+91',
                                            style: theme.textTheme.bodyMedium?.copyWith(
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        )
                                      : const Icon(Icons.email_outlined),
                                ),
                              ),
                              const SizedBox(height: 12),

                              // Error Message Box
                              if (authState.errorMessage != null) ...[
                                Container(
                                  key: const Key('login-error-message'),
                                  padding: const EdgeInsets.all(10),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFEE2E2),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(Icons.error_outline, size: 16, color: Color(0xFFDC2626)),
                                      const SizedBox(width: 8),
                                      Expanded(
                                        child: Text(
                                          authState.errorMessage!,
                                          style: const TextStyle(
                                            color: Color(0xFFB91C1C),
                                            fontSize: 12,
                                            fontWeight: FontWeight.w500,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 12),
                              ],

                              // Send OTP Button
                              PrimaryButton(
                                key: const Key('send-otp-btn'),
                                label: authState.isLoading
                                    ? (l10n?.sendingOtp ?? 'Sending...')
                                    : (l10n?.sendOtp ?? 'Send OTP'),
                                isLoading: authState.isLoading,
                                onPressed: isRateLimited ? null : _handleSendOtp,
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTabButton({
    required Key key,
    required String label,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return GestureDetector(
      key: key,
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: isSelected
              ? (isDark ? BrandTokens.darkSurface : Colors.white)
              : Colors.transparent,
          borderRadius: BorderRadius.circular(BrandTokens.radiusButton),
          boxShadow: isSelected
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 4,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
            color: isSelected
                ? theme.colorScheme.primary
                : (isDark ? BrandTokens.darkMuted : BrandTokens.lightMuted),
          ),
          textAlign: TextAlign.center,
        ),
      ),
    );
  }
}

