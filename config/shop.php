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

];
