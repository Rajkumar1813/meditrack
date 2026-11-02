import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../features/auth/controllers/auth_controller.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/auth/presentation/otp_verify_screen.dart';
import '../features/home/home_screen.dart';
import '../features/owner/owner_screen.dart';

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
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
      ),
      GoRoute(
        path: '/otp',
        builder: (context, state) => const OtpVerifyScreen(),
      ),
      GoRoute(
        path: '/home',
        builder: (context, state) => const HomeScreen(),
      ),
      GoRoute(
        path: '/owner',
        builder: (context, state) => const OwnerScreen(),
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

