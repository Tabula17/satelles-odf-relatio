<?php

namespace Tabula17\Satelles\Odf;

enum XmlMemberPath: string
{
    case CONTENT = 'content.xml';
    case STYLES = 'styles.xml';
    case SETTINGS = 'settings.xml';
    case MANIFEST = 'META-INF/manifest.xml';
    case PICTURES = 'Pictures/';

    public function name(): string
    {
        return match ($this) {
            self::CONTENT => 'content',
            self::STYLES => 'styles',
            self::SETTINGS => 'settings',
            self::MANIFEST => 'manifest',
            self::PICTURES => 'pictures',
        };
    }
}
