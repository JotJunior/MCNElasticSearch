<?php
/**
 * Copyright (c) 2011-2013 Antoine Hedgecock.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @author      Antoine Hedgecock <antoine@pmg.se>
 *
 * @copyright   2011-2013 Antoine Hedgecock
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 */

namespace MCNElasticSearch\Service;

use Elastica\Client;
use Elastica\Type\Mapping;
use Elastica\Type;
use MCNElasticSearch\Options\TypeMappingOptions;
use Zend\EventManager\EventManagerAwareTrait;
use Zend\EventManager\EventManagerInterface;
use Zend\Log\Logger;
use Zend\Stdlib\Hydrator\ClassMethods;

/**
 * Class MappingService
 */
class MappingService implements MappingServiceInterface
{
    use EventManagerAwareTrait;

    /**
     * @var \Elastica\Client
     */
    protected $client;

    /**
     * @var MetadataService
     */
    protected $metadata;

    /**
     * @param \Elastica\Client $client
     * @param MetadataService $metadata
     */
    public function __construct(Client $client, MetadataService $metadata)
    {
        $this->client   = $client;
        $this->metadata = $metadata;
    }

    public function build(array $types = [])
    {
        $mappings = $this->metadata->getAllTypeMappings();

        if ($types !== null) {
            array_filter($mappings, function(TypeMappingOptions $t) use ($types) {
                return in_array($t->getName(), $types);
            });
        }

        $hydrator = new ClassMethods();

        /** @var $options \MCNElasticSearch\Options\TypeMappingOptions */
        foreach ($mappings as $options) {

            $type = $this->client->getIndex($options->getIndex())
                                 ->getType($options->getName());

            $mapping = new Mapping($type);
            $hydrator->hydrate($options->toArray(), $mapping);

            $response = $mapping->send();
            if (! $response->isOk()) {
                $this->getEventManager()->trigger(__FUNCTION__ . '.error', $this, [ 'response' => $response ]);
                throw new Exception\RuntimeException(
                    sprintf(
                        'Error updating "%s" mapping: %s', $options->getName(), $response->getError()
                    )
                );
            }
        }
    }

    public function delete(array $types = [])
    {
        $mappings = $this->metadata->getAllTypeMappings();

        if ($types !== null) {
            array_filter($mappings, function(TypeMappingOptions $t) use ($types) {
                return in_array($t->getName(), $types);
            });
        }

        foreach ($mappings as $options) {

            // Delete the type
            $response = $this->client->getIndex($options->getIndex())->getType($options->getName())->delete();

            if (! $response->isOk()) {
                $this->getEventManager()->trigger(__FUNCTION__ . '.error', $this, [ 'response' => $response ]);
                throw new Exception\RuntimeException(
                    sprintf(
                        'Error deleting "%s" mapping: %s', $options->getName(), $response->getError()
                    )
                );
            }
        }
    }
}
