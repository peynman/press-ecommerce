<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers;

use DomainException;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Exceptions\InvalidException;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Exceptions\NoAccessException;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Exceptions\NoDataException;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Exceptions\TooMuchDataException;

/**
 * Validate the status code
 */
abstract class StatusValidate
{
    /**
     * Validate the status code and throw an exception if something is wrong
     *
     * @param array $status
     * @throws InvalidException
     * @throws NoAccessException
     * @throws NoDataException
     * @throws TooMuchDataException
     * @throws DomainException
     */
    public static function validate(array $status)
    {
        switch ($status['code']) {
            case 'ok':
                return;

            case 'invalid':
                $invalid = $status['invalid'];
                throw new InvalidException(
                    "{$invalid['field']} {$invalid['subcode']}"
                );

            case 'no-access':
                throw new NoAccessException($status['subcode']);

            case 'no-data':
                throw new NoDataException();

            case 'too-much-data':
                throw new TooMuchDataException();
        }

        throw new DomainException('Status Code is Invalid');
    }
}
