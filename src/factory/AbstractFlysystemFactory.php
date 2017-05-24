<?php
/**
 * Created by PhpStorm.
 * User: christophe
 * Date: 24/05/17
 * Time: 11:10
 */

namespace oat\awsTools\factory;

use Zend\ServiceManager\ServiceLocatorAwareTrait;
use Zend\ServiceManager\ServiceLocatorAwareInterface;

abstract class AbstractFlysystemFactory implements ServiceLocatorAwareInterface
{

    use ServiceLocatorAwareTrait;



    abstract public function __invoke($options);

}