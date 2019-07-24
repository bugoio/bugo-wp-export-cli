<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInite88835c534047e7c7dd5791564ca4d82
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Test\\Markdownify\\' => 17,
        ),
        'M' => 
        array (
            'Markdownify\\' => 12,
        ),
        'L' => 
        array (
            'League\\HTMLToMarkdown\\' => 22,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Test\\Markdownify\\' => 
        array (
            0 => __DIR__ . '/..' . '/pixel418/markdownify/test',
        ),
        'Markdownify\\' => 
        array (
            0 => __DIR__ . '/..' . '/pixel418/markdownify/src',
        ),
        'League\\HTMLToMarkdown\\' => 
        array (
            0 => __DIR__ . '/..' . '/league/html-to-markdown/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInite88835c534047e7c7dd5791564ca4d82::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInite88835c534047e7c7dd5791564ca4d82::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}