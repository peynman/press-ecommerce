<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter;

use InvalidArgumentException;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Connection\ResponseInterface;

interface ConverterInterface
{
    /**
     * Converts the data into an associative array with camelCase keys
     *
     * Example:
     *     [
     *         'status' => [
     *             'code' => 'invalid',
     *             'invalid' => [
     *                 'field' => 'login',
     *                 'type' => 'string',
     *                 'subcode' => 'missing',
     *             ],
     *         ],
     *         'common' => [
     *             'zoneId' => 3,
     *             'locale' => '',
     *         ],
     *     ];
     *
     * @param ResponseInterface $response
     * @throws InvalidArgumentException if data is invalid
     * @return array
     */
    public static function convert(ResponseInterface $response);
}
