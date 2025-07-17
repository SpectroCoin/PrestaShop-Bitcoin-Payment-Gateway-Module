<?php
/**
 * SpectroCoin Module
 *
 * Copyright (C) 2014-2025 SpectroCoin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software Foundation,
 * Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @author SpectroCoin
 * @copyright 2014-2025 SpectroCoin
 * @license https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace SpectroCoin\SCMerchantClient\Enum;

if (!defined('_PS_VERSION_')) {
    exit;
}

enum OrderStatus: string
{
    case NEW     = 'NEW';
    case PENDING = 'PENDING';
    case PAID    = 'PAID';
    case FAILED  = 'FAILED';
    case EXPIRED = 'EXPIRED';

    /**
     * Map old numeric codes to new enum.
     */
    public static function fromCode(int $code): self
    {
        return match ($code) {
            1 => self::NEW,
            2 => self::PENDING,
            3 => self::PAID,
            4 => self::FAILED,
            5 => self::EXPIRED,
            default => throw new \InvalidArgumentException("Unknown numeric status code: $code"),
        };
    }

    /**
     * Normalize either an integer (legacy) or a string.
     */
    public static function normalize(string|int $raw): self
    {
        if (is_int($raw) || ctype_digit((string)$raw)) {
            return self::fromCode((int)$raw);
        }
        $upper = strtoupper((string)$raw);
        return match ($upper) {
            'NEW'     => self::NEW,
            'PENDING' => self::PENDING,
            'PAID'    => self::PAID,
            'FAILED'  => self::FAILED,
            'EXPIRED' => self::EXPIRED,
            default   => throw new \InvalidArgumentException("Unknown status string: $raw"),
        };
    }
}
