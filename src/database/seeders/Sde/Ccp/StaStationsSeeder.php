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


namespace Seat\Eveapi\Database\Seeders\Sde\Ccp;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Seat\Eveapi\Database\Seeders\Sde\AbstractSdeSeeder;
use Seat\Eveapi\Mapping\Sde\AbstractSdeMapping;
use Seat\Eveapi\Mapping\Sde\Ccp\StaStationMapping;
use Seat\Eveapi\Models\Sde\MapDenormalize;
use Seat\Eveapi\Models\Sde\StaStation;

/**
 * Class StaStationsSeeder.
 *
 * npcStations.jsonl describes the station itself. Its display name, security
 * status and the constellation and region it belongs to are published with the
 * map dump, so they are copied from mapDenormalize once that table exists.
 *
 * The docking and reprocessing costs CCP no longer publishes stay null.
 *
 * @package Seat\Eveapi\Database\Seeders\Sde\Ccp
 */
class StaStationsSeeder extends AbstractSdeSeeder
{
    protected const FILENAME = 'npcStations.jsonl';

    /**
     * Define seeder related SDE table structure.
     *
     * @param  \Illuminate\Database\Schema\Blueprint  $table
     * @return void
     */
    protected function getSdeTableDefinition(Blueprint $table): void
    {
        $table->bigInteger('stationID')->primary();
        $table->double('security')->nullable();
        $table->double('dockingCostPerVolume')->nullable();
        $table->double('maxShipVolumeDockable')->nullable();
        $table->integer('officeRentalCost')->nullable();
        $table->integer('operationID')->nullable();
        $table->integer('stationTypeID')->nullable();
        $table->integer('corporationID')->nullable();
        $table->integer('solarSystemID')->nullable();
        $table->integer('constellationID')->nullable();
        $table->integer('regionID')->nullable();
        $table->string('stationName', 100)->nullable();
        $table->double('x')->nullable();
        $table->double('y')->nullable();
        $table->double('z')->nullable();
        $table->double('reprocessingEfficiency')->nullable();
        $table->double('reprocessingStationsTake')->nullable();
        $table->integer('reprocessingHangarFlag')->nullable();

        $table->index('constellationID', 'ix_staStations_constellationID');
        $table->index('operationID', 'ix_staStations_operationID');
        $table->index('regionID', 'ix_staStations_regionID');
        $table->index('stationTypeID', 'ix_staStations_stationTypeID');
        $table->index('corporationID', 'ix_staStations_corporationID');
        $table->index('solarSystemID', 'ix_staStations_solarSystemID');
    }

    /**
     * The mapping instance which must be used to seed table with SDE dump.
     *
     * @return \Seat\Eveapi\Mapping\Sde\AbstractSdeMapping
     */
    protected function getMappingClass(): AbstractSdeMapping
    {
        return new StaStationMapping();
    }

    /**
     * @param  array  $arr
     * @return int
     */
    public function insert($arr)
    {
        return StaStation::insert($arr);
    }

    /**
     * Copy the name, security and location of every station from the map.
     *
     * @return void
     */
    protected function after(): void
    {
        if (! Schema::hasTable('mapDenormalize')) {
            $this->command->warn(
                'mapDenormalize is not available, station names and security are not filled.');

            return;
        }

        DB::table('mapDenormalize')
            ->where('groupID', MapDenormalize::STATION)
            ->select(['itemID', 'itemName', 'security', 'constellationID', 'regionID'])
            ->chunk(1000, function ($rows) {
                foreach ($rows as $row)
                    DB::table('staStations')
                        ->where('stationID', $row->itemID)
                        ->update([
                            'stationName' => $row->itemName,
                            'security' => $row->security,
                            'constellationID' => $row->constellationID,
                            'regionID' => $row->regionID,
                        ]);
            });
    }
}
