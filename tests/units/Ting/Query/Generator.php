<?php
/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
 *
 ***********************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 **********************************************************************/


namespace tests\units\CCMBenchmark\Ting\Query;

use CCMBenchmark\Ting\Repository\CollectionFactory;
use mageekguy\atoum;

class Generator extends atoum
{
    protected $mockConnectionPool;
    protected $mockConnection;
    protected $mockQueryFactory;

    public function beforeTestMethod($method)
    {
        $this->mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();
        $this->mockConnection = new \mock\CCMBenchmark\Ting\Connection($this->mockConnectionPool, 'main', 'db');
        $this->mockQueryFactory = new \mock\CCMBenchmark\Ting\Query\QueryFactory();
        $mockDriver = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver();


        $this->calling($this->mockConnection)->slave  = $mockDriver;
        $this->calling($this->mockConnection)->master = $mockDriver;
    }

    public function testGetByPrimariesShouldReturnAQuery()
    {
        $this
            ->if(
                $generator = new \CCMBenchmark\Ting\Query\Generator(
                    $this->mockConnection,
                    $this->mockQueryFactory,
                    'table',
                    ['id', 'population']
                )
            )
            ->object($generator->getByPrimaries(['id' => 1], new CollectionFactory()))
                ->isInstanceOf('\CCMBenchmark\Ting\Query\Query')
            ->mock($this->mockConnection)
                ->call('slave')
                    ->once()
            ->object($generator->getByPrimaries(['id' => 1], new CollectionFactory(), true))
                ->isInstanceOf('\CCMBenchmark\Ting\Query\Query')
            ->mock($this->mockConnection)
                ->call('master')
                    ->once()

        ;
    }

    public function testInsertShouldReturnAPreparedQuery()
    {
        $this
            ->if(
                $generator = new \CCMBenchmark\Ting\Query\Generator(
                    $this->mockConnection,
                    $this->mockQueryFactory,
                    'table',
                    ['id', 'population']
                )
            )
            ->object($generator->insert(['id' => 1]))
                ->isInstanceOf('\CCMBenchmark\Ting\Query\PreparedQuery')
        ;
    }

    public function testUpdateShouldReturnAPreparedQuery()
    {
        $this
            ->if(
                $generator = new \CCMBenchmark\Ting\Query\Generator(
                    $this->mockConnection,
                    $this->mockQueryFactory,
                    'table',
                    ['id', 'population']
                )
            )
            ->object($generator->update(['id' => [0 => 1, 1 => 2], 'name' => ['oldValue', 'newValue']], ['id' => 1]))
                ->isInstanceOf('\CCMBenchmark\Ting\Query\PreparedQuery')
        ;
    }

    public function testDeleteShouldReturnAPreparedQuery()
    {
        $this
            ->if(
                $generator = new \CCMBenchmark\Ting\Query\Generator(
                    $this->mockConnection,
                    $this->mockQueryFactory,
                    'table',
                    ['id', 'population']
                )
            )
            ->object($generator->delete(['id' => 1]))
                ->isInstanceOf('\CCMBenchmark\Ting\Query\PreparedQuery')
        ;
    }
}