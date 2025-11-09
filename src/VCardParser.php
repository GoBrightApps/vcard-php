<?php

declare(strict_types=1);

namespace Bright\VCard;

/*
 * This file is part of the VCard PHP Class from Jeroen Desloovere.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use DateTimeImmutable;
use Exception;
use Iterator;
use OutOfBoundsException;
use RuntimeException;
use stdClass;

/**
 * VCard PHP Class to parse .vcard files.
 *
 * This class is heavily based on the Zendvcard project (seemingly abandoned),
 * which is licensed under the Apache 2.0 license.
 * More information can be found at https://code.google.com/archive/p/zendvcard/
 */
class VCardParser implements Iterator
{
    /**
     * The VCard data objects.
     *
     * @var array
     */
    protected $vcardObjects = [];

    /**
     * The iterator position.
     *
     * @var int
     */
    protected $position;

    /**
     * @param  string  $content
     */
    public function __construct(
        /**
         * The raw VCard content.
         */
        protected $content
    ) {
        $this->rewind();
        $this->parse();
    }

    /**
     * Helper function to parse a file directly.
     */
    public static function parseFromFile(string $filename): self
    {
        if (file_exists($filename) && is_readable($filename)) {
            return new self(file_get_contents($filename));
        }
        throw new RuntimeException(sprintf("File %s is not readable, or doesn't exist.", $filename));
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): stdClass
    {
        Vutils::throw_unless($this->valid(), RuntimeException::class, 'invalid');

        return $this->getCardAtIndex($this->position);
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function valid(): bool
    {
        return ! empty($this->vcardObjects[$this->position]);
    }

    /**
     * Fetch all the imported VCards.
     *
     * @return array
     *               A list of VCard card data objects.
     */
    public function getCards(): array
    {
        return $this->vcardObjects;
    }

    /**
     * Fetch the imported VCard at the specified index.
     *
     *
     * @param  int  $i
     * @return stdClass
     *                  The card data object.
     *
     * @throws OutOfBoundsException
     */
    public function getCardAtIndex($i): stdClass
    {
        if (isset($this->vcardObjects[$i])) {
            return $this->vcardObjects[$i];
        }
        throw new OutOfBoundsException();
    }

    /**
     * Start the parsing process.
     *
     * This method will populate the data object.
     */
    protected function parse()
    {
        // Normalize new lines.
        $this->content = str_replace(["\r\n", "\r"], "\n", $this->content);

        // RFC2425 5.8.1. Line delimiting and folding
        // Unfolding is accomplished by regarding CRLF immediately followed by
        // a white space character (namely HTAB ASCII decimal 9 or. SPACE ASCII
        // decimal 32) as equivalent to no characters at all (i.e., the CRLF
        // and single white space character are removed).
        $this->content = preg_replace("/\n(?:[ \t])/", '', $this->content);
        $lines = explode("\n", (string) $this->content);

        // Parse the VCard, line by line.
        foreach ($lines as $line) {
            $line = trim($line);

            if (mb_strtoupper($line) === 'BEGIN:VCARD') {
                $cardData = new stdClass();
            } elseif (mb_strtoupper($line) === 'END:VCARD') {
                if (isset($cardData)) {
                    $this->vcardObjects[] = $cardData;
                    unset($cardData); // Ensure $cardData is unset after use
                }
            } elseif (isset($cardData) && $line !== '' && $line !== '0') {
                // Strip grouping information. We don't use the group names. We
                // simply use a list for entries that have multiple values.
                // As per RFC, group names are alphanumerical, and end with a
                // period (.).
                $line = preg_replace('/^\w+\./', '', $line);

                $type = '';
                $value = '';
                @[$type, $value] = explode(':', (string) $line, 2);

                $types = explode(';', $type);
                $element = mb_strtoupper($types[0]);

                array_shift($types);

                // Normalize types. A type can either be a type-param directly,
                // or can be prefixed with "type=". E.g.: "INTERNET" or
                // "type=INTERNET".
                if ($types !== []) {
                    $types = array_map(fn($type): ?string => preg_replace('/^type=/i', '', $type), $types);
                }

                $i = 0;
                $rawValue = false;
                foreach ($types as $type) {
                    if (preg_match('/base64/', mb_strtolower((string) $type))) {
                        $value = base64_decode($value, true);
                        unset($types[$i]);
                        $rawValue = true;
                    } elseif (preg_match('/encoding=b/', mb_strtolower((string) $type))) {
                        $value = base64_decode($value, true);
                        unset($types[$i]);
                        $rawValue = true;
                    } elseif (preg_match('/quoted-printable/', mb_strtolower((string) $type))) {
                        $value = quoted_printable_decode($value);
                        unset($types[$i]);
                        $rawValue = true;
                    } elseif (mb_strpos(mb_strtolower((string) $type), 'charset=') === 0) {
                        try {
                            $value = mb_convert_encoding($value, 'UTF-8', mb_substr((string) $type, 8));
                        } catch (Exception) {
                        }
                        unset($types[$i]);
                    }
                    $i++;
                }

                switch (mb_strtoupper($element)) {
                    case 'FN':
                        $cardData->fullname = $value;
                        break;
                    case 'N':
                        foreach ($this->parseName($value) as $key => $val) {
                            $cardData->{$key} = $val;
                        }
                        break;
                    case 'BDAY':
                        $cardData->birthday = $this->parseBirthday($value);
                        break;
                    case 'ADR':
                        if (! isset($cardData->address)) {
                            $cardData->address = [];
                        }
                        $key = $types === [] ? 'WORK;POSTAL' : implode(';', $types);
                        $cardData->address[$key][] = $this->parseAddress($value);
                        break;
                    case 'TEL':
                        if (! isset($cardData->phone)) {
                            $cardData->phone = [];
                        }
                        $key = $types === [] ? 'default' : implode(';', $types);
                        $cardData->phone[$key][] = $value;
                        break;
                    case 'EMAIL':
                        if (! isset($cardData->email)) {
                            $cardData->email = [];
                        }
                        $key = $types === [] ? 'default' : implode(';', $types);
                        $cardData->email[$key][] = $value;
                        break;
                    case 'REV':
                        $cardData->revision = $value;
                        break;
                    case 'VERSION':
                        $cardData->version = $value;
                        break;
                    case 'ORG':
                        $cardData->organization = $value;
                        break;
                    case 'URL':
                        if (! isset($cardData->url)) {
                            $cardData->url = [];
                        }
                        $key = $types === [] ? 'default' : implode(';', $types);
                        $cardData->url[$key][] = $value;
                        break;
                    case 'TITLE':
                        $cardData->title = $value;
                        break;
                    case 'PHOTO':
                        if ($rawValue) {
                            $cardData->rawPhoto = $value;
                        } else {
                            $cardData->photo = $value;
                        }
                        break;
                    case 'LOGO':
                        if ($rawValue) {
                            $cardData->rawLogo = $value;
                        } else {
                            $cardData->logo = $value;
                        }
                        break;
                    case 'NOTE':
                        $cardData->note = $this->unescape($value);
                        break;
                    case 'CATEGORIES':
                        $cardData->categories = array_map(trim(...), explode(',', $value));
                        break;
                    case 'LABEL':
                        $cardData->label = $value;
                        break;
                }
            }
        }
    }

    protected function parseName($value)
    {
        @[
            $lastname,
            $firstname,
            $additional,
            $prefix,
            $suffix
        ] = explode(';', (string) $value);

        return (object) [
            'lastname' => $lastname,
            'firstname' => $firstname,
            'additional' => $additional,
            'prefix' => $prefix,
            'suffix' => $suffix,
        ];
    }

    protected function parseBirthday($value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    protected function parseAddress($value)
    {
        @[
            $name,
            $extended,
            $street,
            $city,
            $region,
            $zip,
            $country,
        ] = explode(';', (string) $value);

        return (object) [
            'name' => $name,
            'extended' => $extended,
            'street' => $street,
            'city' => $city,
            'region' => $region,
            'zip' => $zip,
            'country' => $country,
        ];
    }

    /**
     * Unescape newline characters according to RFC2425 section 5.8.4.
     * This function will replace escaped line breaks with PHP_EOL.
     *
     * @link http://tools.ietf.org/html/rfc2425#section-5.8.4
     *
     * @param  string  $text
     */
    protected function unescape($text): string
    {
        return str_replace('\\n', PHP_EOL, $text);
    }
}
