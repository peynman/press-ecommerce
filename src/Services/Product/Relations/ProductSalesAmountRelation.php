<?php

namespace Larapress\ECommerce\Services\Product\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\Reports\Models\MetricCounter;
use Larapress\ECommerce\IECommerceUser;
use Larapress\Profiles\Models\FormEntry;

class ProductSalesAmountRelation extends Relation
{

    protected $filterType = null;
    protected $isReadyToLoad = false;
    protected $extraSuffix = "";
    protected $groupBy = [];
    public function __construct(Model $parent, $filterType = null, $extraSuffix = "", $groupBy = ['metrics_counters.domain_id', 'metrics_counters.key'])
    {
        parent::__construct(MetricCounter::query(), $parent);
        $this->filterType = $filterType;
        $this->extraSuffix = $extraSuffix;
        $this->groupBy = $groupBy;
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
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
        $this->query->selectRaw(implode(",", array_merge(['sum(metrics_counters.value) as total_amount'], $this->groupBy)));

        $suffix = ".amount";
        $domains = null;
        /** @var IECommerceUser */
        $user = Auth::user();

        if (isset($models[0]['id'])) {
            $models = collect($models)->pluck('id');
        } else {
            $models = collect($models);
        }

        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            if ($user->hasRole(config('larapress.lcms.support_role_id'))) {
                $suffix = $suffix . ".$user->id";
            } elseif ($user->hasRole(config('larapress.lcms.owner_role_id'))) {
                $ownerEntries = $user->getOwenedProductsIds();
                $models = $models->filter(function ($model) use ($ownerEntries) {
                    return in_array($model, $ownerEntries);
                });
            } else {
                $domains = $user->getAffiliateDomainIds();
            }
        }

        $ids = $models->join("|");

        $fullSuffix = str_replace(".", "\.", $this->extraSuffix . $suffix);
        $this->query->where('metrics_counters.key', 'RLIKE', "^product\.($ids)\.sales\.".(is_null($this->filterType) ? "(1|2)": $this->filterType)."$fullSuffix$");

        if (!is_null($domains)) {
            $this->query->whereIn('domain_id', $domains);
        }

        if (count($this->groupBy) > 0) {
            $this->query->groupBy($this->groupBy);
        }

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

        $suffix = ".amount";
        /** @var IECommerceUser */
        $user = Auth::user();

        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            if ($user->hasRole(config('larapress.lcms.support_role_id'))) {
                $suffix = $suffix . ".$user->id";
            }
        }

        foreach ($models as $model) {
            $resultset = array_values($results->filter(function (Model $contract) use ($model, $suffix) {
                return in_array($contract->key, [
                    "product.$model->id.sales." . WalletTransaction::TYPE_REAL_MONEY. $this->extraSuffix . $suffix,
                    "product.$model->id.sales." . WalletTransaction::TYPE_VIRTUAL_MONEY . $this->extraSuffix . $suffix,
                ]);
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
