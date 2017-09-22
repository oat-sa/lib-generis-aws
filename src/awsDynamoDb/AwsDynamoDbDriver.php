<?php
/**
 * Copyright 2016 Open Assessment Technologies SA
 *
 * This file is part of the Tao AWS tools.
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU
 * General Public License as published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even
 * the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this package.
 * If not, see http://www.gnu.org/licenses/.
 *
 */

namespace oat\awsTools\awsDynamoDb;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Result;
use oat\oatbox\service\ServiceManager;

/**
 * A driver for Amazon DynamoDB
 *
 * @author Joel Bout <joel@taotesting.com>
 * @author Camille Moyon <camille@taotesting.com>
 */
class AwsDynamoDbDriver implements \common_persistence_AdvKvDriver
{
    /**
     * The abstraction for dynamo connection
     *
     * @var DynamoDbClient
     */
    private $client;

    /**
     * The table name to manage
     *
     * @var string
     */
    private $tableName;

    /**
     * To know if values has to be store as base64
     *
     * @var boolean
     */
    private $isBase64_encoded;

    const HPREFIX = 'hPrfx_';

    const SIMPLE_KEY_NAME = 'key';
    const SIMPLE_VALUE_NAME = 'value';

    /**
     * @see common_persistence_Driver::connect()
     *
     * @param string $key
     * @param array $params
     * @return \common_persistence_Persistence
     */
    public function connect($key, array $params)
    {
        $dynamoClientFactory = $this->getDynamoFactory($params);

        $this->tableName        = $dynamoClientFactory->getTableName();
        $this->client           = $dynamoClientFactory->getClient();
        $this->isBase64_encoded = $dynamoClientFactory->getIsBase64Encoded();

        return $dynamoClientFactory->getConfiguredPersistence($this);
    }

    /**
     * Get the service to factory AwsDynamoClient
     *
     * @param array $options
     * @return AwsDynamoClientFactory
     */
    protected function getDynamoFactory(array $options)
    {
        $dynamoClientFactory = new AwsDynamoClientFactory($options);
        ServiceManager::getServiceManager()->propagate($dynamoClientFactory);
        return $dynamoClientFactory;
    }

    /**
     * (non-PHPdoc)
     *
     * @see common_persistence_KvDriver::set()
     */
    public function set($key, $value, $ttl = null)
    {
        try {
            $valueEncoded = $value;

            if (gettype($value) === 'integer') {
                $valueType = 'N';
            } else {
                $valueType = 'B';
                if ($this->isBase64_encoded) {
                    $valueEncoded = base64_encode($value);
                }
            }

            $result = $this->client->updateItem(array(
                'TableName' => $this->tableName,
                'Key' => array(
                    self::SIMPLE_KEY_NAME => array(
                        'S' => $key
                    )
                ),
                'ExpressionAttributeNames' => [
                    '#VALUE' => self::SIMPLE_VALUE_NAME
                ],
                'ExpressionAttributeValues' => [
                    ':val1' => [$valueType => $valueEncoded]
                ],
                'UpdateExpression' => 'set #VALUE = :val1',
                'ReturnValues' => 'ALL_NEW'
            ));

            $a = $result->get('Attributes');
            \common_Logger::t('SET:: ' . $key);

            return (bool)($valueEncoded == $a[self::SIMPLE_VALUE_NAME][$valueType]);
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see common_persistence_KvDriver::get()
     */
    public function get($key)
    {
        /** @var Result $result */
        $result = $this->client->getItem(array(
            'ConsistentRead' => true,
            'TableName' => $this->tableName,
            'Key' => array(
                self::SIMPLE_KEY_NAME => array(
                    'S' => $key
                )
            )
        ));

        \common_Logger::t('GET: ' . $key);
        $itemResult = $result->get('Item');

        if (is_null($itemResult)) {
            return false;
        }

        $resultValue = $itemResult[self::SIMPLE_VALUE_NAME];
        if (isset($resultValue['B'])) {
            if ($this->isBase64_encoded) {
                return base64_decode($resultValue['B']);
            } else {
                return $resultValue['B'];
            }
        } elseif (isset($resultValue['N'])) {
            $return = (int)$resultValue['N'];
        } else {
            // unexpected storage type
            $return = null;
        }

        return $return;
    }

    /**
     * (non-PHPdoc)
     *
     * @see common_persistence_KvDriver::exists()
     */
    public function exists($key)
    {
        /** @var Result $result */
        $result = $this->client->getItem(array(
            'ConsistentRead' => true,
            'TableName' => $this->tableName,
            'Key' => array(
                self::SIMPLE_KEY_NAME => array(
                    'S' => $key
                )
            )
        ));

        $itemResult = $result->get('Item');
        \common_Logger::t('EXISTS: ' . $key);
        if (is_null($itemResult)) {
            return false;
        }
        return true;
    }

    /**
     * (non-PHPdoc)
     *
     * @see common_persistence_KvDriver::del()
     */
    public function del($key)
    {
        try {
            $this->client->deleteItem(array(
                'TableName' => $this->tableName,
                'Key' => array(
                    self::SIMPLE_KEY_NAME => array(
                        'S' => $key
                    )
                )
            ));
            \common_Logger::t('DEL: ' . $key);
        } catch (\Exception $ex) {
            return false;
        }
        return true;
    }

    /**
     * Increments the value of a key by 1.
     * The data in the key needs to be of a integer type
     *
     * @param string $key
     *            The key to be incremented
     * @return integer bool the value of the incremented key if the operation succeeds and FALSE if the operation fails
     */
    public function incr($key)
    {
        $result = $this->client->updateItem(array(
            'TableName' => $this->tableName,
            'Key' => array(
                self::SIMPLE_KEY_NAME => array(
                    'S' => $key
                )
            ),
            'AttributeUpdates' => array(
                self::SIMPLE_VALUE_NAME => array(
                    'Action' => 'ADD',
                    'Value' => array(
                        'N' => 1
                    )
                )
            ),
            'ReturnValues' => 'UPDATED_NEW'
        ));
        return (int)$result['Attributes'][self::SIMPLE_VALUE_NAME]['N'];
    }

    /**
     * Sets the specified fields to their respective values in the hash stored at key.
     * <br />
     * This command overwrites any existing fields in the hash. <br />
     * If key does not exist, a new key holding a hash is created. <br />
     *
     * @param string $key
     *            The key on which the operation will be applied to
     * @param array $fields
     *            An associative array with the key=>value pairs to be set
     * @return boolean Returns TRUE if the operation is successfull
     */
    public function hmSet($key, $fields)
    {
        \common_Logger::t(' Call of ' . __METHOD__);

        $attributesToUpdate = array();

        if (!is_array($fields)) {
            return false;
        }
        foreach ($fields as $hashkey => $val) {
            if ($this->isBase64_encoded) {
                $encodedValue = base64_encode($val);
            } else {
                $encodedValue = $val;
            }
            $attributesToUpdate[self::HPREFIX . $hashkey] = array(
                'Action' => 'PUT',
                'Value' => array(
                    'B' => $encodedValue
                )
            );
        }

        if (count($attributesToUpdate) > 0) {
            try {
                $result = $this->client->updateItem(array(
                    'TableName' => $this->tableName,
                    'Key' => array(
                        self::SIMPLE_KEY_NAME => array(
                            'S' => $key
                        )
                    ),
                    'AttributeUpdates' => $attributesToUpdate,
                    'ReturnValues' => 'UPDATED_OLD'
                ));
                return true;
            } catch (\Exception $ex) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Determine if a hash field exists at $key
     *
     * @param string $key
     *            The key on which to perform the check
     * @param string $field
     *            The field name to check for
     * @return boolean Returns TRUE if the field exists and FALSE otherwise
     */
    public function hExists($key, $field)
    {
        \common_Logger::t(' Call of ' . __METHOD__);

        $result = $this->client->getItem(array(
            'TableName' => $this->tableName,
            'Key' => array(
                self::SIMPLE_KEY_NAME => array(
                    'S' => $key
                )
            ),
            'ConsistentRead' => true,
            'AttributesToGet' => array(
                self::HPREFIX . $field
            )
        ));
        return isset($result['Item'][self::HPREFIX . $field]);
    }

    /**
     * Returns all fields and values of the hash stored at key
     *
     * @param string $key
     *            The key to get all hash fields from
     * @return array An associative array containing all the keys and values of the hashes
     */
    public function hGetAll($key)
    {
        \common_Logger::t(' Call of ' . __METHOD__);

        $result = $this->client->getItem(array(
            'TableName' => $this->tableName,
            'Key' => array(
                self::SIMPLE_KEY_NAME => array(
                    'S' => $key
                )
            ),
            'ConsistentRead' => true
        ));
        if (isset($result['Item'])) {
            $tempArray = $result['Item'];
            unset($result);
            unset($tempArray[self::SIMPLE_KEY_NAME]); // remove the KEY from the resutlset
            $prefixLength = strlen(self::HPREFIX);
            $returnArray = array();
            foreach ($tempArray as $taKey => $val) {
                if (mb_substr($taKey, 0, $prefixLength) === self::HPREFIX) {
                    if ($this->isBase64_encoded) {
                        $decodedValue = base64_decode($val['B']);
                    } else {
                        $decodedValue = $val['B'];
                    }
                    $returnArray[mb_substr($taKey, $prefixLength)] = $decodedValue;
                    unset($tempArray[$taKey]); // unset data as soon as we don't need it so we could free memory
                } else {
                    unset($tempArray[$taKey]);
                }
            }
            return $returnArray;
        } else {
            return array();
        }
    }

    /**
     * Returns the value associated with field in the hash stored at key
     *
     * @param string $key
     *            The desired key to get a hash value from
     * @param string $field
     *            The name of the hash field to get
     * @return mixed The value stored at the specified hash field
     */
    public function hGet($key, $field)
    {
        \common_Logger::t(' Call of ' . __METHOD__);

        $result = $this->client->getItem(array(
            'TableName' => $this->tableName,
            'Key' => array(
                self::SIMPLE_KEY_NAME => array('S' => $key)
            ),
            'ConsistentRead' => true,
            'AttributesToGet' => array(self::HPREFIX . $field)
        ));
        if (isset($result['Item'][self::HPREFIX . $field])) {
            if ($this->isBase64_encoded) {
                return base64_decode($result['Item'][self::HPREFIX . $field]['B']);
            } else {
                return $result['Item'][self::HPREFIX . $field]['B'];
            }
        } else {
            return null;
        }
    }

    /**
     * Sets field in the hash stored at key to value. If key does not exist, a new key holding a hash is created. <br />
     * If field already exists in the hash, it is overwritten. <br />
     *
     * @param string $key The key at which to set a hash
     * @param string $field The field to set a value to
     * @param mixed $value The value to be set
     * @return integer Returns 1 if field is a new field in the hash and value was set, 0 if field already exists in the hash and the value was updated
     */
    public function hSet($key, $field, $value)
    {
        \common_Logger::t(' Call of ' . __METHOD__);

        if (!($key !== '') || !($field !== '')) {
            return false;
        }
        try {
            $result = $this->client->updateItem(array(
                'TableName' => $this->tableName,
                'Key' => array(
                    self::SIMPLE_KEY_NAME => array('S' => $key)
                ),
                'AttributeUpdates' => array(
                    self::HPREFIX . $field => array(
                        'Action' => 'PUT',
                        'Value' => array('B' => $this->isBase64_encoded ? base64_encode($value) : $value)
                    )
                )
            ));
            return true;
        } catch (\Exception $ex) {
            \common_Logger::i('Error on ' . __METHOD__ . ' : ' . $ex->getMessage());
            return false;
        }
    }

    /**
     * Returns all keys that match the pattern given in $pattern. If an asterisk is used it returns all keys that start with the string that precede the asterisk.<br />
     * If an asterisk is not used then it returns all keys containing the $pattern.
     *
     * @param string $pattern
     * @return array An array containing all matched keys
     */
    public function keys($pattern)
    {
        \common_Logger::t(' Call of ' . __METHOD__);

        $astPos = mb_strpos($pattern, '*');
        if ($astPos !== false && $astPos > 0) {
            $comparisonOperator = 'BEGINS_WITH';
            $comparisonValue = mb_substr($pattern, 0, $astPos);
        } else {
            $comparisonOperator = 'CONTAINS';
            $comparisonValue = $pattern;
        }

        $iterator = $this->client->getIterator('Scan', array(
            'TableName' => $this->tableName,
            'AttributesToGet' => array(self::SIMPLE_KEY_NAME),
            'ReturnConsumedCapacity' => 'TOTAL',
            'ScanFilter' => array(
                self::SIMPLE_KEY_NAME => array(
                    'AttributeValueList' => array(
                        array('S' => $comparisonValue)
                    ),
                    'ComparisonOperator' => $comparisonOperator
                )
            )
        ));

        $keysArray = array();
        foreach ($iterator as $item) {
            $keysArray[] = $item[self::SIMPLE_KEY_NAME]['S'];
        }
        return $keysArray;
    }
}