<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2013 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Cityware\Db;

abstract class Factory
{
    /**
     * Adapter plugin manager
     * @var AdapterPluginManager
     */
    protected static $adapters;

    /**
     * @var array Known captcha types
     */
    protected static $classMap = array(
        'zend' => 'Cityware\Db\Adapter\ZendAdapter',
    );

    /**
     * Create a captcha adapter instance
     *
     * @param  array|Traversable                  $options
     * @return AdapterInterface
     * @throws Exception\InvalidArgumentException for a non-array, non-Traversable $options
     * @throws Exception\DomainException          if class is missing or invalid
     */
    public static function factory($adapter = 'zend')
    {
        if (!is_string($adapter)) {
            throw new \Cityware\Exception\InvalidArgumentException(sprintf(
                            '%s expects an string or Traversable argument; received "%s"', __METHOD__, (is_object($adapter) ? get_class($adapter) : gettype($adapter))
            ));
        }

        if (isset(static::$classMap[strtolower($adapter)])) {
            $class = static::$classMap[strtolower($adapter)];
        }

        if (!class_exists($class)) {
            throw new \Cityware\Exception\DomainException(
                    sprintf('%s expects the "class" to resolve to an existing class; received "%s"', __METHOD__, $class)
            );
        }

        switch (strtolower($adapter)) {
            case 'zend':
                return new Adapter\ZendAdapter();
                break;
            default:
                throw new \Cityware\Exception\InvalidArgumentException('Adaptador não definico');
                break;
        }
    }

}
