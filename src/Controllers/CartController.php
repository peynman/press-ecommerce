<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Response;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\ICartService;

class CartController extends Controller
{
    public function __construct(public ICartService $service) {}

    /**
     * Undocumented function
     *
     * @param int $cartId
     * @return Response
     */
    public function markCartPosted($cartId) {
        return $this->service->markCartPosted(Cart::find($cartId));
    }
}
