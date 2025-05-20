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
    public static function fromName(string $name): string{
        return match ($name) {
            'content' => self::CONTENT->value,
            'styles' => self::STYLES->value,
            'settings' => self::SETTINGS->value,
            'manifest' => self::MANIFEST->value,
            'pictures' => self::PICTURES->value,
            default => throw new \InvalidArgumentException("Invalid name: $name"),
        };
    }
}
