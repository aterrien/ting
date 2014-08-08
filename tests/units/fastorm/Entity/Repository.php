<?php

namespace tests\units\fastorm\Entity;

use \mageekguy\atoum;

class Repository extends atoum
{

    public function testShouldBeSingleton()
    {
        $this
            ->object(\fastorm\Entity\Repository::getInstance())
            ->isIdenticalTo(\fastorm\Entity\Repository::getInstance());
    }

    public function testExecuteShouldExecuteQuery()
    {
        $mockDriver = new \mock\fastorm\Driver\Mysqli\Driver();
        $mockConnectionPool = new \mock\fastorm\ConnectionPool();
        $this->calling($mockConnectionPool)->connect =
            function ($connectionName, $database, callable $callback) use ($mockDriver) {
                $callback($mockDriver);
            };

        $mockQuery = new \mock\fastorm\Query('SELECT * FROM bouh');
        $this->calling($mockQuery)->execute =
            function ($driver, $columns, $collection) use (&$outerDriver, &$outerColumns, &$outerCollection) {
                $outerDriver     = $driver;
                $outerColumns    = $columns;
                $outerCollection = $collection;
            };

        $collection = new \fastorm\Entity\Collection();

        $this
            ->if($repository = \fastorm\Entity\Repository::getInstance())
            ->then($repository->execute($mockQuery, $collection, $mockConnectionPool))
            ->object($outerDriver)
                ->isIdenticalTo($mockDriver)
            ->array($outerColumns)
                ->isIdenticalTo(array('YOUR_OWN_INIT_METADATA' => array(
                    'id'         => true,
                    'fieldName'  => 'YOU_SHOULD_ADD',
                    'columnName' => 'YOUR_OWN_INIT_METADATA',
                    'type'       => 'IN_YOUR_REPOSITORY'
                )))
            ->object($outerCollection)
                ->isIdenticalTo($collection);
    }
}
