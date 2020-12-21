<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;
use Larapress\Profiles\Models\FormEntry;
use Larapress\ECommerce\IECommerceUser;

class FileUploadCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.file_uploads.name';
    public $verbs = [
        self::VIEW,
        self::DELETE,
        'upload',
    ];
    public $model = FileUpload::class;
    public $searchColumns = [
        'title',
        'filename',
    ];
    public $validSortColumns = [
        'id',
        'uploader_id',
    ];
    public $validRelations = [
        'uploader',
    ];
    public $filterFields = [
        'created_from' => 'after:created_at',
        'created_to' => 'before:created_at',
        'uploader_id' => 'equals:uploader_id',
        'mime' => 'in:mime',
    ];

    /**
     * @param Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function onBeforeQuery($query)
    {
        /** @var IProfileUser|ICRUDUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->where('uploader_id', $user->id);
        }

        return $query;
    }

    /**
     * @param Domain $object
     *
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            if (
                !is_null(config('larapress.ecommerce.lms.teacher_support_form_id')) &&
                $user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))
            ) {

                return in_array($object->id, $user->getOwenedProductsIds());
            } else if (
                !is_null(config('larapress.ecommerce.lms.support_group_default_form_id')) &&
                $user->hasRole(config('larapress.ecommerce.lms.support_role_id'))
            ) {
                return FormEntry::query()
                ->where('user_id', $object->uploader_id)
                ->where('form_id', config('larapress.ecommerce.lms.support_group_default_form_id'))
                ->where('tags', 'support-group-' . $user->id)
                ->count() > 0;
            } else {
                return $object->uploader_id === $user->id;
            }
        }

        return true;
    }
}
