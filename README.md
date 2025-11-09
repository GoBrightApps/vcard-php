# VCard Generator

A lightweight PHP package for generating `.vcf` (vCard) files — compatible with most contact applications. 
 
Includes convenient helpers for building vCards from models or plain data arrays.

## ⚙️ Requirements

-   PHP 8.1+

## Installation

Install via Composer:

```bash
composer require bright/vcard
```

## Quick Usage

### Create a simple vCard

```php
use Bright\VCard\VCard;

$vcard = VCard::make()
    ->addName('Doe', 'John')
    ->addEmail('john@example.com', 'WORK')
    ->addPhoneNumber('+1 555-1234', 'WORK')
    ->addCompany('Bright')
    ->addJobtitle('Developer')
    ->addAddress('', '', '123 Main St', 'New York', '', '10001', 'United States')
    ->addURL('https://linkedin.com/in/johndoe', 'LinkedIn')
    ->addNote('Software Developer at Bright')
    ->addFilename('John Doe');

$file = $vcard->getFilename() . '.vcf';
$vcard->save(); // Saves to disk
```

## File Output

By default, `.vcf` files are saved in the current working directory.  
You can change the save directory:

```php
$vcard->setSavePath(__DIR__ . '/exports/');
$vcard->save();
```

## Output response

```php
use Bright\VCard\VCard;

$vcard = VCard::make()
    ->addName('Doe', 'John')
    ->addEmail('john@example.com', 'WORK')
    ->addPhoneNumber('+1 555-1234', 'WORK')
    ->addCompany('Bright')
    ->addFilename('John Doe');

// Build the VCard
$output = $vcard->buildVCard();

// Set appropriate headers
$headers = $vcard->getHeaders(true);

// Output headers manually (instead of Laravel's response helper)
foreach ($headers as $key => $value) {
    header("$key: $value");
}

// Send the VCard data as the response
http_response_code(200);
echo $output;
exit;
```

## Testing

To run the test suite (powered by [Pest](https://pestphp.com)):

```bash
composer install
./vendor/bin/pest
```

## 📝 License

MIT License © [Bright](https://bright.it)
