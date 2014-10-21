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

namespace tests\units\CCMBenchmark\Ting\Repository;

use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\ConnectionPoolInterface;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\MetadataFactory as MetadataFactoryOriginal;
use mageekguy\atoum;
use tests\fixtures\model\BouhRepository;

class Repository extends atoum
{
    public function testExecuteShouldExecuteQuery()
    {
        $mockDriver         = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver();
        $mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();
        $this->calling($mockConnectionPool)->connect =
            function ($connectionConfig, $database, $connectionType, \Closure $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $services  = new \CCMBenchmark\Ting\Services();
        $mockQuery = new \mock\CCMBenchmark\Ting\Query\Query('SELECT * FROM bouh');
        $this->calling($mockQuery)->execute =
            function ($metadata, $connectionPool, $collection) use (&$outerCollection) {
                $outerCollection = $collection;
            };

        $collection = new \CCMBenchmark\Ting\Repository\Collection();

        $this
            ->if($repository = new \tests\fixtures\model\BouhRepository(
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('MetadataFactory'),
                $services->get('CollectionFactory'),
                $services->get('UnitOfWork'),
                $services->get('Cache')
            ))
            ->then($repository->execute($mockQuery, $collection))
            ->object($outerCollection)
                ->isIdenticalTo($collection);
    }

    public function testExecuteShouldReturnACollectionIfNoParam()
    {
        $services           = new \CCMBenchmark\Ting\Services();
        $mockDriver         = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver();
        $mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();

        $this->calling($mockConnectionPool)->connect =
            function ($connectionConfig, $database, $connectionType, \Closure $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $mockQuery = new \mock\CCMBenchmark\Ting\Query\Query('SELECT * FROM bouh');
        $this->calling($mockQuery)->execute =
            function ($metadata, $connectionPool, $collection) use (&$outerCollection) {
                $outerCollection = $collection;
            };
        $this
            ->if($repository = new \tests\fixtures\model\BouhRepository(
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('MetadataFactory'),
                $services->get('CollectionFactory'),
                $services->get('UnitOfWork'),
                $services->get('Cache')
            ))
            ->then($repository->execute($mockQuery))
            ->object($outerCollection)
                ->isInstanceOf('\CCMBenchmark\Ting\Repository\Collection');
    }

    public function testGet()
    {
        $services           = new \CCMBenchmark\Ting\Services();
        $mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();
        $driverFake         = new \mock\Fake\Mysqli();
        $mockDriver         = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver($driverFake);
        $mockMysqliResult   = new \mock\tests\fixtures\FakeDriver\MysqliResult(array());

        $this->calling($driverFake)->query = $mockMysqliResult;

        $this->calling($mockMysqliResult)->fetch_fields = function () {
            $fields = array();
            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'prenom';
            $stdClass->orgname  = 'boo_firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        };

        $this->calling($mockMysqliResult)->fetch_array[1] = [3, 'Sylvain'];
        $this->calling($mockMysqliResult)->fetch_array[2] = null;

        $this->calling($mockConnectionPool)->connect =
            function ($connectionConfig, $database, $connectionType, \Closure $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $bouh = new \tests\fixtures\model\Bouh();
        $bouh->setId(3);
        $bouh->setfirstname('Sylvain');

        $this
            ->if($bouhRepository = new \tests\fixtures\model\BouhRepository(
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('MetadataFactory'),
                $services->get('CollectionFactory'),
                $services->get('UnitOfWork'),
                $services->get('Cache')
            ))
            ->and($testBouh = $bouhRepository->get(3))
            ->integer($testBouh->getId())
                ->isIdenticalTo($bouh->getId())
            ->string($testBouh->getFirstname())
                ->isIdenticalTo($bouh->getFirstname());
    }

    public function testGetOnMaster()
    {
        $services           = new \CCMBenchmark\Ting\Services();
        $mockMetadataFactory= new \mock\CCMBenchmark\Ting\Repository\MetadataFactory($services->get('QueryFactory'));
        $mockMetadata       = new \mock\CCMBenchmark\Ting\Repository\Metadata($services->get('QueryFactory'));
        $fakeDriver         = new \mock\Fake\Mysqli();
        $mockDriver         = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver($fakeDriver);
        $mockMysqliResult   = new \mock\tests\fixtures\FakeDriver\MysqliResult(array());
        $mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();

        $this->calling($mockMetadataFactory)->get = function () use ($mockMetadata) {
            return $mockMetadata;
        };

        $this->calling($mockConnectionPool)->connect =
            function ($connectionConfig, $database, $connectionType, \Closure $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $this->calling($fakeDriver)->query = $mockMysqliResult;

        $this->calling($mockMysqliResult)->fetch_fields = function () {
            $fields = array();
            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'prenom';
            $stdClass->orgname  = 'boo_firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        };

        $this->calling($mockMysqliResult)->fetch_array[1] = [3, 'Sylvain'];
        $this->calling($mockMysqliResult)->fetch_array[2] = null;

        $this
            ->if(
                $bouhRepository = new \tests\fixtures\model\BouhRepository(
                    $mockConnectionPool,
                    $services->get('MetadataRepository'),
                    $mockMetadataFactory,
                    $services->get('CollectionFactory'),
                    $services->get('UnitOfWork'),
                    $services->get('Cache')
                )
            )
            ->and($bouhRepository->get(3, null, ConnectionPoolInterface::CONNECTION_MASTER))
                ->mock($mockMetadata)
                    ->call('connectMaster')
                    ->once();
        ;
    }

    public function testExecuteWithConnectionTypeShouldCallQueryExecuteWithConnectionType()
    {
        $services  = new \CCMBenchmark\Ting\Services();
        $mockQuery = new \mock\CCMBenchmark\Ting\Query\Query('SELECT * FROM bouh');

        $this->calling($mockQuery)->execute = true;

        $connectionPool = new ConnectionPool();
        $metadataFactory = new MetadataFactoryOriginal(new QueryFactory());
        $metadata = BouhRepository::initMetadata($metadataFactory);
        $collectionFactory = new CollectionFactory();

        $this
            ->if(
                $bouhRepository = new \tests\fixtures\model\BouhRepository(
                    $connectionPool,
                    $services->get('MetadataRepository'),
                    $metadataFactory,
                    $collectionFactory,
                    $services->get('UnitOfWork'),
                    $services->get('Cache')
                )
            )
            ->and(
                $bouhRepository->execute(
                    $mockQuery,
                    null,
                    ConnectionPoolInterface::CONNECTION_MASTER
                )
            )
                ->mock($mockQuery)
                   ->call('execute')
                        ->withArguments(
                            $metadata,
                            $connectionPool,
                            $collectionFactory->get(),
                            ConnectionPoolInterface::CONNECTION_MASTER
                        )
                        ->once()
        ;
    }


    public function testStartTransactionShouldOpenTransaction()
    {
        $services           = new \CCMBenchmark\Ting\Services();
        $fakeDriver         = new \mock\Fake\Mysqli();
        $mockDriver         = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver($fakeDriver);
        $mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();

        $services->set('ConnectionPool', function ($container) use ($mockConnectionPool) {
            return $mockConnectionPool;
        });

        $this->calling($mockConnectionPool)->connect =
            function ($connectionConfig, $database, $connectionType, \Closure $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $this
            ->if($bouhRepository = new \tests\fixtures\model\BouhRepository(
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('MetadataFactory'),
                $services->get('CollectionFactory'),
                $services->get('UnitOfWork'),
                $services->get('Cache')
            ))
            ->then($bouhRepository->startTransaction())
            ->boolean($mockDriver->isTransactionOpened())
                ->isTrue();
    }

    public function testCommitShouldCloseTransaction()
    {
        $services           = new \CCMBenchmark\Ting\Services();
        $fakeDriver         = new \mock\Fake\Mysqli();
        $mockDriver         = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver($fakeDriver);
        $mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();

        $this->calling($mockConnectionPool)->connect =
            function ($connectionConfig, $database, $connectionType, \Closure $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $this
            ->if($bouhRepository = new \tests\fixtures\model\BouhRepository(
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('MetadataFactory'),
                $services->get('CollectionFactory'),
                $services->get('UnitOfWork'),
                $services->get('Cache')
            ))
            ->then($bouhRepository->startTransaction())
            ->then($bouhRepository->commit())
            ->boolean($mockDriver->isTransactionOpened())
                ->isFalse()
        ;
    }

    public function testRollbackShouldCloseTransaction()
    {
        $services           = new \CCMBenchmark\Ting\Services();
        $fakeDriver         = new \mock\Fake\Mysqli();
        $mockDriver         = new \mock\CCMBenchmark\Ting\Driver\Mysqli\Driver($fakeDriver);
        $mockConnectionPool = new \mock\CCMBenchmark\Ting\ConnectionPool();

        $this->calling($mockConnectionPool)->connect =
            function ($connectionConfig, $database, $connectionType, \Closure $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $this
            ->if($bouhRepository = new \tests\fixtures\model\BouhRepository(
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('MetadataFactory'),
                $services->get('CollectionFactory'),
                $services->get('UnitOfWork'),
                $services->get('Cache')
            ))
            ->then($bouhRepository->startTransaction())
            ->then($bouhRepository->rollback())
            ->boolean($mockDriver->isTransactionOpened())
                ->isFalse()
        ;
    }
}
