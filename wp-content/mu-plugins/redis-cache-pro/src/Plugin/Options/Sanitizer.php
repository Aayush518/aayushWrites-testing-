<?php
/**
 * Copyright © Rhubarb Tech Inc. All Rights Reserved.
 *
 * All information contained herein is, and remains the property of Rhubarb Tech Incorporated.
 * The intellectual and technical concepts contained herein are proprietary to Rhubarb Tech Incorporated and
 * are protected by trade secret or copyright law. Dissemination and modification of this information or
 * reproduction of this material is strictly forbidden unless prior written permission is obtained from
 * Rhubarb Tech Incorporated.
 *
 * You should have received a copy of the `LICENSE` with this file. If not, please visit:
 * https://objectcache.pro/license.txt
 */

declare(strict_types=1);

namespace RedisCachePro\Plugin\Options;

class Sanitizer
{
    /**
     * Sanitize the `channel` option value.
     *
     * @param  mixed  $value
     * @return string
     */
    public function channel($value)
    {
        return sanitize_key($value);
    }

    /**
     * Sanitize the `flushlog` option value.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function flushlog($value)
    {
        return (bool) intval($value);
    }
}
