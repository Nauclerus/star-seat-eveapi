<?php

/*
 * This file is part of SeAT
 *
 * Copyright (C) 2015 to present Leon Jacobs
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace Seat\Eveapi\Mapping\Sde\Ccp;

use Seat\Eveapi\Mapping\Sde\AbstractSdeMapping;

/**
 * InvTypeMaterialMapping.
 *
 * typeMaterials.jsonl holds one row per type with its materials nested in an
 * array, so every nested material becomes an invTypeMaterials row carrying the
 * typeID of its parent.
 */
class InvTypeMaterialMapping extends AbstractSdeMapping
{
    protected const MULTI_ARRAY_KEY = ['_key', 'typeID'];

    protected const MULTI_NEST_PATH = 'materials';

    /**
     * @var string[]
     */
    protected static $mapping = [
        // 'typeID' => '', // Populated from the parent row.
        'materialTypeID' => 'materialTypeID',
        'quantity' => 'quantity',
    ];
}
