<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Base\BaseCRUDProvider;
use Larapress\CRUD\Base\ICRUDProvider;
use Larapress\CRUD\Base\IPermissionsMetadata;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

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
        'uploader_id' => 'equals:uploader_id',
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
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
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
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            return $object->uploader_id === $user->id;
        }

        return true;
    }
}
