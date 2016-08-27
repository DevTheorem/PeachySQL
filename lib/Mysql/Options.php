<?php

namespace PeachySQL\Mysql;

use PeachySQL\BaseOptions;

/**
 * Handles MySQL-specific options
 */
class Options extends BaseOptions
{
    protected $maxBoundParams = 65536; // 2^16

    // use backticks to delimit identifiers since not everyone uses ANSI mode
    public function escapeIdentifier($identifier)
    {
        if (gettype($identifier) !== 'string') {
            throw new \InvalidArgumentException('Identifier must be a string');
        } elseif ($identifier === '') {
            throw new \InvalidArgumentException('Identifier cannot be blank');
        }

        $escaper = function ($identifier) { return '`' . str_replace('`', '``', $identifier) . '`'; };
        $qualifiedIdentifiers = array_map($escaper, explode('.', $identifier));
        return implode('.', $qualifiedIdentifiers);
    }
}
