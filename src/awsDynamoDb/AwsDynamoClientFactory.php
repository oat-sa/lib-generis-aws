<?php
/**
 * Copyright 2016 Open Assessment Technologies SA
 * 
 * This file is part of the Tao AWS tools.
 * 
 * Foobar is free software: you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 * 
 * Foobar is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with this package.
 * If not, see http://www.gnu.org/licenses/.
 * 
 */

namespace oat\awsTools\awsDynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use oat\awsTools\AwsClient;
use oat\oatbox\service\ConfigurableService;

/**
 * 
 * @author Joel Bout
 */
class AwsDynamoClientFactory extends ConfigurableService
{
    const OPTION_CLIENT = 'client';

    const OPTION_TABLE = 'table';

    const OPTION_BASE64_ENCODED = 'base64_encoded';

    const OPTION_LARGE_VALUE = 'enable_large_value';

    const DEFAULT_AWS_CLIENT_KEY = 'generis/awsClient';

    /**
     * Aws Dynamo allows a value maximum width of 400
     * All values have to be base64 encoded, that means final value will take 33% more of space
     * So maximum value is 300
     */
    const MAX_WIDTH_VALUE = 300000;

    protected $client;
    protected $tableName;

    /**
     * Get the dynamodb client
     *
     * DynamoDbClient should be construct from params client key
     *     - Nothing: look for generis/awsClient, if not throws common_exception
     *     - Array, as DynamoDbClient params
     *     - DynamoDbClient
     *     - AwsClient, DynamoDbClient params will be AwsClient options
     *     - string, key of serviceManager for AwsClient
     *
     * @return DynamoDbClient|mixed
     * @throws \common_Exception
     */
    public function getClient()
    {
        if (! $this->hasOption(self::OPTION_CLIENT)) {
            if (! $this->getServiceLocator()->has(self::DEFAULT_AWS_CLIENT_KEY)) {
                throw new \common_Exception('Unable to load driver for dynamodb, config key "client" is missing' .
                    ' and generis/awsClient.conf.php not found.');
            }
            $this->setOption(self::OPTION_CLIENT, $this->getServiceLocator()->get(self::DEFAULT_AWS_CLIENT_KEY));
        }

        $client = $this->getOption(self::OPTION_CLIENT);
        if (is_array($client)) {
            $dynamoClientConfig = (new AwsClient($client))->getDynamoClient();
        }  elseif ($client instanceof AwsClient) {
            $dynamoClientConfig = $client->getDynamoClient();
        } else {
            if (! $this->getServiceLocator()->has($this->getOption(self::OPTION_CLIENT))) {
                throw new \common_Exception('Client config found but it\'s not loadable.');
            }
            $awsClient = $this->getServiceLocator()->get($this->getOption(self::OPTION_CLIENT));
            $dynamoClientConfig = $awsClient->getDynamoClient();
        }
        return $dynamoClientConfig;
    }

    /**
     * Get the table name of dynamo db
     *
     * @return mixed
     * @throws \common_Exception
     */
    public function getTableName()
    {
        if (! $this->hasOption(self::OPTION_TABLE)) {
            throw new \common_Exception('Unable to load driver for dynamodb, config key "table" is missing.');
        }
        return $this->getOption(self::OPTION_TABLE);
    }

    /**
     * Return base64 encoding as boolean, default is true
     *
     * @return bool
     */
    public function getIsBase64Encoded()
    {
        if (! $this->hasOption(self::OPTION_BASE64_ENCODED)) {
            return true;
        }
        return (bool) $this->getOption(self::OPTION_BASE64_ENCODED);
    }

    /**
     * Return the key large value persistence, default is false
     *
     * @param AwsDynamoDbDriver $driver
     * @return \common_persistence_AdvKeyLargeValuePersistence|\common_persistence_AdvKeyValuePersistence
     */
    public function getConfiguredPersistence(AwsDynamoDbDriver $driver)
    {
        if ($this->hasOption(self::OPTION_LARGE_VALUE)) {
            if (! $this->hasOption(\common_persistence_KeyLargeValuePersistence::VALUE_MAX_WIDTH)) {
                $this->setOption(\common_persistence_KeyLargeValuePersistence::VALUE_MAX_WIDTH, self::MAX_WIDTH_VALUE);
            }
            return new \common_persistence_AdvKeyLargeValuePersistence($this->getOptions(), $driver);
        } else {
            return new \common_persistence_AdvKeyValuePersistence($this->getOptions(), $driver);
        }
    }

}