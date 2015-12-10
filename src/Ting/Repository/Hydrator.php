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

namespace CCMBenchmark\Ting\Repository;

use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\UnitOfWork;

class Hydrator implements HydratorInterface
{

    protected $metadataRepository = null;
    protected $unitOfWork         = null;

    /**
     * @param MetadataRepository $metadataRepository
     * @return void
     */
    public function setMetadataRepository(MetadataRepository $metadataRepository)
    {
        $this->metadataRepository = $metadataRepository;
    }

    /**
     * @param UnitOfWork $unitOfWork
     * @return void
     */
    public function setUnitOfWork(UnitOfWork $unitOfWork)
    {
        $this->unitOfWork = $unitOfWork;
    }


    /**
     * Hydrate one object from values and add to Collection
     * @param array               $columns
     * @param CollectionInterface $collection
     * @return array
     */
    public function hydrate(array $columns, CollectionInterface $collection)
    {
        $result = $this->hydrateColumns($columns);
        $collection->add($result);
        return $result;
    }

    /**
     * Hydrate one object from values
     *
     * @internal hydrate all column into the right Entity according to the table name and metadata information
     *           all virtual columns (COUNT(*), etc) will be set in the array key 0
     *           all Entities without any information (a "LEFT JOIN user" can return no informatoin at all about user)
     *              are set to null
     *
     * @param array               $columns
     * @return array
     */
    protected function hydrateColumns(array $columns)
    {
        $result        = [];
        $metadataList  = [];
        $tmpEntities   = []; // Temporary entity when all properties are null for the moment (LEFT/RIGHT JOIN)
        $validEntities = []; // Entity marked as valid will fill an object
                             // (a valid Entity is a entity with at less one property not null)

        foreach ($columns as $column) {
            // We have the information table, it's not a virtual column like COUNT(*)
            if (isset($result[$column['table']]) === false) {
                $this->metadataRepository->findMetadataForTable(
                    $column['orgTable'],
                    function (Metadata $metadata) use ($column, &$result, &$metadataList) {
                        $metadataList[$column['table']] = $metadata;
                        $result[$column['table']]       = $metadata->createEntity();
                        $tmpEntities[$column['table']]  = [];
                    }
                );
            }

            // We have a metadata defined for the column
            if (isset($metadataList[$column['table']]) === true &&
                $metadataList[$column['table']]->hasColumn($column['orgName']) === true
            ) {
                // Column value is null or entity is still not marked as valid
                if ($column['value'] === null && isset($validEntities[$column['table']]) === false) {
                    $tmpEntities[$column['table']][$column['orgName']] = $result[$column['table']];
                } else {
                    // Entity was previously marked as a temporary entity, we set all previous columns retrieved
                    if (isset($tmpEntities[$column['table']]) === true && $tmpEntities[$column['table']] !== []) {
                        foreach ($tmpEntities[$column['table']] as $entityColumn => $entity) {
                            $metadataList[$column['table']]->setEntityProperty(
                                $entity,
                                $entityColumn,
                                null
                            );
                        }
                        unset($tmpEntities[$column['table']]);
                    }

                    $validEntities[$column['table']] = true;

                    $metadataList[$column['table']]->setEntityProperty(
                        $result[$column['table']],
                        $column['orgName'],
                        $column['value']
                    );
                }

            // Table is not mapped or column is a virtual column
            } else {
                $validEntities[0] = true;
                if (isset($result[0]) === false) {
                    $result[0] = new \stdClass();
                }

                $result[0]->$column['name'] = $column['value'];
            }
        }

        foreach ($result as $table => $entity) {
            // All no valid entity is replaced by a null value
            if (isset($validEntities[$table]) === false) {
                $result[$table] = null;
            }

            if (is_object($entity) === true) {
                $this->unitOfWork->manage($entity);
            }
        }

        return $result;
    }
}
