// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'MediTrack';

  @override
  String get tagline => 'Your Trusted Online Pharmacy';

  @override
  String get adminTagline => 'Pharmacy Inventory OS';

  @override
  String get loginTitle => 'Welcome to MediTrack';

  @override
  String get loginSubtitle =>
      'Enter your mobile number or email to receive a 6-digit OTP';

  @override
  String get phoneMode => 'Mobile Number';

  @override
  String get emailMode => 'Email';

  @override
  String get phonePlaceholder => '10-digit mobile number';

  @override
  String get emailPlaceholder => 'name@example.com';

  @override
  String get sendOtp => 'Send OTP';

  @override
  String get sendingOtp => 'Sending...';

  @override
  String get verifyOtp => 'Verify & Proceed';

  @override
  String get verifyingOtp => 'Verifying...';

  @override
  String enterOtpFor(String target) {
    return 'Enter 6-digit code sent to $target';
  }

  @override
  String resendIn(int seconds) {
    return 'Resend code in ${seconds}s';
  }

  @override
  String get resendOtp => 'Resend OTP';

  @override
  String get invalidPhone => 'Please enter a valid 10-digit mobile number';

  @override
  String get invalidEmail => 'Please enter a valid email address';

  @override
  String get invalidOtp => 'Please enter all 6 digits of the OTP';

  @override
  String rateLimitMessage(int seconds) {
    return 'Too many requests. Please wait ${seconds}s.';
  }

  @override
  String get homeTitle => 'Pharmacy Store';

  @override
  String get ownerTitle => 'Pharmacy Inventory OS';

  @override
  String get customerRole => 'Customer';

  @override
  String get pharmacistRole => 'Pharmacist';

  @override
  String get adminRole => 'Admin';

  @override
  String get staffRole => 'Staff';

  @override
  String get offlineBanner =>
      'You are currently offline. Showing cached catalog.';

  @override
  String get themeMode => 'Theme';

  @override
  String get language => 'Language';

  @override
  String get logout => 'Logout';

  @override
  String get retry => 'Retry';

  @override
  String get errorGeneric => 'Something went wrong. Please try again.';

  @override
  String get changeIdentifier => 'Change number / email';
}
