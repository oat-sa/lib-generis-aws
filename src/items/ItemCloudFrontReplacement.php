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
use oat\oatbox\service\ConfigurableService;
use oat\taoItems\model\render\ItemAssetsReplacement;

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
    const CLOUDFRONT_PATTERN = 'cloudFrontPattern';
    /**
     * The time during which the resource is accessible in second, by default 1440
     */
    const CLOUDFRONT_EXPIRATION = 'cloudFrontExpiration';
    /**
     * The Key pair used in the cloudfront configuration
     */
    const CLOUDFRONT_KEYPAIR = 'cloudFrontKeyPair';
    /**
     * The key file name used in cloudfront configuration, this is the name on the s3
     */
    const CLOUDFRONT_KEYFILE = 'cloudFrontKeyFile';
    /**
     * The tmp name for the key file, it will be used to store the s3 file on the server
     */
    const CLOUDFRONT_KEYTMPFILE = 'tmpFile';
    /**
     * The bucket where you stored the key file
     */
    const BUCKET = 'bucket';

    /**
     * The prefix on the bucket in which you stored the key file
     */
    const PREFIX = 'prefix';

    /**
     * The client definition, you can leave it empty if you have an awsClient configuration in generis/awsClient.conf.php
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
            $awsClient = $this->getClient();
            $keyFile = $this->retrieveKeyFile();


            $cloudFront = $this->getClient()->getCloudFrontClient();

            $expiration = ($this->hasOption(self::CLOUDFRONT_EXPIRATION)) ? $this->getOption(self::CLOUDFRONT_EXPIRATION) : 1440;
            $expires = time() + $expiration;

            $signedUrl = $cloudFront->getSignedUrl(array(
                'private_key' => $keyFile,
                'key_pair_id' => $this->getOption(self::CLOUDFRONT_KEYPAIR),
                'url' => $asset,
                'expires' => $expires,
            ));

        }

        return $signedUrl;
    }

    /**
     * Whether or not the assets should be replaced
     * @param string $asset
     * @return bool
     */
    private function shouldBeReplaced($asset)
    {
        if ($this->hasOption(self::CLOUDFRONT_PATTERN) && preg_match($this->getOption(self::CLOUDFRONT_PATTERN), $asset) === 1) {
            return true;
        }
        return false;
    }

    /**
     * Method that gets the awsClient with the correct configuration
     * @return AwsClient
     * @throws \common_Exception
     */
    private function getClient()
    {
        $serviceId = ($this->hasOption(self::OPTION_CLIENT)) ? $this->getOption(self::OPTION_CLIENT) : self::DEFAULT_AWS_CLIENT_KEY;

        if (!$this->getServiceLocator()->has($serviceId)) {
            throw new \common_Exception('Unable to load driver for aws, config key "client" is missing' .
                ' and generis/awsClient.conf.php not found.');
        }
        return $this->getServiceLocator()->get($serviceId);
    }

    /**
     * Method that check if the key file exists or retrieve it from s3
     * @return string path to the local key file
     * @throws \common_Exception
     */
    private function retrieveKeyFile()
    {
        if (!$this->hasOption(self::CLOUDFRONT_KEYTMPFILE)) {
            throw new \common_Exception('You should provide a configuration for : ' . self::CLOUDFRONT_KEYTMPFILE);
        }

        if (!file_exists($this->getOption(self::CLOUDFRONT_KEYTMPFILE))) {
            if ($this->hasOption(self::CLOUDFRONT_KEYFILE)) {
                $s3Client = $this->getClient()->getS3Client();
                $s3Adapter = new AwsS3Adapter($s3Client, $this->getOption(self::BUCKET), $this->getOption(self::PREFIX));
                $response = $s3Adapter->read($this->getOption(self::CLOUDFRONT_KEYFILE));
                if ($response !== false) {
                    file_put_contents($this->getOption(self::CLOUDFRONT_KEYTMPFILE), $response['contents']);
                    chmod($this->getOption(self::CLOUDFRONT_KEYTMPFILE), 700);
                } else {
                    throw new \common_Exception('Unable to retrieve key file from s3 : ' . $this->getOption(self::CLOUDFRONT_KEYFILE));
                }
            } else {
                throw new \common_Exception('Unable to retrieve key file from s3. You should have a configuration for : ' . $this->getOption(self::CLOUDFRONT_KEYFILE));
            }
        }

        return $this->getOption(self::CLOUDFRONT_KEYTMPFILE);
    }
}
