<?php

namespace Larapress\ECommerce\Services\Product;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\Reports\Models\MetricCounter;
use Larapress\ECommerce\IECommerceUser;
use Larapress\Profiles\Models\FormEntry;

class ProductSalesCountRelationship extends Relation
{

    protected $filterType = null;
    protected $isReadyToLoad = false;
    public function __construct(Model $parent, string $filterType)
    {
        parent::__construct(MetricCounter::query(), $parent);
        $this->filterType = $filterType;
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        $this->query->selectRaw('metrics_counters.domain_id, metrics_counters.key, sum(metrics_counters.value) as total_count');
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $suffix = $this->filterType;
        $domains = null;
        /** @var IECommerceUser */
        $user = Auth::user();

        $models = collect($models);
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            if ($user->hasRole(config('larapress.ecommerce.lms.support_role_id'))) {
                $suffix = $suffix . ".$user->id";
            } else if ($user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))) {
                $ownerEntries = $user->getOwenedProductsIds();
                $models = $models->filter(function($model) use($ownerEntries) {
                    return in_array($model->id, $ownerEntries);
                });
            } else {
                $domains = $user->getAffiliateDomainIds();
            }
        }

        $this->query
            ->whereIn('metrics_counters.key', $models->map(function ($model)  use ($suffix) {
                return "product.$model->id.$suffix";
            }));

        if (!is_null($domains)) {
            $this->query->whereIn('domain_id', $domains);
        }

        $this->query->groupBy(['metrics_counters.domain_id', 'metrics_counters.key']);

        $this->isReadyToLoad = true;
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     *
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation(
                $relation,
                $this->related->newCollection()
            );
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string $relation
     *
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        if ($results->isEmpty()) {
            return $models;
        }

        $suffix = $this->filterType;
        /** @var IECommerceUser */
        $user = Auth::user();

        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            if ($user->hasRole(config('larapress.ecommerce.lms.support_role_id'))) {
                $suffix = $suffix . ".$user->id";
            }
        }
        foreach ($models as $model) {
            $resultset = array_values($results->filter(function (Model $contract) use ($model, $suffix) {
                return $contract->key === "product.$model->id.$suffix";
            })->toArray());
            $model->setRelation(
                $relation,
                $this->related->newCollection($resultset)
            );
        }

        return $models;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (!$this->isReadyToLoad) {
            $this->addEagerConstraints([$this->parent]);
        }
        return $this->query->get();
    }
}
