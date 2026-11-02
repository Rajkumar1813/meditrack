import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../features/auth/controllers/auth_controller.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/otp_verify_screen.dart';
import '../features/home/home_screen.dart';
import '../features/catalog/presentation/catalog_screen.dart';
import '../features/catalog/presentation/medicine_detail_screen.dart';
import '../features/cart/presentation/cart_screen.dart';
import '../features/checkout/presentation/checkout_screen.dart';
import '../features/orders/presentation/orders_screen.dart';
import '../features/orders/presentation/order_detail_screen.dart';
import '../features/prescription/presentation/prescriptions_screen.dart';
import '../features/notifications/presentation/notifications_screen.dart';
import '../features/profile/presentation/profile_screen.dart';
import '../features/owner/owner_screen.dart';
import '../features/owner/presentation/inventory_screen.dart';
import '../features/owner/presentation/expiring_screen.dart';
import '../features/owner/presentation/racks_screen.dart';
import '../features/owner/presentation/owner_orders_screen.dart';
import '../features/owner/presentation/rx_review_screen.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  return GoRouter(
    initialLocation: '/login',
    refreshListenable: _AuthListenable(ref),
    redirect: (BuildContext context, GoRouterState state) {
      final auth = ref.read(authControllerProvider);
      final isAuth = auth.isAuthenticated;
      final user = auth.user;

      final isLoggingIn = state.matchedLocation == '/login' || state.matchedLocation == '/otp';

      if (!isAuth) {
        return isLoggingIn ? null : '/login';
      }

      // If authenticated and trying to access login/otp, redirect based on role
      if (isLoggingIn) {
        if (user != null && user.isOwnerOrStaff) {
          return '/owner';
        }
        return '/home';
      }

      // If customer attempts to access owner route, redirect to customer home
      if (state.matchedLocation.startsWith('/owner') && (user == null || !user.isOwnerOrStaff)) {
        return '/home';
      }

      return null;
    },
    routes: [
      // Auth routes
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/otp',
        builder: (context, state) => const OtpVerifyScreen(),
      ),

      // Customer routes
      GoRoute(
        path: '/home',
        builder: (context, state) => const HomeScreen(),
      ),
      GoRoute(
        path: '/catalog',
        builder: (context, state) {
          final query = state.uri.queryParameters['q'];
          return CatalogScreen(initialQuery: query);
        },
      ),
      GoRoute(
        path: '/medicine/:id',
        builder: (context, state) {
          final idStr = state.pathParameters['id'] ?? '0';
          final id = int.tryParse(idStr) ?? 0;
          return MedicineDetailScreen(medicineId: id);
        },
      ),
      GoRoute(
        path: '/cart',
        builder: (context, state) => const CartScreen(),
      ),
      GoRoute(
        path: '/checkout',
        builder: (context, state) => const CheckoutScreen(),
      ),
      GoRoute(
        path: '/orders',
        builder: (context, state) => const OrdersScreen(),
      ),
      GoRoute(
        path: '/orders/:id',
        builder: (context, state) {
          final idStr = state.pathParameters['id'] ?? '0';
          final id = int.tryParse(idStr) ?? 0;
          return OrderDetailScreen(orderId: id);
        },
      ),
      GoRoute(
        path: '/prescriptions',
        builder: (context, state) => const PrescriptionsScreen(),
      ),
      GoRoute(
        path: '/notifications',
        builder: (context, state) => const NotificationsScreen(),
      ),
      GoRoute(
        path: '/profile',
        builder: (context, state) => const ProfileScreen(),
      ),

      // Owner / Admin / Pharmacist routes
      GoRoute(
        path: '/owner',
        builder: (context, state) => const OwnerScreen(),
      ),
      GoRoute(
        path: '/owner/inventory',
        builder: (context, state) => const InventoryScreen(),
      ),
      GoRoute(
        path: '/owner/expiring',
        builder: (context, state) => const ExpiringScreen(),
      ),
      GoRoute(
        path: '/owner/racks',
        builder: (context, state) => const RacksScreen(),
      ),
      GoRoute(
        path: '/owner/orders',
        builder: (context, state) => const OwnerOrdersScreen(),
      ),
      GoRoute(
        path: '/owner/rx-review',
        builder: (context, state) => const RxReviewScreen(),
      ),
    ],
  );
});

class _AuthListenable extends ChangeNotifier {
  _AuthListenable(Ref ref) {
    ref.listen(authControllerProvider, (previous, next) {
      notifyListeners();
    });
  }
}
