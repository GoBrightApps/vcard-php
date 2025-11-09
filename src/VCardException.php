<?php

declare(strict_types=1);

namespace Bright\VCard;

use Exception;

/**
 * VCard Exception PHP Class.
 */
class VCardException extends Exception
{
    public static function elementAlreadyExists(string $element): self
    {
        return new self('You can only set "' . $element . '" once.');
    }

    public static function emptyURL(): self
    {
        return new self('Nothing returned from URL.');
    }

    public static function invalidImage(): self
    {
        return new self('Returned data is not an image.');
    }

    public static function outputDirectoryNotExists(): self
    {
        return new self('Output directory does not exist.');
    }
}
