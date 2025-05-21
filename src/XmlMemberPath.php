<?php

namespace Tabula17\Satelles\Odf;
/**
 * An enumeration representing XML member paths used in a specific context.
 * Each case corresponds to a file path related to a defined XML component.
 *
 * The enumeration provides methods to map these cases to associated names and retrieve values
 * based on a given name.
 *
 * Cases:
 * - CONTENT: Represents the 'content.xml' path.
 * - STYLES: Represents the 'styles.xml' path.
 * - SETTINGS: Represents the 'settings.xml' path.
 * - MANIFEST: Represents the 'META-INF/manifest.xml' path.
 * - PICTURES: Represents the 'Pictures/' directory path.
 *
 * Methods:
 * - name: Maps the enumeration case to its corresponding simple name string.
 * - fromName: Retrieves the file path value from a given name. Throws an exception for invalid names.
 */
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
