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

use Aws\CloudFront\CloudFrontClient;
use Aws\CloudWatch\CloudWatchClient;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use oat\oatbox\service\ConfigurableService;
use Aws\S3\S3Client;
/**
 * AwsClient class. It allow to retrieve all the aws client classes with the correct options
 * @author Joel Bout
 */
class AwsClient extends ConfigurableService
{

    /**
     * Retrieve the aws S3 client with the options of the class
     * @return S3Client
     */
    public function getS3Client()
    {
        return new S3Client($this->getOptions());
    }

    /**
     * Retrieve the aws Cloudfront client with the options of the class
     * @return CloudFrontClient
     */
    public function getCloudFrontClient()
    {
        return new CloudFrontClient($this->getOptions());
    }

    /**
     * Retrieve the aws Dynamo DB client with the options of the class
     * @return DynamoDbClient
     */
    public function getDynamoClient()
    {
        return new DynamoDbClient($this->getOptions());
    }

    /**
     * Retrieve the aws SQS client with the options of the class and potential extra options
     * @param array $extraOptions
     * @return SqsClient
     */
    public function getSqsClient(array $extraOptions = [])
    {
        return new SqsClient(array_merge($this->getOptions(), $extraOptions));
    }

    /**
     * Retrieve the aws SNS client with the options of the class and potential extra options
     * @param array $extraOptions
     * @return SnsClient
     */
    public function getSnsClient(array $extraOptions = [])
    {
        return new SnsClient(array_merge($this->getOptions(), $extraOptions));
    }

    /**
     * Retrieve the aws CloudWatch client with the options of the class and potential extra options
     * @param array $extraOptions
     * @return CloudWatchClient
     */
    public function getCloudWatchClient(array $extraOptions = []){
        return new CloudWatchClient(array_merge($this->getOptions(), $extraOptions));
    }

    /**
     * Retrieve the aws CloudWatch Logs client with the options of the class and potential extra options
     * @param array $extraOptions
     * @return CloudWatchLogsClient
     */
    public function getCloudWatchLogsClient(array $extraOptions = []){
        return new CloudWatchLogsClient(array_merge($this->getOptions(), $extraOptions));
    }
}