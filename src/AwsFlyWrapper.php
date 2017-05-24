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

use oat\awsTools\factory\AbstractFlysystemFactory;
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

    const OPTION_FACTORY = 'factory';

    private $adapter;
    
    public function getClient()
    {



        $clientServiceId = $this->hasOption(self::OPTION_CLIENT) ? $this->getOption(self::OPTION_CLIENT) : 'generis/awsClient';
        return $this->getServiceLocator()->get($clientServiceId)->getS3Client();
    }

    /**
     * config sample
     *
     *
    'mediaManager' =>
     *      array(
                'class' => 'oat\\awsTools\\AwsFlyWrapper',
                'options' => array(
                    array(
                        'factory' => [
                            'class' =>   \oat\flysystem\Adapter\Factory\LocalCachedS3Factory::class ,
                            'options' =>
                             array(
                                'bucket' => 'my-s3-bucket',
                                'client' => 'generis/awsClient',
                                'metaStorage' =>  oat\flysystem\Adapter\Cache\Metadata\{TxtStorage|JsonStorage|PhpStorage|ApcuStorage}::class // optional if it isn't set, an autodetection is used
                                'prefix' => 'tao-test',
                                'path' => '/var/ww/tao/data/media'
                        )
                    ]
                )
     *      )
     *
     * @param $factoryOptions
     * @return AbstractFlysystemFactory|void
     */
    protected function useFactory($factoryOptions) {
        $factoryClass = $factoryOptions['class'];
        /**
         * @var $factoryObject AbstractFlysystemFactory
         */
        $factoryObject = new $factoryClass();
        if($factoryObject instanceof AbstractFlysystemFactory ) {

            $factoryObject->setServiceLocator($this->getServiceLocator());
            return  $factoryObject($factoryOptions['options']);
        } else {
            $this->logWarning('Factory ' .$factoryClass . ' Must extends AbstractFlysystemFactory');
        }
    }

    /**
     * (non-PHPdoc)
     * @see \oat\oatbox\filesystem\FlyWrapperTrait::getAdapter()
     */
    public function getAdapter()
    {
        if (is_null($this->adapter)) {

            if($this->hasOption(self::OPTION_FACTORY)) {

                $factory = $this->getOption(self::OPTION_FACTORY);
                $adapter = $this->useFactory($factory);

            } else  {
                $adapter = new AwsS3Adapter($this->getClient(),$this->getOption(self::OPTION_BUCKET),$this->getOption(self::OPTION_PREFIX));
                if ($this->hasOption(self::OPTION_CACHE)) {
                    if (class_exists(LocalCacheAdapter::class)) {
                        $cached = new Local($this->getOption(self::OPTION_CACHE));
                        $adapter = new LocalCacheAdapter($adapter, $cached, true);
                    } else {
                        $this->logWarning('Cache specified but LocalCacheAdapter class not found');
                    }
                }
            }
            $this->adapter = $adapter;
        }
        return $this->adapter;
    }
}
