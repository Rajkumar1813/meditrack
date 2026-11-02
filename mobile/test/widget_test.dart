import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:meditrack/core/storage/pref_storage.dart';
import 'package:meditrack/core/network/rate_limit_interceptor.dart';
import 'package:meditrack/core/storage/secure_storage.dart';
import 'package:meditrack/core/network/api_client.dart';
import 'package:meditrack/core/push/fcm_service.dart';
import 'package:meditrack/core/i18n/generated/app_localizations.dart';
import 'package:meditrack/features/auth/models/user_model.dart';
import 'package:meditrack/features/auth/controllers/auth_controller.dart';
import 'package:meditrack/features/auth/presentation/login_screen.dart';
import 'package:meditrack/features/auth/presentation/otp_verify_screen.dart';
import 'package:meditrack/features/home/home_screen.dart';
import 'package:meditrack/features/owner/owner_screen.dart';
import 'package:meditrack/shared/widgets/card_3d.dart';

Widget createTestableWidget({
  required Widget child,
  required SharedPreferences prefs,
  List<Override> overrides = const [],
  bool disableAnimations = false,
}) {
  return ProviderScope(
    overrides: [
      sharedPreferencesProvider.overrideWithValue(prefs),
      ...overrides,
    ],
    child: MaterialApp(
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      home: MediaQuery(
        data: MediaQueryData(disableAnimations: disableAnimations),
        child: child,
      ),
    ),
  );
}

void main() {
  late SharedPreferences prefs;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    prefs = await SharedPreferences.getInstance();
  });

  group('Login & OTP Screen Widget Tests', () {
    testWidgets('mode toggle switches between mobile number and email', (tester) async {
      await tester.pumpWidget(createTestableWidget(prefs: prefs, child: const LoginScreen()));
      await tester.pumpAndSettle();

      // Default is phone mode: prefix "+91" is visible
      expect(find.byKey(const Key('phone-prefix')), findsOneWidget);
      expect(find.text('+91'), findsOneWidget);

      // Tap Email tab
      await tester.tap(find.byKey(const Key('tab-email')));
      await tester.pumpAndSettle();

      // Prefix is gone, email icon appears
      expect(find.byKey(const Key('phone-prefix')), findsNothing);
      expect(find.byIcon(Icons.email_outlined), findsOneWidget);

      // Tap Phone tab again
      await tester.tap(find.byKey(const Key('tab-phone')));
      await tester.pumpAndSettle();

      // Prefix is back
      expect(find.byKey(const Key('phone-prefix')), findsOneWidget);
    });

    testWidgets('validates invalid mobile number and shows error message', (tester) async {
      await tester.pumpWidget(createTestableWidget(prefs: prefs, child: const LoginScreen()));
      await tester.pumpAndSettle();

      // Enter short number '123'
      await tester.enterText(find.byKey(const Key('login-identifier-input')), '123');
      await tester.tap(find.byKey(const Key('send-otp-btn')));
      await tester.pumpAndSettle();

      // Error banner is displayed
      expect(find.byKey(const Key('login-error-message')), findsOneWidget);
      expect(find.textContaining('Please enter a valid 10-digit mobile number'), findsOneWidget);
    });

    testWidgets('rate limit countdown banner displays seconds and disables button', (tester) async {
      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          overrides: [
            rateLimitProvider.overrideWith((ref) {
              final notifier = RateLimitNotifier();
              notifier.triggerRateLimit(42);
              return notifier;
            }),
          ],
          child: const LoginScreen(),
        ),
      );
      await tester.pumpAndSettle();

      // Banner is shown
      expect(find.byKey(const Key('rate-limit-banner')), findsOneWidget);
      expect(find.textContaining('42s'), findsOneWidget);

      // Send OTP button onPressed is null (disabled)
      final elevatedButton = tester.widget<ElevatedButton>(find.byType(ElevatedButton));
      expect(elevatedButton.onPressed, isNull);
    });

    testWidgets('otp verify screen renders 6 digit input boxes and target phone', (tester) async {
      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          overrides: [
            authControllerProvider.overrideWith((ref) {
              final authNotifier = AuthController(
                ref.read(secureStorageProvider),
                ref.read(apiClientProvider),
                ref.read(fcmServiceProvider),
              );
              authNotifier.state = const AuthState(
                isOtpSent: true,
                activeIdentifier: '+919811111111',
                resendCooldown: 58,
              );
              return authNotifier;
            }),
          ],
          child: const OtpVerifyScreen(),
        ),
      );
      await tester.pumpAndSettle();

      // Verify target phone number is shown
      expect(find.textContaining('+919811111111'), findsOneWidget);

      // Verify all 6 digit input boxes exist
      for (int i = 0; i < 6; i++) {
        expect(find.byKey(Key('otp-digit-$i')), findsOneWidget);
      }

      // Verify 58s resend timer is running
      expect(find.textContaining('58s'), findsOneWidget);
    });
  });

  group('Role-Based Home Destinations', () {
    testWidgets('customer role renders customer home screen with customer badge', (tester) async {
      const customer = UserModel(
        id: 10,
        name: 'Rahul Gupta',
        role: 'customer',
        phone: '+919833333333',
      );

      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          overrides: [
            authControllerProvider.overrideWith((ref) {
              final authNotifier = AuthController(
                ref.read(secureStorageProvider),
                ref.read(apiClientProvider),
                ref.read(fcmServiceProvider),
              );
              authNotifier.state = const AuthState(
                isAuthenticated: true,
                user: customer,
              );
              return authNotifier;
            }),
          ],
          child: const HomeScreen(),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('customer-home-screen')), findsOneWidget);
      expect(find.text('Hello, Rahul Gupta'), findsOneWidget);
      expect(find.byKey(const Key('customer-role-badge')), findsOneWidget);
      expect(find.text('CUSTOMER'), findsOneWidget);
    });

    testWidgets('pharmacist role renders owner dashboard with pharmacist badge and KPIs', (tester) async {
      const pharmacist = UserModel(
        id: 2,
        name: 'Dr. Priya Sharma',
        role: 'pharmacist',
        phone: '+919822222222',
      );

      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          overrides: [
            authControllerProvider.overrideWith((ref) {
              final authNotifier = AuthController(
                ref.read(secureStorageProvider),
                ref.read(apiClientProvider),
                ref.read(fcmServiceProvider),
              );
              authNotifier.state = const AuthState(
                isAuthenticated: true,
                user: pharmacist,
              );
              return authNotifier;
            }),
          ],
          child: const OwnerScreen(),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('owner-home-screen')), findsOneWidget);
      expect(find.text('Dr. Priya Sharma'), findsOneWidget);
      expect(find.byKey(const Key('owner-role-badge')), findsOneWidget);
      expect(find.text('PHARMACIST'), findsOneWidget);
      expect(find.text('Total Medicines'), findsOneWidget);
      expect(find.text('Units in Stock'), findsOneWidget);
      expect(find.text('Expiring in 30d'), findsOneWidget);
    });
  });

  group('Card3D Widget Animations and Reduced Motion', () {
    testWidgets('Card3D renders child normally when reduce-motion is enabled', (tester) async {
      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          disableAnimations: true,
          child: const Card3D(
            child: Text('Safe 3D Card'),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Safe 3D Card'), findsOneWidget);
    });
  });
}
