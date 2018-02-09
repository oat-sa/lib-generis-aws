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

namespace oat\awsTools;

use Aws\DynamoDb\DynamoDbClient;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use oat\oatbox\service\ConfigurableService;
use Aws\S3\S3Client;
/**
 * 
 * @author Joel Bout
 */
class AwsClient extends ConfigurableService
{
    public function getS3Client()
    {
        return new S3Client($this->getOptions());
    }

    public function getDynamoClient()
    {
        return new DynamoDbClient($this->getOptions());
    }

    public function getSqsClient(array $extraOptions = [])
    {
        return new SqsClient(array_merge($this->getOptions(), $extraOptions));
    }

    public function getSnsClient()
    {
        return new SnsClient($this->getOptions());
    }
}