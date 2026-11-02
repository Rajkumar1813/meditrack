// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Hindi (`hi`).
class AppLocalizationsHi extends AppLocalizations {
  AppLocalizationsHi([String locale = 'hi']) : super(locale);

  @override
  String get appName => 'MediTrack';

  @override
  String get tagline => 'आपकी विश्वसनीय ऑनलाइन फार्मेसी';

  @override
  String get adminTagline => 'फार्मेसी इन्वेंटरी ओएस';

  @override
  String get loginTitle => 'MediTrack में आपका स्वागत है';

  @override
  String get loginSubtitle =>
      '6-अंकों का OTP पाने के लिए मोबाइल नंबर या ईमेल दर्ज करें';

  @override
  String get phoneMode => 'मोबाइल नंबर';

  @override
  String get emailMode => 'ईमेल';

  @override
  String get phonePlaceholder => '10-अंकों का मोबाइल नंबर';

  @override
  String get emailPlaceholder => 'name@example.com';

  @override
  String get sendOtp => 'OTP भेजें';

  @override
  String get sendingOtp => 'भेज रहे हैं...';

  @override
  String get verifyOtp => 'सत्यापित करें और आगे बढ़ें';

  @override
  String get verifyingOtp => 'सत्यापित कर रहे हैं...';

  @override
  String enterOtpFor(String target) {
    return '$target पर भेजा गया 6-अंकों का कोड दर्ज करें';
  }

  @override
  String resendIn(int seconds) {
    return '${seconds}s में पुनः भेजें';
  }

  @override
  String get resendOtp => 'OTP पुनः भेजें';

  @override
  String get invalidPhone => 'कृपया वैध 10-अंकों का मोबाइल नंबर दर्ज करें';

  @override
  String get invalidEmail => 'कृपया वैध ईमेल दर्ज करें';

  @override
  String get invalidOtp => 'कृपया पूरा 6-अंकों का OTP दर्ज करें';

  @override
  String rateLimitMessage(int seconds) {
    return 'बहुत अधिक अनुरोध। कृपया ${seconds}s प्रतीक्षा करें।';
  }

  @override
  String get homeTitle => 'फार्मेसी स्टोर';

  @override
  String get ownerTitle => 'फार्मेसी इन्वेंटरी ओएस';

  @override
  String get customerRole => 'ग्राहक';

  @override
  String get pharmacistRole => 'फार्मासिस्ट';

  @override
  String get adminRole => 'एडमिन';

  @override
  String get staffRole => 'स्टाफ';

  @override
  String get offlineBanner =>
      'आप ऑफ़लाइन हैं। सहेजा गया डेटा दिखाया जा रहा है।';

  @override
  String get themeMode => 'थीम';

  @override
  String get language => 'भाषा';

  @override
  String get logout => 'लॉग आउट';

  @override
  String get retry => 'पुनः प्रयास करें';

  @override
  String get errorGeneric => 'कुछ गलत हो गया। कृपया पुनः प्रयास करें।';

  @override
  String get changeIdentifier => 'नंबर / ईमेल बदलें';
}
