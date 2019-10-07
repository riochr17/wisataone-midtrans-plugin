# Wisataone - Midtrans Payment Plugin

Midtrans interface payment plugin for Wisataone.id

## Installation

Follow two section instructions below:

### 1. Plugin Instalation

Login wp-admin > Plugins > Add New > Upload Plugin > Choose wisataone-midtrans-\*-plugin.zip > Install Now > Activate

### 2. Tourmaster Plugin Modification

### 2.1. File tourmaster/include/payment-util.php

1. pada berkas plugin "tourmaster/include/payment-util.php" cari fungsi ```tourmaster_payment_method()```
   tambahkan baris berikut

```php
$midtrans_enable = in_array('midtrans', $payment_method);
```

tepat setelah baris

```php
$paypal_enable = in_array('paypal', $payment_method);
```

2. pada berkas yang sama, ubah baris berikut

```php
if( $admin_approval && ($paypal_enable...
```

menjadi 

```php
if( $admin_approval && ($midtrans_enable || $paypal_enable...
```

3. masih pada berkas yang sama tambahkan blok baris berikut dibawah perubahan nomor 2,

```php
if( $midtrans_enable ){
    $midtrans_button_atts = apply_filters('tourmaster_midtrans_button_atts', array());
    $ret .= '<div class="tourmaster-online-payment-method tourmaster-payment-paypal" >';
    $ret .= '<img style="object-fit: contain; background: rgba(255, 255, 255, .7); border-radius: 3px; padding: 4px;" src="https://algorit.ma/wp-content/uploads/2017/08/midtrans.png" alt="midtrans" width="170" height="76" ';
    if( !empty($midtrans_button_atts['method']) && $midtrans_button_atts['method'] == 'ajax' ){
        $ret .= 'data-method="ajax" data-action="tourmaster_payment_selected" data-ajax="' . esc_url(TOURMASTER_AJAX_URL) . '" ';
        if( !empty($midtrans_button_atts['type']) ){
            $ret .= 'data-action-type="' . esc_attr($midtrans_button_atts['type']) . '" ';
        } 
    }
    $ret .= ' />';
    $ret .= '</div>';
}
```

tepat setelah baris

```php
$ret .= '<div class="tourmaster-payment-gateway clearfix" >';
```

### 2.2 File tourmaster/include/plugin-option.php

1. pada berkas plugin "tourmaster/include/plugin-option.php" cari baris berikut

```php
'payment-method' => array(
```

lalu tambahkan 

```php
'midtrans' => esc_html__('Midtrans', 'tourmaster'), 
```

pada bagian options.

## Usage

1. Select Midtrans payment method on Tourmaster Setting page.

```
Tourmaster > Payment > Choose Midtrans on section "Payment Method"
```

2. Setup your Midtrans credential key on 

```
Tourmaster > Payment > Midtrans
```

## Contributing

1. Fork it!
2. Create your feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request :D

## History


| Date | Description |
|-------|-----|
|Sep 21st, 2019 | Init Project |
|Oct 7th, 2019 | Final version 1.0 |

## Credits

[Rio Chandra Rajagukguk](https://github.com/riochr17)

## License

-