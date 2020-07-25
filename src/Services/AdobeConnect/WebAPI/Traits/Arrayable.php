<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Traits;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StringCaseTransform as SCT;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\ValueTransform as VT;

/**
 * Override the methods to turn into a valid ArrayableInterface
 */
trait Arrayable
{
    /**
     * Retrieves all not null attributes in an associative array
     *
     * The keys in hash style: Ex: is-member
     * The values as string
     *
     * @return string[]
     */
    public function toArray()
    {
        $values = [];

        foreach ($this as $prop => $value) {
            if (!isset($value)) {
                continue;
            }
            $values[SCT::toHyphen($prop)] = VT::toString($value);
        }
        return $values;
    }
}
