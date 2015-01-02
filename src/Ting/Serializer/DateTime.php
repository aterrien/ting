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

namespace CCMBenchmark\Ting\Serializer;


class DateTime implements SerializerInterface
{
    /**
     * @param \DateTime $toSerialize
     * @param array $options
     * @return string
     */
    public function serialize($toSerialize, array $options = [])
    {
        $defaultOptions = ['format' => 'Y-m-d H:i:s'];
        $options = array_merge($defaultOptions, $options);
        return $toSerialize->format($options['format']);
    }

    /**
     * @param string $serialized
     * @param array  $options
     * @return \Datetime
     */
    public function unserialize($serialized, array $options = [])
    {
        $defaultOptions = ['format' => 'Y-m-d H:i:s'];
        $options = array_merge($defaultOptions, $options);
        return \DateTime::createFromFormat($options['format'], $serialized);
    }
}
