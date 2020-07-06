<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2018 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 *
 */

namespace oat\awsTools\items;

use League\Flysystem\AwsS3v3\AwsS3Adapter;
use oat\awsTools\AwsClient;
use oat\oatbox\filesystem\FileSystemService;
use oat\oatbox\service\ConfigurableService;
use oat\taoItems\model\render\ItemAssetsReplacement;
use oat\taoOperations\scripts\tools\CloudFrontAssets;
use oat\oatbox\filesystem\File;

/**
 * Implementation of the ItemAssetsReplacement for cloudfront signature
 *
 * @access public
 * @author Antoine Robin, <antoine@taotesting.com>
 * @package awsTools
 */
class ItemCloudFrontReplacement extends ConfigurableService implements ItemAssetsReplacement
{

    /**
     * The pattern to match, should be something like '/^https?:\\/\\/specific\\.cloudfront\\.net\\//',
     */
    const OPTION_PATTERN = 'pattern';
    /**
     * The time during which the resource is accessible in second, by default 1440
     */
    const OPTION_EXPIRATION = 'expiration';
    /**
     * The Key pair used in the cloudfront configuration
     */
    const OPTION_KEYPAIR = 'keyPair';

    /**
     * The tmp name for the key file, it will be used to store the s3 file on the server
     */
    const OPTION_LOCAL_KEYFILE = 'localFile';

    /**
     * The key file name used in cloudfront configuration, this is the name on the s3
     */
    const OPTION_S3_KEYFILE = 's3File';

    /**
     * The bucket where you stored the key file
     */
    const OPTION_S3_BUCKET = 'bucket';

    /**
     * The prefix on the bucket in which you stored the key file
     */
    const OPTION_S3_PREFIX = 'prefix';

    /**
     * The client service id, you can leave it empty if you have an awsClient configuration in generis/awsClient.conf.php
     */
    const OPTION_CLIENT = 'client';

    const DEFAULT_AWS_CLIENT_KEY = 'generis/awsClient';


    /**
     * Replace the asset string with the signed one if it is needed
     * @param string $asset
     * @return string
     * @throws \common_Exception
     * @throws \common_exception_Error
     */
    public function postProcessAssets($asset)
    {
        $signedUrl = $asset;

        if ($this->shouldBeReplaced($asset)) {
            $keyFile = $this->retrieveKeyFile();


            $cloudFront = $this->getClient()->getCloudFrontClient();

            $expiration = ($this->hasOption(self::OPTION_EXPIRATION)) ? $this->getOption(self::OPTION_EXPIRATION) : 1440;
            $expires = time() + $expiration;

            $signedUrl = $cloudFront->getSignedUrl(array(
                'private_key' => $keyFile,
                'key_pair_id' => $this->getOption(self::OPTION_KEYPAIR),
                'url' => $asset,
                'expires' => $expires,
            ));

        }

        return $signedUrl;
    }

    /**
     * @inheritdoc
     */
    public function replaceResourcesWithCloudfront($file)
    {
        if (!class_exists('CloudFrontAssets') || !$this->hasOption('url') || !$this->getOption('url')) {
            return null;
        }

        $filename = $this->getFileSystemService()->getFullPathFile($file);

        $cloudFrontAssets = new CloudFrontAssets();
        $cloudFrontAssets->setServiceLocator($this->getServiceLocator());

        $resSetCloudFront = $cloudFrontAssets([
            "-s",
            $filename,
            "-b",
            $this->getOption('bucket'),
            "-p",
            $this->getOption('prefix'),
            "-u",
            $this->getOption('url'),
            "-t",
            $filename
        ]);

        return $resSetCloudFront;
    }

    /**
     * Whether or not the assets should be replaced
     * @param string $asset
     * @return bool
     * @throws \common_Exception if there is an issue in the pattern provided
     */
    protected function shouldBeReplaced($asset)
    {
        if ($this->hasOption(self::OPTION_PATTERN)) {
            if (($preg = preg_match($this->getOption(self::OPTION_PATTERN), $asset)) === 1) {
                return true;
            } elseif ($preg === false) {
                throw new \common_Exception('There is an issue with the pattern : ' . $this->getOption(self::OPTION_PATTERN));

            }
        }
        return false;
    }

    /**
     * Method that check if the key file exists or retrieve it from s3
     * @return string path to the local key file
     * @throws \common_Exception
     */
    protected function retrieveKeyFile()
    {
        if (!$this->hasOption(self::OPTION_LOCAL_KEYFILE)) {
            throw new \common_Exception('You should provide a configuration for : ' . self::OPTION_LOCAL_KEYFILE);
        }

        if (!file_exists($this->getOption(self::OPTION_LOCAL_KEYFILE))) {
            if ($this->hasOption(self::OPTION_S3_KEYFILE) && $this->hasOption(self::OPTION_S3_BUCKET) && $this->hasOption(self::OPTION_S3_PREFIX)) {
                $s3Client = $this->getClient()->getS3Client();
                $s3Adapter = new AwsS3Adapter($s3Client, $this->getOption(self::OPTION_S3_BUCKET), $this->getOption(self::OPTION_S3_PREFIX));
                $response = $s3Adapter->read($this->getOption(self::OPTION_S3_KEYFILE));
                if ($response !== false) {
                    file_put_contents($this->getOption(self::OPTION_LOCAL_KEYFILE), $response['contents']);
                    chmod($this->getOption(self::OPTION_LOCAL_KEYFILE), 0600);
                } else {
                    throw new \common_Exception('Unable to retrieve key file from s3 : ' . $this->getOption(self::OPTION_LOCAL_KEYFILE));
                }
            } else {
                throw new \common_Exception('Unable to retrieve key file from s3. You should have a configuration for : ' . self::OPTION_S3_KEYFILE . ',' . self::OPTION_S3_BUCKET . 'and' . self::OPTION_S3_PREFIX);
            }
        }

        return $this->getOption(self::OPTION_LOCAL_KEYFILE);
    }

    /**
     * Method that gets the awsClient with the correct configuration
     * @return AwsClient
     * @throws \common_Exception
     */
    protected function getClient()
    {
        $serviceId = ($this->hasOption(self::OPTION_CLIENT)) ? $this->getOption(self::OPTION_CLIENT) : self::DEFAULT_AWS_CLIENT_KEY;

        if (!$this->getServiceLocator()->has($serviceId)) {
            throw new \common_Exception('Unable to load driver for aws, config key "client" is missing' .
                ' and generis/awsClient.conf.php not found.');
        }
        return $this->getServiceLocator()->get($serviceId);
    }

    /**
     * @return FileSystemService
     */
    private function getFileSystemService()
    {
        return $this->getServiceLocator()->get(FileSystemService::SERVICE_ID);
    }
}
