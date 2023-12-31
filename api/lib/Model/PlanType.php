<?php
/**
 * PlanType
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
 * PlanType Class Doc Comment
 *
 * @category Class
 * @package  OpenAPI\Client
 * @author   OpenAPI Generator team
 * @link     https://openapi-generator.tech
 */
class PlanType
{
    /**
     * Possible values of this enum
     */
    public const SHARED = 'shared';

    public const DEDICATED = 'dedicated';

    /**
     * Gets allowable values of the enum
     * @return string[]
     */
    public static function getAllowableEnumValues()
    {
        return [
            self::SHARED,
            self::DEDICATED
        ];
    }
}


