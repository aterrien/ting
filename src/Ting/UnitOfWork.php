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

namespace CCMBenchmark\Ting;

use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\Ting\Entity\PropertyListenerInterface;
use CCMBenchmark\Ting\Query\QueryFactoryInterface;
use CCMBenchmark\Ting\Repository\Metadata;

class UnitOfWork implements PropertyListenerInterface
{
    const STATE_NEW     = 1;
    const STATE_MANAGED = 2;
    const STATE_DELETE  = 3;

    protected $connectionPool            = null;
    protected $metadataRepository        = null;
    protected $queryFactory              = null;
    protected $entities                  = [];
    protected $entitiesManaged           = [];
    protected $entitiesChanged           = [];
    protected $entitiesShouldBePersisted = [];
    protected $statements = [];

    /**
     * @param ConnectionPool        $connectionPool
     * @param MetadataRepository    $metadataRepository
     * @param QueryFactoryInterface $queryFactory
     */
    public function __construct(
        ConnectionPool $connectionPool,
        MetadataRepository $metadataRepository,
        QueryFactoryInterface $queryFactory
    ) {
        $this->connectionPool     = $connectionPool;
        $this->metadataRepository = $metadataRepository;
        $this->queryFactory       = $queryFactory;
    }

    /**
     * @return string
     */
    protected function generateUUID()
    {
        return uniqid(rand(), true);
    }

    /**
     * Watch changes on provided entity
     *
     * @param $entity
     */
    public function manage($entity)
    {
        if (isset($entity->tingUUID) === false) {
            $entity->tingUUID = $this->generateUUID();
        }

        $this->entitiesManaged[$entity->tingUUID] = true;
        if ($entity instanceof NotifyPropertyInterface) {
            $entity->addPropertyListener($this);
        }
    }

    /**
     * @param $entity
     * @return bool - true if the entity is managed
     */
    public function isManaged($entity)
    {
        if (isset($entity->tingUUID) === false) {
            return false;
        }

        if (isset($this->entitiesManaged[$entity->tingUUID]) === true) {
            return true;
        }

        return false;
    }

    /**
     * @param $entity
     * @return bool - true if the entity has not been persisted yet
     */
    public function isNew($entity)
    {
        if (isset($entity->tingUUID) === false) {
            return false;
        }

        if (
            isset($this->entitiesShouldBePersisted[$entity->tingUUID]) === true
            && $this->entitiesShouldBePersisted[$entity->tingUUID] === self::STATE_NEW
        ) {
            return true;
        }
        return false;
    }

    /**
     * Flag the entity to be persisted (insert or update) on next process
     *
     * @param $entity
     * @return $this
     */
    public function pushSave($entity)
    {
        if (isset($entity->tingUUID) === false) {
            $entity->tingUUID = $this->generateUUID();
        }

        $state = self::STATE_NEW;
        if (isset($this->entitiesManaged[$entity->tingUUID]) === true) {
            $state = self::STATE_MANAGED;
        }

        $this->entitiesShouldBePersisted[$entity->tingUUID] = $state;
        $this->entities[$entity->tingUUID] = $entity;

        return $this;
    }

    /**
     * @param $entity
     * @return bool
     */
    public function shouldBePersisted($entity)
    {
        if (isset($entity->tingUUID) === false) {
            return false;
        }

        if (isset($this->entitiesShouldBePersisted[$entity->tingUUID]) === true) {
            return true;
        }

        return false;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @param $oldValue
     * @param $newValue
     */
    public function propertyChanged($entity, $propertyName, $oldValue, $newValue)
    {
        if ($oldValue === $newValue) {
            return;
        }

        if (isset($entity->tingUUID) === false) {
            $entity->tingUUID = $this->generateUUID();
        }

        if (isset($this->entitiesChanged[$entity->tingUUID]) === false) {
            $this->entitiesChanged[$entity->tingUUID] = [];
        }

        if (isset($this->entitiesChanged[$entity->tingUUID][$propertyName]) === false) {
            $this->entitiesChanged[$entity->tingUUID][$propertyName] = [$oldValue, null];
        }

        $this->entitiesChanged[$entity->tingUUID][$propertyName][1] = $newValue;
    }

    /**
     * @param $entity
     * @param $propertyName
     * @return bool
     */
    public function isPropertyChanged($entity, $propertyName)
    {
        if (isset($entity->tingUUID) === false) {
            return false;
        }

        if (isset($this->entitiesChanged[$entity->tingUUID][$propertyName]) === true) {
            return true;
        }

        return false;
    }

    /**
     * Stop watching changes on the entity
     *
     * @param $entity
     */
    public function detach($entity)
    {
        if (isset($entity->tingUUID) === false) {
            return;
        }

        unset($this->entitiesChanged[$entity->tingUUID]);
        unset($this->entitiesShouldBePersisted[$entity->tingUUID]);
        unset($this->entities[$entity->tingUUID]);
    }

    /**
     * Flag the entity to be deleted on next process
     *
     * @param $entity
     * @return $this
     */
    public function pushDelete($entity)
    {
        if (isset($entity->tingUUID) === false) {
            $entity->tingUUID = $this->generateUUID();
        }

        $this->entitiesShouldBePersisted[$entity->tingUUID] = self::STATE_DELETE;
        $this->entities[$entity->tingUUID] = $entity;

        return $this;
    }

    /**
     * Returns true if delete($entity) has been called
     *
     * @param $entity
     * @return bool
     */
    public function shouldBeRemoved($entity)
    {
        if (isset($entity->tingUUID) === false) {
            return false;
        }

        if (
            isset($this->entitiesShouldBePersisted[$entity->tingUUID]) === true
            && $this->entitiesShouldBePersisted[$entity->tingUUID] === self::STATE_DELETE
        ) {
            return true;
        }

        return false;
    }

    /**
     * Apply flagged changes against the database:
     * Save flagged new entities
     * Update flagged entities
     * Delete flagged entities
     */
    public function process()
    {
        foreach ($this->entitiesShouldBePersisted as $uuid => $state) {
            switch ($state) {
                case self::STATE_MANAGED:
                    $this->processManaged($uuid);
                    break;

                case self::STATE_NEW:
                    $this->processNew($uuid);
                    break;

                case self::STATE_DELETE:
                    $this->processDelete($uuid);
                    break;
            }
        }
        foreach ($this->statements as $statementName => $connections) {
            foreach ($connections as $connection) {
                $connection->closeStatement($statementName);
            }
        }
        $this->statements = [];
    }

    /**
     * Update all applicable entities in database
     *
     * @param $uuid
     * @throws Exception
     */
    protected function processManaged($uuid)
    {
        if (isset($this->entitiesChanged[$uuid]) === false) {
            return;
        }

        $entity = $this->entities[$uuid];
        $properties = [];
        foreach ($this->entitiesChanged[$uuid] as $property => $values) {
            if ($values[0] !== $values[1]) {
                $properties[$property] = $values;
            }
        }

        if ($properties === []) {
            return;
        }

        $this->metadataRepository->findMetadataForEntity(
            $entity,
            function (Metadata $metadata) use ($entity, $properties, $uuid) {
                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForUpdate(
                    $connection,
                    $this->queryFactory,
                    $entity,
                    $properties
                );

                $this->addStatementToClose($query->getStatementName(), $connection->master());
                $query->prepareExecute()->execute();

                unset($this->entitiesChanged[$uuid]);
                unset($this->entitiesShouldBePersisted[$uuid]);
            },
            function () use ($entity) {
                throw new Exception('Could not find repository matching entity "' . get_class($entity) . '"');
            }
        );
    }

    /**
     * Insert all applicable entities in database
     *
     * @param $uuid
     * @throws Exception
     */
    protected function processNew($uuid)
    {
        $entity = $this->entities[$uuid];

        $this->metadataRepository->findMetadataForEntity(
            $entity,
            function (Metadata $metadata) use ($entity, $uuid) {
                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForInsert(
                    $connection,
                    $this->queryFactory,
                    $entity
                );
                $this->addStatementToClose($query->getStatementName(), $connection->master());
                $query->prepareExecute()->execute();

                $metadata->setEntityPropertyForAutoIncrement($entity, $connection->master());

                unset($this->entitiesChanged[$uuid]);
                unset($this->entitiesShouldBePersisted[$uuid]);

                $this->manage($entity);
            },
            function () use ($entity) {
                throw new Exception('Could not find repository matching entity "' . get_class($entity) . '"');
            }
        );
    }

    /**
     * Delete all flagged entities from database
     *
     * @param $uuid
     * @throws Exception
     */
    protected function processDelete($uuid)
    {
        $entity = $this->entities[$uuid];
        $properties = [];
        if (isset($this->entitiesChanged[$uuid])) {
            foreach ($this->entitiesChanged[$uuid] as $property => $values) {
                if ($values[0] !== $values[1]) {
                    $properties[$property] = $values;
                }
            }
        }

        $this->metadataRepository->findMetadataForEntity(
            $entity,
            function (Metadata $metadata) use ($entity, $properties) {
                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForDelete(
                    $connection,
                    $this->queryFactory,
                    $properties,
                    $entity
                );
                $this->addStatementToClose($query->getStatementName(), $connection->master());
                $query->prepareExecute()->execute();
                $this->detach($entity);
            },
            function () use ($entity) {
                throw new Exception('Could not find repository matching entity "' . get_class($entity) . '"');
            }
        );
    }

    protected function addStatementToClose($statementName, DriverInterface $connection)
    {
        if (isset($this->statements[$statementName]) === false) {
            $this->statements[$statementName] = [];
        }
        if (isset($this->statements[$statementName][spl_object_hash($connection)]) === false) {
            $this->statements[$statementName][spl_object_hash($connection)] = $connection;
        }
    }
}
