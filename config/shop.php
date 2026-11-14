<?php

return [

    /*
    | Prefix of the order number: `WEB-000123`. The number is the `ref_no` of the Jubelio sales order and the
    | `partnerReferenceNo` of the DANA order, and DANA refuses a number it has already seen with other data
    | (4045418). Two installs that share one DANA or Jubelio account (the live site and a laptop) count their
    | own orders, so each needs its own prefix: leave `WEB` on the server, use e.g. `DEV` on a laptop.
    */
    'order_prefix' => env('ORDER_NUMBER_PREFIX', 'WEB'),

    /*
    | Slugs of Jubelio categories kept off the storefront. Their products (and the category itself, which then
    | has none) are hidden from the list, detail pages, category list, and sitemap; the sync still copies them,
    | and the admin product list still shows them. Jasa Service is sold in the shop, not online.
    */
    'hidden_categories' => ['jasa-service'],

    /*
    | The store's WhatsApp number (country code, digits only), the same one the product pages use. "Hubungi admin" on
    | an unpaid order opens a chat with it, with the order already written in the message.
    */
    'whatsapp' => env('SHOP_WHATSAPP_NUMBER', '6282227222971'),

    /*
    | When the tracking number is promised (WIB), on the finish page, the payment mail, and the invoice: an order paid
    | before `tracking_cutoff` gets it by `tracking_deadline` the same day, a later one by `tracking_deadline` the next.
    | A deadline on a day the warehouse does not ship (ISO weekday, 7 = Sunday) moves to the next day it does, so
    | Saturday after the cutoff and all of Sunday get Monday.
    */
    'tracking_cutoff' => '15:00',
    'tracking_deadline' => '16:00',
    'tracking_closed_days' => [7],

];
