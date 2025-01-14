<?php

declare(strict_types=1);

namespace CommunityTranslation\Translatable;

use ArgumentCountError;
use CommunityTranslation\Entity\Translatable;
use RuntimeException;
use Throwable;
use ValueError;

enum StringFormat: string
{
    case Raw = 'raw';
    case PHP = 'php';

    public static function fromString(string $string): self
    {
        $num = substr_count($string, '%');
        if ($num === 0) {
            return self::PHP;
        }
        $samples = array_fill(0, $num, 1);
        try {
            sprintf($string, ... $samples);
        } catch (ValueError $_) {
            return self::Raw;
        } catch (ArgumentCountError $_) {
            // Good
        } catch (Throwable $x) {
            $xClass = get_class($x);

            throw new RuntimeException("Error checking the following string: {$string}\nThrowable class: {$xClass}\nMessage: {$x->getMessage()}");
        }

        return self::PHP;
    }

    public static function fromStrings(... $strings): self
    {
        $result = null;
        while ($strings !== []) {
            $string = array_shift($strings);
            $type = self::fromString($string);
            if ($type === self::Raw) {
                return $type;
            }
            if ($result === null) {
                $result = $type;
            } elseif ($result !== $type) {
                return self::Raw;
            }
        }

        return $result ?? self::PHP;
    }

    public static function fromTranslatable(Translatable $translatable): self
    {
        return self::fromStrings($translatable->getText(), $translatable->getPlural());
    }
}
