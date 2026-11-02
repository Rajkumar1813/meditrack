// ignore_for_file: deprecated_member_use

import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/brand/brand_controller.dart';
import '../../../shared/widgets/card_3d.dart';
import '../../../shared/widgets/primary_button.dart';
import '../../cart/controllers/cart_controller.dart';
import '../../orders/controllers/orders_controller.dart';
import '../../profile/controllers/address_controller.dart';
import '../../prescription/controllers/prescription_controller.dart';

class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen({super.key});

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  int? _selectedAddressId;
  String _paymentMethod = 'cod'; // 'cod' or 'razorpay'
  File? _pickedRxFile;
  int? _uploadedRxId;
  bool _isUploadingRx = false;

  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final addrState = ref.read(addressControllerProvider);
      if (addrState.defaultAddress != null) {
        setState(() => _selectedAddressId = addrState.defaultAddress!.id);
      }
    });
  }

  Future<void> _pickPrescription(ImageSource source) async {
    try {
      final picked = await _picker.pickImage(source: source, imageQuality: 80);
      if (picked != null) {
        setState(() {
          _pickedRxFile = File(picked.path);
          _isUploadingRx = true;
        });

        // Upload to backend
        final rxCtrl = ref.read(prescriptionControllerProvider.notifier);
        final uploaded = await rxCtrl.uploadPrescription(_pickedRxFile!);

        if (mounted) {
          setState(() {
            _uploadedRxId = uploaded?.id;
            _isUploadingRx = false;
          });
        }
      }
    } catch (_) {
      setState(() => _isUploadingRx = false);
    }
  }

  void _showAddAddressDialog() {
    final nameCtrl = TextEditingController();
    final phoneCtrl = TextEditingController();
    final lineCtrl = TextEditingController();
    final cityCtrl = TextEditingController(text: 'New Delhi');
    final stateCtrl = TextEditingController(text: 'Delhi');
    final pinCtrl = TextEditingController(text: '110001');

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Add Delivery Address'),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name')),
              const SizedBox(height: 10),
              TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Mobile Number')),
              const SizedBox(height: 10),
              TextField(controller: lineCtrl, decoration: const InputDecoration(labelText: 'House / Street / Area')),
              const SizedBox(height: 10),
              TextField(controller: cityCtrl, decoration: const InputDecoration(labelText: 'City')),
              const SizedBox(height: 10),
              TextField(controller: pinCtrl, decoration: const InputDecoration(labelText: 'Pincode')),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              if (nameCtrl.text.isNotEmpty && lineCtrl.text.isNotEmpty) {
                final addr = await ref.read(addressControllerProvider.notifier).addAddress(
                      name: nameCtrl.text,
                      phone: phoneCtrl.text,
                      addressLine: lineCtrl.text,
                      city: cityCtrl.text,
                      stateName: stateCtrl.text,
                      pincode: pinCtrl.text,
                      isDefault: true,
                    );
                if (addr != null && mounted) {
                  setState(() => _selectedAddressId = addr.id);
                  if (ctx.mounted) {
                    Navigator.pop(ctx);
                  }
                }
              }
            },
            child: const Text('Save Address'),
          ),
        ],
      ),
    );
  }

  void _handlePlaceOrder() async {
    final addrId = _selectedAddressId ?? 1;
    final ordersCtrl = ref.read(ordersControllerProvider.notifier);

    final order = await ordersCtrl.createOrder(
      addressId: addrId,
      paymentMethod: _paymentMethod,
      prescriptionId: _uploadedRxId,
    );

    if (order != null && mounted) {
      context.go('/orders/${order.id}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final brand = ref.watch(brandControllerProvider);
    final cartState = ref.watch(cartControllerProvider);
    final addrState = ref.watch(addressControllerProvider);
    final ordersState = ref.watch(ordersControllerProvider);
    final theme = Theme.of(context);

    final effectiveAddressId = _selectedAddressId ?? (addrState.addresses.isNotEmpty ? addrState.addresses.first.id : 1);

    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // 1. Delivery Address Card
          Card3D(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Delivery Address', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                    TextButton.icon(
                      icon: const Icon(Icons.add, size: 16),
                      label: const Text('Add New'),
                      onPressed: _showAddAddressDialog,
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                if (addrState.addresses.isEmpty) ...[
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Radio(value: 1, groupValue: 1, onChanged: null),
                    title: const Text('Default Address (Home)'),
                    subtitle: const Text('123 Health Ave, Connaught Place, New Delhi - 110001'),
                  ),
                ] else ...[
                  ...addrState.addresses.map((a) => RadioListTile<int>(
                        value: a.id,
                        groupValue: effectiveAddressId,
                        contentPadding: EdgeInsets.zero,
                        title: Text('${a.name} (${a.phone})', style: const TextStyle(fontWeight: FontWeight.w600)),
                        subtitle: Text(a.fullAddress),
                        onChanged: (val) => setState(() => _selectedAddressId = val),
                      )),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),

          // 2. Prescription Upload (Mandatory if cart has Rx items)
          if (cartState.requiresPrescription) ...[
            Card3D(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.description_outlined, color: Color(0xFFDC2626)),
                      const SizedBox(width: 8),
                      Text('Prescription (Rx) Required', style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  const Text('Please upload a clear photo or PDF of your doctor prescription.'),
                  const SizedBox(height: 14),
                  if (_pickedRxFile != null) ...[
                    Row(
                      children: [
                        const Icon(Icons.check_circle_rounded, color: Color(0xFF059669)),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _isUploadingRx ? 'Uploading prescription...' : 'Prescription attached successfully',
                            style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFF059669)),
                          ),
                        ),
                        IconButton(
                          icon: const Icon(Icons.delete_outline),
                          onPressed: () => setState(() {
                            _pickedRxFile = null;
                            _uploadedRxId = null;
                          }),
                        ),
                      ],
                    ),
                  ] else ...[
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            icon: const Icon(Icons.camera_alt_outlined),
                            label: const Text('Take Photo'),
                            onPressed: () => _pickPrescription(ImageSource.camera),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: OutlinedButton.icon(
                            icon: const Icon(Icons.photo_library_outlined),
                            label: const Text('Gallery'),
                            onPressed: () => _pickPrescription(ImageSource.gallery),
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
          ],

          // 3. Payment Method Selection
          Card3D(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                const SizedBox(height: 12),
                RadioListTile<String>(
                  value: 'cod',
                  groupValue: _paymentMethod,
                  title: const Text('Cash on Delivery (COD)'),
                  subtitle: const Text('Pay with cash or UPI upon delivery at your doorstep'),
                  secondary: const Icon(Icons.payments_outlined, color: Color(0xFF059669)),
                  onChanged: (val) => setState(() => _paymentMethod = val!),
                ),
                RadioListTile<String>(
                  value: 'razorpay',
                  groupValue: _paymentMethod,
                  title: const Text('Online Payment (Razorpay)'),
                  subtitle: const Text('UPI (GPay/PhonePe), Credit/Debit Card, NetBanking'),
                  secondary: const Icon(Icons.credit_card_outlined, color: Color(0xFF2563EB)),
                  onChanged: (val) => setState(() => _paymentMethod = val!),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // 4. Order Summary
          Card3D(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Order Summary', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                const SizedBox(height: 12),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total Items'),
                    Text('${cartState.totalCount}'),
                  ],
                ),
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Subtotal'),
                    Text('${brand.currencySymbol}${cartState.subtotal.toStringAsFixed(2)}'),
                  ],
                ),
                const SizedBox(height: 6),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Delivery Fee'),
                    Text(cartState.deliveryFee == 0 ? 'FREE' : '${brand.currencySymbol}${cartState.deliveryFee.toStringAsFixed(2)}'),
                  ],
                ),
                const Divider(height: 20),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Amount Payable', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                    Text(
                      '${brand.currencySymbol}${cartState.total.toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: Color(0xFF059669)),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 32),

          PrimaryButton(
            key: const Key('place-order-btn'),
            label: ordersState.isLoading
                ? 'Placing Order...'
                : 'Confirm & Place Order • ${brand.currencySymbol}${cartState.total.toStringAsFixed(2)}',
            isLoading: ordersState.isLoading,
            onPressed: ordersState.isLoading ? null : _handlePlaceOrder,
          ),
        ],
      ),
    );
  }
}
