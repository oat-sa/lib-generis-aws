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

namespace oat\awsTools\test\unit;

use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Stream;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use oat\awsTools\AwsClient;
use oat\awsTools\items\ItemCloudFrontReplacement;
use oat\awsTools\QtiItems\QtiItemAssetCloudFrontReplacer;
use oat\oatbox\filesystem\Directory;
use oat\oatbox\filesystem\File;
use oat\oatbox\log\LoggerService;
use oat\tao\model\media\MediaAsset;
use oat\tao\model\media\MediaBrowser;
use oat\taoItems\model\media\ItemMediaResolver;
use oat\taoItems\model\render\ItemAssetsReplacement;
use oat\taoQtiItem\model\compile\QtiAssetCompiler\QtiItemAssetCompiler;
use oat\taoQtiItem\model\compile\QtiAssetReplacer\QtiItemAssetReplacer;
use oat\taoQtiItem\model\compile\QtiAssetReplacer\QtiItemNonReplacer;
use oat\taoQtiItem\model\compile\QtiItemCompilerAssetBlacklist;
use oat\taoQtiItem\model\pack\QtiAssetPacker\PackedAsset;
use oat\taoQtiItem\model\qti\Img;
use oat\taoQtiItem\model\qti\Item;
use oat\taoQtiItem\model\qti\XInclude;
use oat\taoQtiItem\test\unit\model\compile\mock\ElementMock;
use oat\generis\test\TestCase;
use Psr\Log\NullLogger;

class QtiItemAssetCloudFrontReplacerTest extends TestCase
{
    /** @var QtiItemAssetCompiler */
    private $subject;

    /** @var QtiItemCompilerAssetBlacklist */
    private $blackListService;

    /** @var ItemMediaResolver */
    private $resolver;

    /** @var Item */
    private $item;

    /** @var Directory */
    private $directory;

    /**
     * @var AwsClient
     */
    private $awsClient;

    /**
     * @var ItemAssetsReplacement
     */
    private $itemAssetsReplacement;

    /**
     * @var AwsS3V3Adapter
     */
    private $awsS3Adapter;

    public function setUp(): void
    {
        $this->subject = new QtiItemAssetCompiler();

        $this->blackListService = $this->createMock(QtiItemCompilerAssetBlacklist::class);
        $this->awsClient = $this->createMock(AwsClient::class);
        $this->itemAssetsReplacement = $this->createMock(ItemCloudFrontReplacement::class);
        $this->itemAssetsReplacement->method('getOption')->willReturn('bucket_test');

        $this->awsS3Adapter = $this->createMock(AwsS3V3Adapter::class);
        $this->awsS3Adapter->method('writeStream');

        $replacer = new TestQtiItemAssetCloudFrontReplacer([
            QtiItemAssetCloudFrontReplacer::OPTION_PREFIX => 'prefix',
            QtiItemAssetCloudFrontReplacer::OPTION_HOST => 'host'
        ]);

        $replacer->setAwsS3Adapter($this->awsS3Adapter);

        $replacer->setServiceLocator($this->getServiceLocatorMock([
            'generis/awsClient' => $this->awsClient,
             ItemAssetsReplacement::SERVICE_ID => $this->itemAssetsReplacement
        ]));

        $this->subject->setServiceLocator($this->getServiceLocatorMock([
           QtiItemCompilerAssetBlacklist::SERVICE_ID => $this->blackListService,
           LoggerService::SERVICE_ID => new NullLogger(),
           QtiItemAssetReplacer::SERVICE_ID => $replacer,
        ]));

        $this->resolver = $this->createMock(ItemMediaResolver::class);
        $this->item = $this->createMock(Item::class);
        $this->directory = $this->createMock(Directory::class);
    }

    public function testReplaceAssets()
    {
        $this->item
            ->method('getComposingElements')
            ->willReturn([
            (new ElementMock())->setComposingElements([
                $this->createConfiguredMock(XInclude::class, ['attr' => 'stimulus-href'])
            ]),
            (new ElementMock())->setComposingElements([
                $this->createConfiguredMock(Img::class, ['attr' => 'image-src'])
            ])
        ]);
        $this->item->method('getIdentifier')->willReturn('i12345qwerty');

        $this->resolver->expects($this->exactly(2))
            ->method('resolve')
            ->willReturnOnConsecutiveCalls(
                new MediaAsset(
                    $this->createConfiguredMock(
                        MediaBrowser::class,
                        [
                            'getFileInfo' => ['link' => 'stimulus-link'],
                            'getBaseName' => 'stimulus-link'
                        ]
                    ),
                    'stimulus-fixture'
                ),
                new MediaAsset(
                    $this->createConfiguredMock(
                        MediaBrowser::class,
                        [
                            'getFileInfo' => ['link' => 'image-link'],
                            'getBaseName' => 'image-link',
                            'getFileStream' => $this->createConfiguredMock(
                                Stream::class,
                                [
                                'detach' => true
                                ]
                            )
                        ]
                    ),
                    'image-fixture'
                )
            );

        $this->directory
            ->method('getFile')
            ->willReturn(
                $this->createConfiguredMock(File::class, ['write' => true])
            );

        $this->blackListService->method('isBlacklisted')->willReturn(false);

        $this->awsClient->method('getS3Client')->willReturn($this->createMock(S3Client::class));

        $packedAssets = $this->subject->extractAndCopyAssetFiles(
            $this->item,
            $this->directory,
            $this->resolver
        );

        $this->assertSame('host/items/i12345qwerty/assets/image-link', $packedAssets['image-src']->getReplacedBy());
    }

    private function getFilenameWithoutPrefix(string $filename): string
    {
        $delimiter = '_';
        return substr($filename, strpos($filename, $delimiter) + 1);
    }
}

class TestQtiItemAssetCloudFrontReplacer extends QtiItemAssetCloudFrontReplacer
{
    private $awsS3Adapter;

    public function setAwsS3Adapter($mock)
    {
        $this->awsS3Adapter = $mock;
    }

    protected function getAwsS3Adapter(S3Client $s3Client, string $bucket, string $prefix): AwsS3V3Adapter
    {
        return $this->awsS3Adapter;
    }
}
