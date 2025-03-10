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
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA (under the project TAO-PRODUCT);
 *
 */

declare(strict_types=1);

namespace oat\awsTools\QtiItems;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Config;
use oat\awsTools\AwsClient;
use oat\awsTools\items\ItemCloudFrontReplacement;
use oat\awsTools\BucketOwnerVisibilityConverter;
use oat\oatbox\service\ConfigurableService;
use oat\taoItems\model\render\ItemAssetsReplacement;
use oat\taoQtiItem\model\compile\QtiAssetReplacer\QtiItemAssetReplacer;
use oat\taoQtiItem\model\pack\QtiAssetPacker\PackedAsset;

class QtiItemAssetCloudFrontReplacer extends ConfigurableService implements QtiItemAssetReplacer
{
    /**
     * The host for the external source
     */
    const OPTION_HOST = 'host';

    /**
     * The prefix for the path
     */
    const OPTION_PREFIX = 'prefix';

    /**
     * {@inheritDoc}
     */
    public function shouldBeReplaced(PackedAsset $packetAsset): bool
    {
        return !$this->isExcluded($this->getFilenameFormPacket($packetAsset));
    }

    /**
     * {@inheritDoc}
     *
     * @throws \common_Exception
     */
    public function replace(PackedAsset $packetAsset, string $itemId): PackedAsset
    {
        $filename = $this->getFilenameFormPacket($packetAsset);

        $awsClient = $this->getAwsClient();
        $s3Client = $awsClient->getS3Client();
        $config = new Config();

        $itemAssetReplacement = $this->getItemAssetsReplacement();

        if (!($itemAssetReplacement instanceof ItemCloudFrontReplacement)) {
            throw new \common_Exception('The ItemCloudFrontReplacement service should be configured properly for using CloudFront assets.');
        }

        $s3Adapter = $this->getAwsS3Adapter(
            $s3Client,
            $itemAssetReplacement->getOption(ItemCloudFrontReplacement::OPTION_S3_BUCKET),
            $this->getOption(self::OPTION_PREFIX)
        );

        $path = $this->buildPath($itemId);

        $s3Adapter->writeStream($path . $filename, $this->getResourceFromPacket($packetAsset), $config);
        $path = $this->getOption(self::OPTION_HOST) . DIRECTORY_SEPARATOR . $path . $filename;
        $packetAsset->setReplacedBy($path);

        return $packetAsset;
    }

    /**
     * Build a final path for an asset
     */
    private function buildPath(string $itemId): string
    {
        return 'items' . DIRECTORY_SEPARATOR . $itemId . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR ;
    }

    /**
     * Get a filename from PacketAsset
     */
    private function getFilenameFormPacket(PackedAsset $packetAsset): string
    {
        $mediaAsset = $packetAsset->getMediaAsset();
        $identifier = $mediaAsset->getMediaIdentifier();
        return $mediaAsset->getMediaSource()->getBaseName($identifier);
    }

    /**
     * Get resource from PacketAsset
     */
    private function getResourceFromPacket(PackedAsset $packetAsset)
    {
        $mediaAsset = $packetAsset->getMediaAsset();
        $identifier = $mediaAsset->getMediaIdentifier();
        $fileStream = $mediaAsset->getMediaSource()->getFileStream($identifier);
        return $fileStream->detach();
    }

    /**
     * Check if an asset should be excluded from the moving to the CloudFront
     */
    private function isExcluded(string $src): bool
    {
        if ($this->hasOption(self::OPTION_EXCLUDE_PATTERNS)) {
            return $this->checkPatterns($src, $this->getOption(self::OPTION_EXCLUDE_PATTERNS));
        }
        return $this->checkPatterns($src, self::DEFAULT_EXCLUDE_PATTERNS);
    }

    /**
     * Check patterns
     * @param string $src
     * @param array $patterns
     * @return bool
     */
    private function checkPatterns(string $src, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $src) == 1) {
                return true;
            }
        }
        return false;
    }

    private function getAwsClient(): AwsClient
    {
        return $this->getServiceLocator()->get('generis/awsClient');
    }

    private function getItemAssetsReplacement(): ItemAssetsReplacement
    {
        return $this->getServiceLocator()->get(ItemAssetsReplacement::SERVICE_ID);
    }

    protected function getAwsS3Adapter(S3Client $s3Client, string $bucket, string $prefix): AwsS3V3Adapter
    {
        return new AwsS3V3Adapter(
            $s3Client,
            $bucket,
            $prefix,
            new BucketOwnerVisibilityConverter()
        );
    }
}
