import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:meditrack/core/storage/pref_storage.dart';
import 'package:meditrack/core/storage/secure_storage.dart';
import 'package:meditrack/core/push/fcm_service.dart';
import 'package:meditrack/core/i18n/generated/app_localizations.dart';
import 'package:meditrack/core/network/api_client.dart';
import 'package:meditrack/core/network/api_result.dart';
import 'package:meditrack/features/auth/models/user_model.dart';
import 'package:meditrack/features/auth/controllers/auth_controller.dart';
import 'package:meditrack/features/cart/models/cart_item_model.dart';
import 'package:meditrack/features/cart/controllers/cart_controller.dart';
import 'package:meditrack/features/checkout/presentation/checkout_screen.dart';
import 'package:meditrack/features/profile/models/address_model.dart';
import 'package:meditrack/features/profile/controllers/address_controller.dart';
import 'package:meditrack/features/orders/presentation/order_detail_screen.dart';
import 'package:meditrack/features/catalog/models/medicine_model.dart';
import 'package:meditrack/features/owner/controllers/owner_controller.dart';
import 'package:meditrack/features/owner/presentation/inventory_screen.dart';
import 'package:meditrack/router/app_router.dart';

class _FakeApiClient extends ApiClient {
  _FakeApiClient() : super(Dio());

  @override
  Future<ApiResult<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? fromJson,
  }) async {
    if (path.startsWith('/orders/')) {
      final id = int.tryParse(path.split('/').last) ?? 7001;
      return ApiResult.success({
        'id': id,
        'order_number': 'ORD-$id',
        'status': 'confirmed',
        'payment_method': 'cod',
        'payment_status': 'pending',
        'total': 60.0,
        'subtotal': 60.0,
        'gst_amount': 0.0,
        'delivery_fee': 0.0,
        'created_at': '2026-11-02 12:00:00',
        'items': [
          {
            'medicine_id': 101,
            'medicine_name': 'Paracetamol 650mg',
            'quantity': 2,
            'unit_price': 30.0,
            'total_price': 60.0,
          }
        ],
      } as T);
    }
    if (path == '/admin/medicines') {
      return ApiResult.success({
        'items': [
          {
            'id': 1,
            'name': 'Dolo 650mg',
            'slug': 'dolo-650mg',
            'category': 'Tablets',
            'price': 30.0,
            'mrp': 35.0,
            'requires_prescription': 0,
            'salts': ['Paracetamol 650mg'],
            'manufacturer': 'Micro Labs',
            'stock': 100,
          }
        ]
      } as T);
    }
    return ApiResult.success([] as T);
  }

  @override
  Future<ApiResult<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    T Function(dynamic data)? fromJson,
  }) async {
    if (path == '/orders') {
      return ApiResult.success({
        'id': 7001,
        'order_number': 'ORD-7001',
        'status': 'confirmed',
        'payment_method': 'cod',
        'payment_status': 'pending',
        'total': 60.0,
        'subtotal': 60.0,
        'gst_amount': 0.0,
        'delivery_fee': 0.0,
        'created_at': '2026-11-02 12:00:00',
        'items': [
          {
            'medicine_id': 101,
            'medicine_name': 'Paracetamol 650mg',
            'quantity': 2,
            'unit_price': 30.0,
            'total_price': 60.0,
          }
        ],
      } as T);
    }
    if (path == '/admin/medicines') {
      return ApiResult.success({
        'id': 99,
        'name': (data is Map ? data['name'] : null) ?? 'Azithromycin 500mg',
        'slug': 'azithromycin-500mg',
        'category': (data is Map ? data['category'] : null) ?? 'Antibiotics',
        'price': (data is Map ? (data['price'] as num?)?.toDouble() : null) ?? 120.0,
        'mrp': (data is Map ? (data['mrp'] as num?)?.toDouble() : null) ?? 140.0,
        'requires_prescription': 0,
        'salts': ['Azithromycin 500mg'],
        'manufacturer': 'Cipla',
        'stock': 100,
      } as T);
    }
    return ApiResult.success({} as T);
  }
}

Widget createTestableWidget({
  required Widget child,
  required SharedPreferences prefs,
  List<Override> overrides = const [],
}) {
  final router = GoRouter(
    initialLocation: '/',
    routes: [
      GoRoute(path: '/', builder: (context, state) => child),
      GoRoute(
        path: '/orders/:id',
        builder: (context, state) => Scaffold(body: Text('Order Detail #${state.pathParameters['id']}')),
      ),
    ],
  );

  return ProviderScope(
    overrides: [
      sharedPreferencesProvider.overrideWithValue(prefs),
      apiClientProvider.overrideWithValue(_FakeApiClient()),
      ...overrides,
    ],
    child: MaterialApp.router(
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      routerConfig: router,
    ),
  );
}

void main() {
  late SharedPreferences prefs;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    prefs = await SharedPreferences.getInstance();
  });

  group('Phase 13 Acceptance Tests', () {
    testWidgets('1. Customer COD Checkout flow places order successfully', (tester) async {
      tester.view.physicalSize = const Size(800, 1600);
      tester.view.devicePixelRatio = 1.0;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      const cartItem = CartItemModel(
        medicineId: 101,
        medicineName: 'Paracetamol 650mg',
        quantity: 2,
        price: 30.0,
        mrp: 35.0,
        requiresPrescription: false,
        stockAvailable: 50,
      );

      const address = AddressModel(
        id: 1,
        name: 'Rahul Gupta',
        phone: '+919876543210',
        addressLine: 'Flat 402, Greenfield Apts',
        city: 'New Delhi',
        state: 'Delhi',
        pincode: '110001',
        isDefault: true,
      );

      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          overrides: [
            cartControllerProvider.overrideWith((ref) {
              final c = CartController(ref.read(apiClientProvider));
              c.state = const CartState(items: [cartItem]);
              return c;
            }),
            addressControllerProvider.overrideWith((ref) {
              final a = AddressController(ref.read(apiClientProvider));
              a.state = const AddressState(addresses: [address]);
              return a;
            }),
          ],
          child: const CheckoutScreen(),
        ),
      );
      await tester.pumpAndSettle();

      // Verify checkout summary rendered
      expect(find.text('Order Summary'), findsOneWidget);
      expect(find.text('Cash on Delivery (COD)'), findsOneWidget);

      // Verify place order button is enabled and tap
      final placeOrderBtn = find.byKey(const Key('place-order-btn'));
      expect(placeOrderBtn, findsOneWidget);
      await tester.tap(placeOrderBtn);
      await tester.pumpAndSettle();

      // Verify order placement completes and routes to Order Detail
      expect(find.text('Order Detail #7001'), findsOneWidget);
    });

    testWidgets('2. Owner workflow: Add medicine opens dialog, validates & updates inventory', (tester) async {
      const ownerUser = UserModel(
        id: 1,
        name: 'Owner Admin',
        role: 'admin',
        phone: '+919811111111',
      );

      const initialMedicine = MedicineModel(
        id: 1,
        name: 'Dolo 650mg',
        slug: 'dolo-650mg',
        category: 'Tablets',
        price: 30.0,
        mrp: 35.0,
        requiresPrescription: false,
        salts: ['Paracetamol 650mg'],
        manufacturer: 'Micro Labs',
        stock: 100,
      );

      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          overrides: [
            authControllerProvider.overrideWith((ref) {
              final a = AuthController(
                ref.read(secureStorageProvider),
                ref.read(apiClientProvider),
                ref.read(fcmServiceProvider),
              );
              a.state = const AuthState(isAuthenticated: true, user: ownerUser);
              return a;
            }),
            ownerControllerProvider.overrideWith((ref) {
              final o = OwnerController(ref.read(apiClientProvider));
              o.state = const OwnerState(inventory: [initialMedicine]);
              return o;
            }),
          ],
          child: const InventoryScreen(),
        ),
      );
      await tester.pumpAndSettle();

      // Verify initial medicine exists
      expect(find.text('Dolo 650mg'), findsOneWidget);

      // Tap FloatingActionButton to add medicine
      final fab = find.byKey(const Key('add-medicine-fab'));
      expect(fab, findsOneWidget);
      await tester.tap(fab);
      await tester.pumpAndSettle();

      // Dialog is open
      expect(find.text('Add New Medicine & Batch'), findsOneWidget);

      // Enter Medicine Details
      await tester.enterText(find.byKey(const Key('add-med-name-input')), 'Azithromycin 500mg');
      await tester.enterText(find.byKey(const Key('add-med-price-input')), '120.0');
      await tester.enterText(find.byKey(const Key('add-med-mrp-input')), '140.0');
      await tester.enterText(find.byKey(const Key('add-batch-number-input')), 'AZI-8821');

      // Submit Form
      final submitBtn = find.byKey(const Key('confirm-add-medicine-btn'));
      expect(submitBtn, findsOneWidget);
      await tester.tap(submitBtn);
      await tester.pumpAndSettle();

      // Verify new medicine is added to the inventory
      expect(find.text('Azithromycin 500mg'), findsOneWidget);
    });

    testWidgets('3. Deep link: Push notification navigation opens Order Detail', (tester) async {
      await tester.pumpWidget(
        createTestableWidget(
          prefs: prefs,
          child: const OrderDetailScreen(orderId: 7001),
        ),
      );
      await tester.pumpAndSettle();

      // Verify order detail screen renders order details
      expect(find.textContaining('7001'), findsWidgets);
      expect(find.text('Status'), findsOneWidget);
      expect(find.text('Ordered Medicines'), findsOneWidget);
      expect(find.textContaining('Paracetamol 650mg'), findsOneWidget);
    });

    testWidgets('4. AppRouter registers all required customer and owner routes', (tester) async {
      final container = ProviderContainer(
        overrides: [
          sharedPreferencesProvider.overrideWithValue(prefs),
        ],
      );
      final router = container.read(appRouterProvider);

      final routePaths = router.configuration.routes
          .whereType<GoRoute>()
          .map((r) => r.path)
          .toList();

      expect(routePaths, contains('/login'));
      expect(routePaths, contains('/otp'));
      expect(routePaths, contains('/home'));
      expect(routePaths, contains('/catalog'));
      expect(routePaths, contains('/medicine/:id'));
      expect(routePaths, contains('/cart'));
      expect(routePaths, contains('/checkout'));
      expect(routePaths, contains('/orders'));
      expect(routePaths, contains('/orders/:id'));
      expect(routePaths, contains('/prescriptions'));
      expect(routePaths, contains('/notifications'));
      expect(routePaths, contains('/profile'));
      expect(routePaths, contains('/owner'));
      expect(routePaths, contains('/owner/inventory'));
      expect(routePaths, contains('/owner/expiring'));
      expect(routePaths, contains('/owner/racks'));
      expect(routePaths, contains('/owner/orders'));
      expect(routePaths, contains('/owner/rx-review'));
    });
  });
}
