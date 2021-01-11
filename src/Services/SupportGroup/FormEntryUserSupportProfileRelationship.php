<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Larapress\Profiles\Models\FormEntry;

class FormEntryUserSupportProfileRelationship extends Relation {

    protected $isReadyToLoad = false;
    public function __construct(Model $parent)
    {
        parent::__construct(FormEntry::query(), $parent);
    }

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints() {
        $this->query
            ->select(DB::raw('profile_entries.*, form_entries.updated_at as registrated_at, form_entries.user_id as user_id, profile_entries.user_id as support_user_id'))
            ->join('users', function ($join) {
                $join->on('tags', '=', DB::raw('CONCAT(\'support-group-\', users.id)'));
            })
            ->join('form_entries as profile_entries', function ($join) {
                $join->on('users.id', '=', 'profile_entries.user_id');
                $join->on('profile_entries.form_id', '=', DB::raw(config('larapress.ecommerce.lms.support_profile_form_id')));
            })
            ;
     }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models) {
        $this->query
            ->whereIn('form_entries.user_id', collect($models)->pluck('id'))
            ->where('form_entries.form_id', DB::raw(config('larapress.ecommerce.lms.support_group_default_form_id')))
        ;
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
    public function initRelation(array $models, $relation) {
        foreach ($models as $model) {
            $model->setRelation(
                $relation,
                null
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
    public function match(array $models, Collection $results, $relation) {
        if ($results->isEmpty()) {
            return $models;
        }

        foreach ($models as $model) {
            $resultset = $results->first(function (Model $contract) use ($model) {
                return $contract->user_id === $model->id;
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
    public function getResults() {
        if (!$this->isReadyToLoad) {
            $this->addEagerConstraints([$this->parent]);
        }
        return $this->query->first();
     }

}
