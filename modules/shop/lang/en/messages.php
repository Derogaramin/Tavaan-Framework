<?php

declare(strict_types=1);

// Shop module — error/status messages (en)

return [
    'forbidden' => 'You are not allowed to access this section.',
    'login_required' => 'Please log in to continue.',
    'product_not_found' => 'Product not found.',
    'product_unavailable' => 'Product not found or inactive.',
    'variant_invalid' => 'Invalid variant selection.',
    'order_not_found' => 'Order not found.',
    'order_forbidden' => 'This order does not belong to you.',
    'order_not_cancelable' => 'Order cannot be canceled in this status.',
    'order_not_payable' => 'Order is not payable.',
    'order_failed' => 'Placing the order failed; please retry.',
    'cart_empty' => 'Cart is empty.',
    'cart_item_missing' => 'Cart item not found.',
    'address_incomplete' => 'Shipping address is incomplete.',
    'out_of_stock' => 'Insufficient stock.',
    'payment_failed' => 'Payment not verified.',
    'slug_taken' => 'This slug is already taken.',
    'invalid_status' => 'Invalid status.',
    'reviews_disabled' => 'Reviews are disabled.',
    'reviews_closed' => 'Reviews are closed for this product.',
    'reviews_guest_forbidden' => 'Log in to write a review.',
    'review_not_found' => 'Review not found.',
    'coupon_not_found' => 'Coupon not found.',
    'category_not_found' => 'Category not found.',

    'coupon_empty' => 'Enter a coupon code.',
    'coupon_invalid' => 'Invalid coupon code.',
    'coupon_expired' => 'Coupon expired.',
    'coupon_exhausted' => 'Coupon usage limit reached.',
    'coupon_min_cart' => 'Cart subtotal below coupon minimum.',

    'notify_paid_subject' => 'Order :number paid',
    'notify_paid_line' => 'Payment for order :number succeeded and is being processed.',
    'notify_shipped_subject' => 'Order :number shipped',
    'notify_shipped_line' => 'Order :number shipped. Tracking: :tracking',
    'notify_canceled_subject' => 'Order :number canceled',
    'notify_canceled_line' => 'Order :number was canceled. Paid amounts will be refunded.',
    'notify_cta' => 'View order',
];
