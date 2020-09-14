<?php

namespace Larapress\ECommerce\Services\Azmoon;

use Illuminate\Http\Request;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\Profiles\IProfileUser;

interface IAzmoonService {
    /**
     * Undocumented function
     *
     * @param Product|int $product
     * @return array
     */
    public function getAzmoonDetails($product);

    /**
     * Undocumented function
     *
     * @param Product|int $product
     * @param int $index
     * @param boolean $answer
	 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function streamAzmoonFileAtIndex(Request $request, $product, $index, $answer = false);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param int $product
     * @return FormEntry
     */
    public function acceptAzmoonResultForUser(Request $request, IProfileUser $user, $productId);

    /**
     * Undocumented function
     *
     * @param int $userId
     * @param int $productId
     * @return void
     */
    public function getAzmoonResultForUser($userId, $productId);

    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return array
     */
    public function getAzmoonJSONFromFile(FileUpload $upload);
}
