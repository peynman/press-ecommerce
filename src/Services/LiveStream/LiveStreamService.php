<?php

namespace Larapress\ECommerce\Services\LiveStream;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\IBankingService;
use Larapress\Profiles\Repository\Domain\IDomainRepository;
use Larapress\Profiles\IProfileUser;
use Illuminate\Support\Str;

class LiveStreamService implements ILiveStreamService
{
    /**
     * Undocumented function
     *
     * @param Request $request
     * @return boolean
     */
    public function canStartLiveStream(Request $request) {
        // nginx passes stream name in request name
        return !is_null($this->getLiveStreamProduct($request->get('name', null)));
    }


    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function liveStreamStarted(Request $request) {

    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function liveStreamEnded(Request $request) {

    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return boolean
     */
    public function canWatchLiveStream(Request $request)
    {
        $upstreamNameParts = explode('/', $request->headers->get('X-Original-URI'));
        $upstreamName = $upstreamNameParts[count($upstreamNameParts)-1];
        if (Str::endsWith($upstreamName, '.m3u8')) {
            $upstreamName = substr($upstreamName, 0, strlen($upstreamName) - strlen('.m3u8'));
        }

        $product = $this->getLiveStreamProduct($upstreamName);

        if (is_null($product)) {
            return false;
        }

        if ($product->price() === 0 && is_null($product->parent_id)) {
            return true;
        }

        /** @var IProfileUser|ICRUDUser */
        $user = Auth::user();
        /** @var IBankingService */
        $repo = app(IBankingService::class);
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);

        if (!is_null($user)) {
            $freeForRoles = array_merge(
                config('larapress.profiles.security.roles.super-role'),
                config('larapress.profiles.security.roles.affiliate')
            );
            if ($user->hasRole($freeForRoles))
            {
                return true;
            }
        }

        return $repo->isProductOnPurchasedList($user, $domainRepo->getRequestDomain($request), $product);
    }


    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Product|null
     */
    protected function getLiveStreamProduct($upstreamName)
    {
        if (is_null($upstreamName)) {
            return null;
        }

        $cacheName = 'larapress.ecommerce.livestream.' . $upstreamName;
        $product = Cache::get($cacheName, null);
        if (is_null($product)) {
            $product = Product::query()
                ->whereHas('types', function (Builder $q) {
                    $q->where('name', 'livestream');
                })
                ->where('data->types->livestream->key', $upstreamName)
                ->first();
            if (!is_null($product)) {
                Cache::tags(['product:'.$product->id, 'product'])->put($cacheName, $product, Carbon::now()->addDay(1));
            }
        }

        return $product;
    }
}
