import 'dart:math' as math;
import 'package:flutter/material.dart';
import '../../core/brand/brand_tokens.dart';
import '../../core/theme/shadows_3d.dart';

class Card3D extends StatefulWidget {
  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry? padding;
  final EdgeInsetsGeometry? margin;
  final Color? backgroundColor;
  final double? borderRadius;
  final bool enableTilt;
  final List<BoxShadow>? shadows;
  final Border? border;

  const Card3D({
    super.key,
    required this.child,
    this.onTap,
    this.padding,
    this.margin,
    this.backgroundColor,
    this.borderRadius,
    this.enableTilt = true,
    this.shadows,
    this.border,
  });

  @override
  State<Card3D> createState() => _Card3DState();
}

class _Card3DState extends State<Card3D> {
  double _rotX = 0.0;
  double _rotY = 0.0;
  bool _isHovered = false;

  void _handlePointer(PointerEvent event, Size size) {
    if (!widget.enableTilt) return;

    final center = Offset(size.width / 2, size.height / 2);
    final dx = (event.localPosition.dx - center.dx) / (size.width / 2);
    final dy = (event.localPosition.dy - center.dy) / (size.height / 2);

    final maxRad = (BrandTokens.tiltMaxDeg * math.pi) / 180.0;
    setState(() {
      _rotX = (-dy * maxRad).clamp(-maxRad, maxRad);
      _rotY = (dx * maxRad).clamp(-maxRad, maxRad);
      _isHovered = true;
    });
  }

  void _reset() {
    if (!widget.enableTilt) return;
    setState(() {
      _rotX = 0.0;
      _rotY = 0.0;
      _isHovered = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final reduceMotion = MediaQuery.maybeDisableAnimationsOf(context) ?? false;

    final effectiveRadius = widget.borderRadius ?? BrandTokens.radiusCard;
    final defaultBg = isDark ? BrandTokens.darkSurface : BrandTokens.lightSurface;
    final defaultBorder = Border.all(
      color: isDark ? BrandTokens.darkBorder : BrandTokens.lightBorder,
      width: 1,
    );

    final defaultShadows = _isHovered
        ? Shadows3D.lift
        : (isDark ? Shadows3D.softDark : Shadows3D.soft);

    final card = Container(
      margin: widget.margin,
      decoration: BoxDecoration(
        color: widget.backgroundColor ?? defaultBg,
        borderRadius: BorderRadius.circular(effectiveRadius),
        border: widget.border ?? defaultBorder,
        boxShadow: widget.shadows ?? defaultShadows,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(effectiveRadius),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: widget.onTap,
            borderRadius: BorderRadius.circular(effectiveRadius),
            child: Padding(
              padding: widget.padding ?? const EdgeInsets.all(16.0),
              child: widget.child,
            ),
          ),
        ),
      ),
    );

    if (reduceMotion || !widget.enableTilt) {
      return card;
    }

    return LayoutBuilder(
      builder: (context, constraints) {
        final size = Size(constraints.maxWidth, constraints.maxHeight);

        return Listener(
          onPointerDown: (e) => _handlePointer(e, size),
          onPointerMove: (e) => _handlePointer(e, size),
          onPointerUp: (_) => _reset(),
          onPointerCancel: (_) => _reset(),
          child: TweenAnimationBuilder<Matrix4>(
            duration: const Duration(milliseconds: 150),
            curve: Curves.easeOutCubic,
            tween: Matrix4Tween(
              begin: Matrix4.identity(),
              end: Matrix4.identity()
                ..setEntry(3, 2, 0.001) // perspective
                ..rotateX(_rotX)
                ..rotateY(_rotY),
            ),
            builder: (context, transform, child) {
              return Transform(
                alignment: Alignment.center,
                transform: transform,
                child: child,
              );
            },
            child: card,
          ),
        );
      },
    );
  }
}

