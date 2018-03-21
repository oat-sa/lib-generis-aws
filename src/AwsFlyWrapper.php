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

use oat\oatbox\service\ConfigurableService;
use League\Flysystem\AdapterInterface;
use oat\oatbox\filesystem\utils\FlyWrapperTrait;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use oat\flysystem\Adapter\LocalCacheAdapter;
use League\Flysystem\Adapter\Local;
use oat\oatbox\log\LoggerAwareTrait;
/**
 * 
 * @author Joel Bout
 */
class AwsFlyWrapper extends ConfigurableService implements AdapterInterface
{
    use FlyWrapperTrait;
    use LoggerAwareTrait;
    
    const OPTION_BUCKET = 'bucket';
    
    const OPTION_PREFIX = 'prefix';
    
    const OPTION_CLIENT = 'client';
    
    const OPTION_CACHE = 'cache';
    
    const OPTION_CACHE_LISTCONTENTS = 'cache-listcontents';

    const OPTION_CACHE_HASDIRECTORY = 'cache-hasdirectory';

    private $adapter;
    
    public function getClient()
    {
        $clientServiceId = $this->hasOption(self::OPTION_CLIENT) ? $this->getOption(self::OPTION_CLIENT) : 'generis/awsClient';
        return $this->getServiceLocator()->get($clientServiceId)->getS3Client();
    }
    
    /**
     * (non-PHPdoc)
     * @see \oat\oatbox\filesystem\FlyWrapperTrait::getAdapter()
     */
    public function getAdapter()
    {
        if (is_null($this->adapter)) {
            $adapter = new AwsS3Adapter($this->getClient(),$this->getOption(self::OPTION_BUCKET),$this->getOption(self::OPTION_PREFIX));
            if ($this->hasOption(self::OPTION_CACHE)) {
                if (class_exists(LocalCacheAdapter::class)) {
                    $cached = new Local($this->getOption(self::OPTION_CACHE));
                    $adapter = new LocalCacheAdapter($adapter, $cached, true);

                    // FlySystem::listContents caching.
                    if ($this->hasOption(self::OPTION_CACHE_LISTCONTENTS) && method_exists($adapter, 'setCacheListContents')) {
                        $adapter->setCacheListContents(boolval($this->getOption(self::OPTION_CACHE_LISTCONTENTS)));
                    }

                    // FlySystem::has caching.
                    if ($this->hasOption(self::OPTION_CACHE_HASDIRECTORY) && method_exists($adapter, 'setCacheHasDirectory')) {
                        $adapter->setCacheHasDirectory(boolval($this->getOption(self::OPTION_CACHE_HASDIRECTORY)));
                    }
                } else {
                    $this->logWarning('Cache specified but LocalCacheAdapter class not found');
                }
            }
            $this->adapter = $adapter;
        }
        return $this->adapter;
    }
}
