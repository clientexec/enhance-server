<?php
/**
 * ServiceLogStatus
 *
 * PHP version 7.4
 *
 * @category Class
 * @package  OpenAPI\Client
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */

/**
 * orchd
 *
 * orchd API docs
 *
 * The version of the OpenAPI document: 9.1.1
 * Generated by: https://openapi-generator.tech
 * OpenAPI Generator version: 6.4.0
 */

/**
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Do not edit the class manually.
 */

namespace OpenAPI\Client\Model;
use \OpenAPI\Client\ObjectSerializer;

/**
 * ServiceLogStatus Class Doc Comment
 *
 * @category Class
 * @description The status of the website at the time the log was written. Some statuses have additional metadata (ok, slow, unrecognizedGreetingsMessage)     whereas some don&#39;t (timeout).
 * @package  OpenAPI\Client
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */
class ServiceLogStatus
{
    /**
     * Possible values of this enum
     */
    public const OK = 'ok';

    public const SLOW = 'slow';

    public const TIMEOUT = 'timeout';

    public const UNRECOGNIZED_GREETINGS_MESSAGE = 'unrecognizedGreetingsMessage';

    /**
     * Gets allowable values of the enum
     * @return string[]
     */
    public static function getAllowableEnumValues()
    {
        return [
            self::OK,
            self::SLOW,
            self::TIMEOUT,
            self::UNRECOGNIZED_GREETINGS_MESSAGE
        ];
    }
}


