import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../core/i18n/generated/app_localizations.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/primary_button.dart';
import '../controllers/auth_controller.dart';

class OtpVerifyScreen extends ConsumerStatefulWidget {
  const OtpVerifyScreen({super.key});

  @override
  ConsumerState<OtpVerifyScreen> createState() => _OtpVerifyScreenState();
}

class _OtpVerifyScreenState extends ConsumerState<OtpVerifyScreen> {
  final List<TextEditingController> _controllers = List.generate(6, (_) => TextEditingController());
  final List<FocusNode> _focusNodes = List.generate(6, (_) => FocusNode());

  @override
  void dispose() {
    for (final c in _controllers) {
      c.dispose();
    }
    for (final f in _focusNodes) {
      f.dispose();
    }
    super.dispose();
  }

  String get _otpCode => _controllers.map((c) => c.text).join();

  void _handleVerify() async {
    final code = _otpCode;
    final authCtrl = ref.read(authControllerProvider.notifier);
    await authCtrl.verifyOtp(code);
  }

  void _onDigitChanged(int index, String value) {
    if (value.isNotEmpty) {
      if (value.length > 1) {
        // Handle paste of complete 6 digits
        final clean = value.replaceAll(RegExp(r'\D'), '').substring(0, 6);
        for (int i = 0; i < clean.length && i < 6; i++) {
          _controllers[i].text = clean[i];
        }
        final nextIndex = clean.length < 6 ? clean.length : 5;
        _focusNodes[nextIndex].requestFocus();
      } else {
        if (index < 5) {
          _focusNodes[index + 1].requestFocus();
        } else {
          _focusNodes[index].unfocus();
        }
      }
    }

    if (_otpCode.length == 6) {
      _handleVerify();
    }
  }

  @override
  Widget build(BuildContext context) {
    final brand = ref.watch(brandControllerProvider);
    final authState = ref.watch(authControllerProvider);
    final theme = Theme.of(context);
    final l10n = AppLocalizations.of(context);

    final target = authState.activeIdentifier ?? 'your registered device';

    return Scaffold(
      appBar: AppBar(
        title: Text(brand.name),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () {
            ref.read(authControllerProvider.notifier).resetOtpFlow();
            context.pop();
          },
        ),
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Card3D(
                padding: const EdgeInsets.all(24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Verify Code',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      l10n?.enterOtpFor(target) ?? 'Enter 6-digit code sent to $target',
                      key: const Key('sent-target-label'),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.textTheme.bodySmall?.color,
                      ),
                    ),
                    const SizedBox(height: 24),

                    // 6 OTP Digit Fields
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: List.generate(6, (index) {
                        return SizedBox(
                          width: 44,
                          height: 52,
                          child: TextField(
                            key: Key('otp-digit-$index'),
                            controller: _controllers[index],
                            focusNode: _focusNodes[index],
                            keyboardType: TextInputType.number,
                            textAlign: TextAlign.center,
                            maxLength: 1,
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                            inputFormatters: [
                              FilteringTextInputFormatter.digitsOnly,
                            ],
                            decoration: InputDecoration(
                              counterText: '',
                              contentPadding: EdgeInsets.zero,
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                            ),
                            onChanged: (val) => _onDigitChanged(index, val),
                          ),
                        );
                      }),
                    ),
                    const SizedBox(height: 16),

                    // Error Message
                    if (authState.errorMessage != null) ...[
                      Container(
                        key: const Key('otp-error-message'),
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEE2E2),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          authState.errorMessage!,
                          style: const TextStyle(
                            color: Color(0xFFB91C1C),
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Resend Timer / Button
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Flexible(
                          child: authState.resendCooldown > 0
                              ? Text(
                                  l10n?.resendIn(authState.resendCooldown) ??
                                      'Resend code in ${authState.resendCooldown}s',
                                  key: const Key('resend-timer-text'),
                                  style: theme.textTheme.bodySmall?.copyWith(
                                    fontWeight: FontWeight.w600,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                )
                              : TextButton(
                                  key: const Key('resend-otp-btn'),
                                  onPressed: () {
                                    ref.read(authControllerProvider.notifier).resendOtp();
                                  },
                                  child: Text(
                                    l10n?.resendOtp ?? 'Resend OTP',
                                    style: TextStyle(
                                      color: theme.colorScheme.primary,
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                        ),
                        TextButton(
                          key: const Key('change-identifier-btn'),
                          onPressed: () {
                            ref.read(authControllerProvider.notifier).resetOtpFlow();
                            context.pop();
                          },
                          child: Text(
                            l10n?.changeIdentifier ?? 'Change number',
                            style: TextStyle(
                              color: theme.textTheme.bodySmall?.color,
                              fontSize: 12,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Verify Button
                    PrimaryButton(
                      key: const Key('verify-otp-btn'),
                      label: authState.isLoading
                          ? (l10n?.verifyingOtp ?? 'Verifying...')
                          : (l10n?.verifyOtp ?? 'Verify & Proceed'),
                      isLoading: authState.isLoading,
                      onPressed: _handleVerify,
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

