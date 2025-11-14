<?php

declare(strict_types=1);

use Bright\VCard\VCard;
use Bright\VCard\VCardException;
use Bright\VCard\Vutils;

//
// ─── VCard Creation and Setup ───────────────────────────────────────────────
//

it('creates a new instance using make', function () {
    $vcard = VCard::make();
    expect($vcard)->toBeInstanceOf(VCard::class);
});

it('adds filename correctly', function () {
    $vcard = VCard::make()->addFilename('John Doe');
    expect($vcard->getFilename())->toContain('vcard-john-doe');
});

//
// ─── Conditional Application (when) ───────────────────────────────────────────────
//

it('executes callback when value is truthy', function () {
    $vcard = VCard::make();

    $vcard->when('123', function (VCard $v, $value) {
        $v->addNote("Has value: {$value}");
    });

    $properties = $vcard->getProperties();

    expect($properties)->toBeArray();
    expect(array_column($properties, 'key'))->toContain('NOTE;CHARSET=utf-8');
});

it('does not execute callback when value is falsy', function () {
    $vcard = VCard::make();
    $vcard->when('', fn(VCard $v) => $v->addNote('Should not run'));
    expect($vcard->getProperties())->toBeNull();
});

//
// ─── Core Property Methods (from Generator) ───────────────────────────────────────────────
//

it('adds name and fullname correctly', function () {
    $vcard = VCard::make()->addName('Doe', 'John');
    $props = $vcard->getProperties();
    $keys = array_column($props, 'key');

    expect($keys)->toContain('N;CHARSET=utf-8')
        ->and($keys)->toContain('FN;CHARSET=utf-8');
});

it('adds email, phone, and company correctly', function () {
    $vcard = VCard::make()
        ->addEmail('john@example.com', 'WORK')
        ->addPhoneNumber('555-1234', 'HOME')
        ->addCompany('Bright');

    $values = array_column($vcard->getProperties(), 'value');
    expect($values)->toContain('john@example.com')
        ->and($values)->toContain('555-1234')
        ->and($values)->toContain('Bright');
});

it('adds job title, role, and categories', function () {
    $vcard = VCard::make()
        ->addJobtitle('Engineer')
        ->addRole('Team Lead')
        ->addCategories(['Development', 'Backend']);

    $values = array_column($vcard->getProperties(), 'value');

    expect($values)->toContain('Engineer')
        ->and($values)->toContain('Team Lead')
        ->and($values)->toContain('Development,Backend');
});

it('adds address with correct structure', function () {
    $vcard = VCard::make()->addAddress('', '', '123 Street', 'Paris', '', '75000', 'France');
    $props = $vcard->getProperties();
    $values = array_column($props, 'value');

    expect(implode(';', $values))->toContain('Paris');
    expect(implode(';', $values))->toContain('France');
});

it('adds URL and note fields', function () {
    $vcard = VCard::make()
        ->addURL('https://example.com', 'WORK')
        ->addNote('Example note content');

    $keys = array_column($vcard->getProperties(), 'key');
    $values = array_column($vcard->getProperties(), 'value');

    expect($keys)->toContain('URL;WORK')
        ->and($values)->toContain('https://example.com')
        ->and($values)->toContain('Example note content');
});

//
// ─── Build and Output ───────────────────────────────────────────────
//

it('builds a valid vCard string', function () {
    $vcard = VCard::make()
        ->addName('Doe', 'John')
        ->addEmail('john@example.com')
        ->addPhoneNumber('555-1234')
        ->addCompany('Bright');

    $out = $vcard->buildVCard();

    expect($out)->toContain('BEGIN:VCARD')
        ->and($out)->toContain('VERSION:3.0')
        ->and($out)->toContain('END:VCARD');
});

it('includes correct headers and metadata', function () {
    $vcard = VCard::make()->addName('Doe', 'John');
    $headers = $vcard->getHeaders(true);

    expect($headers)->toHaveKeys([
        'Content-type',
        'Content-Disposition',
        'Content-Length',
        'Connection'
    ]);
    expect($headers['Content-type'])->toContain('text/x-vcard');
});

//
// ─── Integration Test: Model() Factory ───────────────────────────────────────────────
//

it('builds a vCard correctly from a model object', function () {
    $info = (object) [
        'name' => 'Jane Doe',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'phone' => '555-6789',
        'job_title' => 'Designer',
        'big_group' => 'Creative',
        'city' => 'Berlin',
        'company' => 'Bright',
        'linkedin' => 'https://linkedin.com/in/janedoe',
        'expertise' => 'UX/UI',
        'interests' => 'Art, Photography',
        'picture' => null,
    ];

    $vcard = VCard::create($info);
    $output = $vcard->buildVCard();

    expect($output)
        ->toContain('BEGIN:VCARD')
        ->and($output)->toContain('EMAIL;INTERNET;WORK:jane@example.com')
        ->and($output)->toContain('TEL;WORK:555-6789')
        ->and($output)->toContain('FN;CHARSET=utf-8:Jane Doe')
        ->and($output)->toContain('ORG;CHARSET=utf-8:Bright')
        ->and($output)->toContain('NOTE;CHARSET=utf-8:BIG: Creative');
});

//
// ─── Validation and Exception Handling ───────────────────────────────────────────────
//

it('throws exception when duplicate element added', function () {
    $vcard = VCard::make();
    $vcard->addName('Doe', 'John');

    $this->expectException(VCardException::class);
    $vcard->addName('Smith', 'Jane'); // "name" element already defined
});

it('throws if invalid save path is set', function () {
    $vcard = VCard::make();

    $this->expectException(VCardException::class);
    $vcard->setSavePath('/path/does/not/exist');
});


it('can save vCard to disk', function () {
    $vcard = VCard::make()->addName('Doe', 'John');
    $vcard->setSavePath(__DIR__ . '/');

    $expectedFile = __DIR__ . '/' . $vcard->getFilename() . '.vcf';
    $vcard->save();

    expect(file_exists($expectedFile))->toBeTrue(); // fix file check

    // Cleanup
    if (file_exists($expectedFile)) {
        unlink($expectedFile);
    }
});
