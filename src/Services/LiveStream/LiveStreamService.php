<?php

namespace Larapress\ECommerce\Services\LiveStream;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Banking\IBankingService;
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
        $streamUrl = $request->get('tcurl', null);
        if (is_null($streamUrl)) {
            return false;
        }
        $url_components = parse_url($streamUrl);
        $params = [];
        if (isset($url_components['query'])) {
            parse_str($url_components['query'], $params);
        }
        // nginx passes stream name in request name
        $product = $this->getLiveStreamProduct($request->get('name'));
        $secret = isset($params['secret']) ? $params['secret'] : null;
        $product_secret = $product->data['types']['livestream']['secret'];
        return $product_secret == $secret;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function liveStreamStarted(Request $request) {
        $product = $this->getLiveStreamProduct($request->get('name', null));
        $data = $product->data;
        $data['types']['livestream']['status'] = 'live';
        $data['types']['livestream']['broadcast_start_at'] = Carbon::now();
        $product->update([
            'data' => $data,
        ]);
        if ($product->parent) {
            $data = $product->parent->data;
            $data['live-streams'] = 1;
            $product->parent->update([
                'data' => $data,
            ]);
        }

        return response('ok');
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function liveStreamEnded(Request $request) {
        $product = $this->getLiveStreamProduct($request->get('name', null));
        $data = $product->data;
        $data['types']['livestream']['status'] = 'ended';
        $data['types']['livestream']['broadcast_end_at'] = Carbon::now();
        $product->update([
            'data' => $data,
        ]);
        if ($product->parent) {
            $data = $product->parent->data;
            $data['live-streams'] = 0;
            $product->parent->update([
                'data' => $data,
            ]);
        }

        return response('ok');
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
            // inner variant files with format $name_xxxpxxxkbs
            if ($upstreamName === 'index') {
                $upstreamName = $upstreamNameParts[count($upstreamNameParts)-2];
                $upstreamName = substr($upstreamName, 0, strrpos($upstreamName, '_'));
            }
        }

        $product = $this->getLiveStreamProduct($upstreamName);


        if (is_null($product)) {
            return false;
        }

        if ($product->isFree()) {
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
