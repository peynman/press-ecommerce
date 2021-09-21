<?php

namespace Larapress\ECommerce\Services\Product\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Larapress\ECommerce\Models\Cart;

class MetricCounterGroupCartRelation extends Relation
{
    protected $filterType = null;
    protected $isReadyToLoad = false;
    protected $groupBy = [];

    public function __construct(Model $parent)
    {
        parent::__construct(Cart::query(), $parent);
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
        $models = collect($models)->pluck('group')->map(function ($group) {
            return substr($group, 5);
        });
        $this->query->whereIn('id', $models);
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
                null,
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

        foreach ($models as $model) {
            $resultset = $results->first(function (Model $contract) use ($model) {
                return $contract->id == substr($model->group, 5);
            });
            $model->setRelation(
                $relation,
                $resultset
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
