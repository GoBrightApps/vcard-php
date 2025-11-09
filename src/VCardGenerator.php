<?php

declare(strict_types=1);

namespace Bright\VCard;

use Bright\VCard\Transliterator;
use finfo;

/**
 * VCard PHP Class to generate .vcard files and save them to a file or output as a download.
 */
class VCardGenerator
{
    /**
     * Default Charset
     *
     * @var string
     */
    public $charset = 'utf-8';

    /**
     * definedElements
     */
    private ?array $definedElements = null;

    /**
     * Filename
     *
     * @var string
     */
    private $filename;

    /**
     * Save Path
     */
    private ?string $savePath = null;

    /**
     * Multiple properties for element allowed
     */
    private array $multiplePropertiesForElementAllowed = [
        'email',
        'address',
        'phoneNumber',
        'url',
        'label',
    ];

    /**
     * Properties
     */
    private ?array $properties = null;

    /**
     * Add address
     *
     * @param  string [optional] $name
     * @param  string [optional] $extended
     * @param  string [optional] $street
     * @param  string [optional] $city
     * @param  string [optional] $region
     * @param  string [optional] $zip
     * @param  string [optional] $country
     * @param  string [optional] $type
     *                                     $type may be DOM | INTL | POSTAL | PARCEL | HOME | WORK
     *                                     or any combination of these: e.g. "WORK;PARCEL;POSTAL"
     * @return $this
     */
    public function addAddress(
        string $name = '',
        string $extended = '',
        string $street = '',
        string $city = '',
        string $region = '',
        string $zip = '',
        string $country = '',
        $type = 'WORK;POSTAL'
    ): static {
        // init value
        $value = $name . ';' . $extended . ';' . $street . ';' . $city . ';' . $region . ';' . $zip . ';' . $country;

        // set property
        $this->setProperty(
            'address',
            'ADR' . (($type !== '') ? ';' . $type : '') . $this->getCharsetString(),
            $value
        );

        return $this;
    }

    /**
     * Add birthday
     *
     * @param  string  $date  Format is YYYY-MM-DD
     * @return $this
     */
    public function addBirthday($date): static
    {
        $this->setProperty(
            'birthday',
            'BDAY',
            $date
        );

        return $this;
    }

    /**
     * Add company
     *
     * @param  string  $department
     * @return $this
     */
    public function addCompany(string $company, $department = ''): static
    {
        $this->setProperty(
            'company',
            'ORG' . $this->getCharsetString(),
            $company
                . ($department !== '' ? ';' . $department : '')
        );

        // if filename is empty, add to filename
        if ($this->filename === null) {
            $this->setFilename($company);
        }

        return $this;
    }

    /**
     * Add email
     *
     * @param  string  $address  The e-mail address
     * @param  string [optional] $type    The type of the email address
     *                                    $type may be  PREF | WORK | HOME
     *                                    or any combination of these: e.g. "PREF;WORK"
     * @return $this
     */
    public function addEmail($address, $type = ''): static
    {
        $this->setProperty(
            'email',
            'EMAIL;INTERNET' . (($type !== '') ? ';' . $type : ''),
            $address
        );

        return $this;
    }

    /**
     * Add jobtitle
     *
     * @param  string  $jobtitle  The jobtitle for the person.
     * @return $this
     */
    public function addJobtitle($jobtitle): static
    {
        $this->setProperty(
            'jobtitle',
            'TITLE' . $this->getCharsetString(),
            $jobtitle
        );

        return $this;
    }

    /**
     * Add a label
     *
     * @param  string  $label
     * @param  string  $type
     * @return $this
     */
    public function addLabel($label, $type = ''): static
    {
        $this->setProperty(
            'label',
            'LABEL' . ($type !== '' ? ';' . $type : '') . $this->getCharsetString(),
            $label
        );

        return $this;
    }

    /**
     * Add role
     *
     * @param  string  $role  The role for the person.
     * @return $this
     */
    public function addRole($role): static
    {
        $this->setProperty(
            'role',
            'ROLE' . $this->getCharsetString(),
            $role
        );

        return $this;
    }

    /**
     * Add name
     *
     * @param  string [optional] $lastName
     * @param  string [optional] $firstName
     * @param  string [optional] $additional
     * @param  string [optional] $prefix
     * @param  string [optional] $suffix
     * @return $this
     */
    public function addName(
        string $lastName = '',
        string $firstName = '',
        string $additional = '',
        string $prefix = '',
        string $suffix = ''
    ): static {
        // define values with non-empty values
        $values = array_filter([
            $prefix,
            $firstName,
            $additional,
            $lastName,
            $suffix,
        ]);

        // define filename
        $this->setFilename($values);

        // set property
        $property = $lastName . ';' . $firstName . ';' . $additional . ';' . $prefix . ';' . $suffix;
        $this->setProperty(
            'name',
            'N' . $this->getCharsetString(),
            $property
        );

        // is property FN set?
        if (! $this->hasProperty('FN')) {
            // set property
            $this->setProperty(
                'fullname',
                'FN' . $this->getCharsetString(),
                trim(implode(' ', $values))
            );
        }

        return $this;
    }

    /**
     * Add note
     *
     * @param  string  $note
     * @return $this
     */
    public function addNote($note): static
    {
        $this->setProperty(
            'note',
            'NOTE' . $this->getCharsetString(),
            $note
        );

        return $this;
    }

    /**
     * Add categories
     *
     * @param  array  $categories
     * @return $this
     */
    public function addCategories($categories): static
    {
        $this->setProperty(
            'categories',
            'CATEGORIES' . $this->getCharsetString(),
            trim(implode(',', $categories))
        );

        return $this;
    }

    /**
     * Add phone number
     *
     * @param  string  $number
     * @param  string [optional] $type
     *                                   Type may be PREF | WORK | HOME | VOICE | FAX | MSG |
     *                                   CELL | PAGER | BBS | CAR | MODEM | ISDN | VIDEO
     *                                   or any senseful combination, e.g. "PREF;WORK;VOICE"
     * @return $this
     */
    public function addPhoneNumber($number, $type = ''): static
    {
        $this->setProperty(
            'phoneNumber',
            'TEL' . (($type !== '') ? ';' . $type : ''),
            $number
        );

        return $this;
    }

    /**
     * Add Logo
     *
     * @param  string  $url  image url or filename
     * @param  bool  $include  Include the image in our vcard?
     * @return $this
     */
    public function addLogo($url, $include = true): static
    {
        $this->addMedia(
            'LOGO',
            $url,
            'logo',
            $include
        );

        return $this;
    }

    /**
     * Add Logo content
     *
     * @param  string  $content  image content
     * @return $this
     */
    public function addLogoContent($content): static
    {
        $this->addMediaContent(
            'LOGO',
            $content,
            'logo'
        );

        return $this;
    }

    /**
     * Add Photo
     *
     * @param  string  $url  image url or filename
     * @param  bool  $include  Include the image in our vcard?
     * @return $this
     */
    public function addPhoto($url, $include = true): static
    {
        $this->addMedia(
            'PHOTO',
            $url,
            'photo',
            $include
        );

        return $this;
    }

    /**
     * Add Photo content
     *
     * @param  string  $content  image content
     * @return $this
     */
    public function addPhotoContent($content): static
    {
        $this->addMediaContent(
            'PHOTO',
            $content,
            'photo'
        );

        return $this;
    }

    /**
     * Add URL
     *
     * @param  string  $url
     * @param  string [optional] $type Type may be WORK | HOME
     * @return $this
     */
    public function addURL($url, $type = ''): static
    {
        $this->setProperty(
            'url',
            'URL' . (($type !== '') ? ';' . $type : ''),
            $url
        );

        return $this;
    }

    /**
     * Build VCard (.vcf)
     */
    public function buildVCard(): string
    {
        // init string
        $string = "BEGIN:VCARD\r\n";
        $string .= "VERSION:3.0\r\n";
        $string .= 'REV:' . date('Y-m-d') . 'T' . date('H:i:s') . "Z\r\n";

        // loop all properties
        $properties = $this->getProperties();
        foreach ($properties as $property) {
            // add to string
            $string .= $this->fold($property['key'] . ':' . $this->escape($property['value'])) . "\r\n";
        }

        // add to string
        $string .= "END:VCARD\r\n";

        // return
        return $string;
    }

    /**
     * Build VCalender (.ics) - Safari (< iOS 8) can not open .vcf files, so we have build a workaround.
     */
    public function buildVCalendar(): string
    {
        // init dates
        $dtstart = date('Ymd') . 'T' . date('Hi') . '00';
        $dtend = date('Ymd') . 'T' . date('Hi') . '01';

        // init string
        $string = "BEGIN:VCALENDAR\n";
        $string .= "VERSION:2.0\n";
        $string .= "BEGIN:VEVENT\n";
        $string .= 'DTSTART;TZID=Europe/London:' . $dtstart . "\n";
        $string .= 'DTEND;TZID=Europe/London:' . $dtend . "\n";
        $string .= "SUMMARY:Click attached contact below to save to your contacts\n";
        $string .= 'DTSTAMP:' . $dtstart . "Z\n";
        $string .= "ATTACH;VALUE=BINARY;ENCODING=BASE64;FMTTYPE=text/directory;\n";
        $string .= ' X-APPLE-FILENAME=' . $this->getFilename() . '.' . $this->getFileExtension() . ":\n";

        // base64 encode it so that it can be used as an attachemnt to the "dummy" calendar appointment
        $b64vcard = base64_encode($this->buildVCard());

        // chunk the single long line of b64 text in accordance with RFC2045
        // (and the exact line length determined from the original .ics file exported from Apple calendar
        $b64mline = chunk_split($b64vcard, 74, "\n");

        // need to indent all the lines by 1 space for the iphone (yes really?!!)
        $b64final = preg_replace('/(.+)/', ' $1', $b64mline);
        $string .= $b64final;

        // output the correctly formatted encoded text
        $string .= "END:VEVENT\n";

        // return
        return $string . "END:VCALENDAR\n";
    }

    /**
     * Download a vcard or vcal file to the browser.
     */
    public function download(): void
    {
        // define output
        $output = $this->getOutput();

        foreach ($this->getHeaders(false) as $header) {
            header($header);
        }

        // echo the output and it will be a download
        echo $output;
    }

    /**
     * Get output as string
     *
     * @deprecated in the future
     */
    public function get(): string
    {
        return $this->getOutput();
    }

    /**
     * Get charset
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Get charset string
     */
    public function getCharsetString(): string
    {
        return ';CHARSET=' . $this->charset;
    }

    /**
     * Get content type
     */
    public function getContentType(): string
    {
        return ($this->isIOS7()) ?
            'text/x-vcalendar' : 'text/x-vcard';
    }

    /**
     * Get filename
     *
     * @return string
     */
    public function getFilename()
    {
        if (! $this->filename) {
            return 'unknown';
        }

        return $this->filename;
    }

    /**
     * Get file extension
     */
    public function getFileExtension(): string
    {
        return ($this->isIOS7()) ?
            'ics' : 'vcf';
    }

    /**
     * Get headers
     *
     * @param  bool  $asAssociative
     */
    public function getHeaders($asAssociative): array
    {
        $contentType = $this->getContentType() . '; charset=' . $this->getCharset();
        $contentDisposition = 'attachment; filename=' . $this->getFilename() . '.' . $this->getFileExtension();
        $contentLength = mb_strlen($this->getOutput(), '8bit');
        $connection = 'close';

        if ((bool) $asAssociative) {
            return [
                'Content-type' => $contentType,
                'Content-Disposition' => $contentDisposition,
                'Content-Length' => $contentLength,
                'Connection' => $connection,
            ];
        }

        return [
            'Content-type: ' . $contentType,
            'Content-Disposition: ' . $contentDisposition,
            'Content-Length: ' . $contentLength,
            'Connection: ' . $connection,
        ];
    }

    /**
     * Get output as string
     * iOS devices (and safari < iOS 8 in particular) can not read .vcf (= vcard) files.
     * So I build a workaround to build a .ics (= vcalender) file.
     */
    public function getOutput(): string
    {
        return ($this->isIOS7()) ?
            $this->buildVCalendar() : $this->buildVCard();
    }

    /**
     * Get properties
     */
    public function getProperties(): ?array
    {
        return $this->properties;
    }

    /**
     * Has property
     *
     * @param  string  $key
     */
    public function hasProperty($key): bool
    {
        $properties = $this->getProperties();

        foreach ($properties as $property) {
            if ($property['key'] === $key && $property['value'] !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Is iOS - Check if the user is using an iOS-device
     */
    public function isIOS(): bool
    {
        // get user agent
        $browser = $this->getUserAgent();

        return mb_strpos($browser, 'iphone') || mb_strpos($browser, 'ipod') || mb_strpos($browser, 'ipad');
    }

    /**
     * Is iOS less than 7 (should cal wrapper be returned)
     */
    public function isIOS7(): bool
    {
        return $this->isIOS() && $this->shouldAttachmentBeCal();
    }

    /**
     * Save to a file
     */
    public function save(): void
    {
        $file = $this->getFilename() . '.' . $this->getFileExtension();

        // Add save path if given
        if ($this->savePath !== null) {
            $file = $this->savePath . $file;
        }

        file_put_contents(
            $file,
            $this->getOutput()
        );
    }

    /**
     * Set charset
     *
     * @param  mixed  $charset
     */
    public function setCharset($charset): void
    {
        $this->charset = $charset;
    }

    /**
     * Set filename
     *
     * @param  mixed  $value
     * @param  bool  $overwrite  [optional] Default overwrite is true
     * @param  string  $separator  [optional] Default separator is  '-'
     */
    public function setFilename($value, $overwrite = true, string $separator = '-'): void
    {
        // recast to string if $value is array
        if (is_array($value)) {
            $value = implode($separator, $value);
        }

        // trim unneeded values
        $value = trim((string) $value, $separator);

        // remove all spaces
        $value = preg_replace('/\s+/', $separator, $value);

        // if value is empty, stop here
        if (empty($value)) {
            return;
        }

        // decode value + lowercase the string
        $value = mb_strtolower($this->decode($value));

        // urlize this part
        $value = Transliterator::urlize($value);

        // overwrite filename or add to filename using a prefix in between
        $this->filename = ($overwrite) ?
            $value : $this->filename . $separator . $value;
    }

    /**
     * Set the save path directory
     *
     * @param  string  $savePath  Save Path
     *
     * @throws VCardException
     */
    public function setSavePath(string $savePath): void
    {
        Vutils::throw_unless(is_dir($savePath), VCardException::outputDirectoryNotExists());

        // Add trailing directory separator the save path
        if (mb_substr($savePath, -1) !== DIRECTORY_SEPARATOR) {
            $savePath .= DIRECTORY_SEPARATOR;
        }

        $this->savePath = $savePath;
    }

    /**
     * Returns the browser user agent string.
     */
    protected function getUserAgent(): string
    {
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            return mb_strtolower((string) ($_REQUEST['HTTP_USER_AGENT'] ?? ''));
        }

        return 'unknown';
    }

    /**
     * Fold a line according to RFC2425 section 5.8.1.
     *
     * @link http://tools.ietf.org/html/rfc2425#section-5.8.1
     *
     * @param  string  $text
     * @return mixed
     */
    protected function fold($text)
    {
        if (mb_strlen($text) <= 75) {
            return $text;
        }

        // The chunk_split_unicode creates a huge memory footprint when used on long strings (EG photos are base64 10MB results in > 1GB memory usage)
        // So check if the string is ASCII (7 bit) and if it is use the built in way RE: https://github.com/jeroendesloovere/vcard/issues/153
        if ($this->is_ascii($text)) {
            return mb_substr(chunk_split($text, 75, "\r\n "), 0, -3);
        }

        // split, wrap and trim trailing separator
        return mb_substr($this->chunk_split_unicode($text, 75, "\r\n "), 0, -3);
    }

    /**
     * Determine if string is pure 7bit ascii
     *
     * @link https://pageconfig.com/post/how-to-validate-ascii-text-in-php
     *
     * @param  string  $string
     */
    protected function is_ascii($string = ''): bool
    {
        $num = 0;
        while (isset($string[$num])) {
            if ((ord($string[$num]) & 0x80) !== 0) {
                return false;
            }
            $num++;
        }

        return true;
    }

    /**
     * multibyte word chunk split
     *
     * @link http://php.net/manual/en/function.chunk-split.php#107711
     *
     * @param  string  $body  The string to be chunked.
     * @param  int  $chunklen  The chunk length.
     * @param  string  $end  The line ending sequence.
     * @return string Chunked string
     */
    protected function chunk_split_unicode($body, $chunklen = 76, string $end = "\r\n"): string
    {
        $array = array_chunk(
            preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY),
            $chunklen
        );
        $body = '';
        foreach ($array as $item) {
            $body .= implode('', $item) . $end;
        }

        return $body;
    }

    /**
     * Escape newline characters according to RFC2425 section 5.8.4.
     *
     * @link http://tools.ietf.org/html/rfc2425#section-5.8.4
     *
     * @param  string  $text
     */
    protected function escape($text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = str_replace("\r\n", '\\n', $text);

        return str_replace("\n", '\\n', $text);
    }

    /**
     * Checks if we should return vcard in cal wrapper
     */
    protected function shouldAttachmentBeCal(): bool
    {
        $browser = $this->getUserAgent();

        $matches = [];
        preg_match('/os (\d+)_(\d+)\s+/', $browser, $matches);
        $version = isset($matches[1]) ? ((int) $matches[1]) : 999;

        return $version < 8;
    }

    /**
     * Add a photo or logo (depending on property name)
     *
     * @param  string  $property  LOGO|PHOTO
     * @param  string  $url  image url or filename
     * @param  bool  $include  Do we include the image in our vcard or not?
     * @param  string  $element  The name of the element to set
     *
     * @throws VCardException
     */
    private function addMedia(string $property, $url, string $element, $include = true): void
    {
        $mimeType = null;

        // Is this URL for a remote resource?
        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $headers = get_headers($url, true);

            if (array_key_exists('Content-Type', $headers)) {
                $mimeType = $headers['Content-Type'];
                if (is_array($mimeType)) {
                    $mimeType = end($mimeType);
                }
            }
        } else {
            // Local file, so inspect it directly
            $mimeType = mime_content_type($url);
        }
        if (mb_strpos((string) $mimeType, ';') !== false) {
            $mimeType = mb_strstr((string) $mimeType, ';', true);
        }

        Vutils::throw_if(! is_string($mimeType) || mb_substr($mimeType, 0, 6) !== 'image/', VCardException::invalidImage());

        $fileType = mb_strtoupper(mb_substr($mimeType, 6));

        if ($include) {
            if ((bool) ini_get('allow_url_fopen')) {
                $value = file_get_contents($url);
            } else {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $value = curl_exec($curl);
                curl_close($curl);
            }
            Vutils::throw_unless($value, VCardException::emptyURL());
            $value = base64_encode($value);
            $property .= ';ENCODING=b;TYPE=' . $fileType;
        } elseif (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            $propertySuffix = ';VALUE=URL';
            $propertySuffix .= ';TYPE=' . mb_strtoupper($fileType);
            $property .= $propertySuffix;
            $value = $url;
        } else {
            $value = $url;
        }

        $this->setProperty(
            $element,
            $property,
            $value
        );
    }

    /**
     * Add a photo or logo (depending on property name)
     *
     * @param  string  $property  LOGO|PHOTO
     * @param  string  $content  image content
     * @param  string  $element  The name of the element to set
     */
    private function addMediaContent(string $property, $content, string $element): void
    {
        $finfo = new finfo();
        $mimeType = $finfo->buffer($content, FILEINFO_MIME_TYPE);

        if (mb_strpos($mimeType, ';') !== false) {
            $mimeType = mb_strstr($mimeType, ';', true);
        }
        Vutils::throw_if(! is_string($mimeType) || mb_substr($mimeType, 0, 6) !== 'image/', VCardException::invalidImage());
        $fileType = mb_strtoupper(mb_substr($mimeType, 6));

        $content = base64_encode($content);
        $property .= ';ENCODING=b;TYPE=' . $fileType;

        $this->setProperty(
            $element,
            $property,
            $content
        );
    }

    /**
     * Decode
     *
     * @param  string  $value  The value to decode
     * @return string decoded
     */
    private function decode($value)
    {
        // convert cyrlic, greek or other caracters to ASCII characters
        return Transliterator::transliterate($value);
    }

    /**
     * Set property
     *
     * @param  string  $element  The element name you want to set, f.e.: name, email, phoneNumber, ...
     * @param  string  $value
     *
     * @throws VCardException
     */
    private function setProperty(string $element, string $key, $value): void
    {
        Vutils::throw_if(! in_array($element, $this->multiplePropertiesForElementAllowed, true)
            && isset($this->definedElements[$element]), VCardException::elementAlreadyExists($element));

        // we define that we set this element
        $this->definedElements[$element] = true;

        // adding property
        $this->properties[] = [
            'key' => $key,
            'value' => $value,
        ];
    }
}
