<?php

namespace Tabula17\Satelles\Odf;

enum XmlMember: string
{
    case CONTENT = 'content';
    case STYLES = 'styles';
    case SETTINGS = 'settings';
    case MANIFEST = 'manifest';
    // ...
}