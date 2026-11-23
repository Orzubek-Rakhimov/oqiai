<?php

namespace App\Enums;

/**
 * The fixed set of pictures a Child profile can use.
 */
enum Avatar: string
{
    case Fox = 'fox';
    case Owl = 'owl';
    case Bear = 'bear';
    case Rabbit = 'rabbit';
    case Cat = 'cat';
    case Lion = 'lion';

    /**
     * Get every avatar key, in display order.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::cases(), 'value');
    }
}
