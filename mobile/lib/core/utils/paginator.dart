import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

@immutable
class PaginatorState<T> {
  final List<T> items;
  final String? nextCursor;
  final bool hasMore;
  final bool isLoading;
  final bool isRefreshing;
  final String? error;

  const PaginatorState({
    this.items = const [],
    this.nextCursor,
    this.hasMore = true,
    this.isLoading = false,
    this.isRefreshing = false,
    this.error,
  });

  bool get isEmpty => items.isEmpty;

  PaginatorState<T> copyWith({
    List<T>? items,
    String? nextCursor,
    bool? hasMore,
    bool? isLoading,
    bool? isRefreshing,
    String? error,
    bool clearError = false,
  }) {
    return PaginatorState<T>(
      items: items ?? this.items,
      nextCursor: nextCursor ?? this.nextCursor,
      hasMore: hasMore ?? this.hasMore,
      isLoading: isLoading ?? this.isLoading,
      isRefreshing: isRefreshing ?? this.isRefreshing,
      error: clearError ? null : (error ?? this.error),
    );
  }
}

typedef PageFetchFn<T> = Future<({List<T> items, String? nextCursor, bool hasMore})> Function(String? cursor);

class PaginatorNotifier<T> extends StateNotifier<PaginatorState<T>> {
  final PageFetchFn<T> _fetchPage;

  PaginatorNotifier(this._fetchPage) : super(PaginatorState<T>()) {
    refresh();
  }

  Future<void> refresh() async {
    state = state.copyWith(isRefreshing: true, clearError: true);
    try {
      final res = await _fetchPage(null);
      state = PaginatorState<T>(
        items: res.items,
        nextCursor: res.nextCursor,
        hasMore: res.hasMore,
        isLoading: false,
        isRefreshing: false,
      );
    } catch (e) {
      state = state.copyWith(
        isRefreshing: false,
        error: e.toString(),
      );
    }
  }

  Future<void> loadMore() async {
    if (state.isLoading || state.isRefreshing || !state.hasMore) return;

    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final res = await _fetchPage(state.nextCursor);
      state = state.copyWith(
        items: [...state.items, ...res.items],
        nextCursor: res.nextCursor,
        hasMore: res.hasMore,
        isLoading: false,
      );
    } catch (e) {
      state = state.copyWith(
        isLoading: false,
        error: e.toString(),
      );
    }
  }
}

class PaginatorView<T> extends StatefulWidget {
  final PaginatorState<T> state;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final Widget Function(BuildContext context, T item, int index) itemBuilder;
  final Widget? emptyWidget;
  final Widget? loadingWidget;
  final Widget? errorWidget;
  final EdgeInsetsGeometry? padding;

  const PaginatorView({
    super.key,
    required this.state,
    required this.onRefresh,
    required this.onLoadMore,
    required this.itemBuilder,
    this.emptyWidget,
    this.loadingWidget,
    this.errorWidget,
    this.padding,
  });

  @override
  State<PaginatorView<T>> createState() => _PaginatorViewState<T>();
}

class _PaginatorViewState<T> extends State<PaginatorView<T>> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      widget.onLoadMore();
    }
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.state.isEmpty) {
      if (widget.state.isLoading || widget.state.isRefreshing) {
        return widget.loadingWidget ??
            const Center(child: CircularProgressIndicator.adaptive());
      }

      if (widget.state.error != null) {
        return widget.errorWidget ??
            Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.error_outline_rounded, size: 48, color: Colors.red),
                  const SizedBox(height: 12),
                  Text(widget.state.error!),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: widget.onRefresh,
                    child: const Text('Retry'),
                  ),
                ],
              ),
            );
      }

      return widget.emptyWidget ??
          const Center(child: Text('No items found.'));
    }

    return RefreshIndicator.adaptive(
      onRefresh: widget.onRefresh,
      child: ListView.builder(
        controller: _scrollController,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: widget.padding ?? const EdgeInsets.all(16),
        itemCount: widget.state.items.length + (widget.state.hasMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (index < widget.state.items.length) {
            return widget.itemBuilder(context, widget.state.items[index], index);
          }
          return const Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: Center(child: CircularProgressIndicator.adaptive()),
          );
        },
      ),
    );
  }
}

