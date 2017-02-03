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
use oat\oatbox\filesystem\FileSystemService;
/**
 * 
 * @author Joel Bout
 */
class AwsFileSystemService extends FileSystemService
{
    const OPTION_DEFAULT_OPTIONS = 'defaultOptions';
    const OPTION_FIRST_PREFIX = 'first_prefix';

    public function createFileSystem($id, $subPath = null)
    {
        $prefix = $this->hasOption(self::OPTION_FIRST_PREFIX) ? $this->getOption(self::OPTION_FIRST_PREFIX) : '';
        $path = (is_null($subPath) ? \helpers_File::sanitizeInjectively($id) : trim($subPath, '/'));
        $adapters = $this->hasOption(self::OPTION_ADAPTERS) ? $this->getOption(self::OPTION_ADAPTERS) : array();
        $options = $this->getOption(self::OPTION_DEFAULT_OPTIONS);
        $options[AwsFlyWrapper::OPTION_PREFIX] = $prefix.$path;
        $adapters[$id] = array(
            'class' => AwsFlyWrapper::class,
            'options' => [$options]
        );
        $this->setOption(self::OPTION_ADAPTERS, $adapters);
        return $this->getFileSystem($id);
    }
}