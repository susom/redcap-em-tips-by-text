<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4da3a71110595c170e61a26769ed9749
{
    public static $prefixLengthsPsr4 = array (
        'B' => 
        array (
            'BenMorel\\GsmCharsetConverter\\' => 29,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'BenMorel\\GsmCharsetConverter\\' => 
        array (
            0 => __DIR__ . '/..' . '/benmorel/gsm-charset-converter/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4da3a71110595c170e61a26769ed9749::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4da3a71110595c170e61a26769ed9749::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
