<?php

namespace HdBase\Module;

use ZfcBase\Module\AbstractModule as ZfcBaseAbstractModule;

abstract class AbstractModule extends ZfcBaseAbstractModule
{

    public function getConfig()
    {
        $config = include $this->getDir() . '/config/module.config.php';
        $config = array_merge($config, $this->getDoctrineConfig());
        $config = array_merge($config, $this->getAssetConfig());
        return $config;
    }

    public function getDoctrineConfig()
    {
        return array(
            'doctrine' => array(
                'driver' => array(
                    $this->getNamespace() . '_driver' => array(
                        'class' =>
                            'Doctrine\ORM\Mapping\Driver\AnnotationDriver',
                        'cache' => 'array',
                        'paths' =>
                        array(
                            $this->getDir()
                            . '/src/'
                            . $this->getNamespace()
                            . '/Entity')
                    ),
                    'orm_default' => array(
                        'drivers' => array(
                            $this->getNamespace() . '\Entity' =>
                            $this->getNamespace() . '_driver'
                        )
                    )
                ),
            ),
        );
    }

    public function getAssetConfig()
    {
        return array(
            'asset_manager' => array(
                'resolver_configs' => array(
                    'paths' => array(
                        $this->getDir() . '/./public',
                    ),
                ),
            ),
        );
    }
}