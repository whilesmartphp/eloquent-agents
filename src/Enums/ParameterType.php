<?php

namespace Whilesmart\Agents\Enums;

enum ParameterType: string
{
    case STRING = 'string';
    case NUMBER = 'number';
    case BOOLEAN = 'boolean';
    case ENUM = 'enum';
    case ARRAY = 'array';
    case OBJECT = 'object';
}
