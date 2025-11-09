<?php

declare(strict_types=1);

namespace Bright\VCard;



class VCard extends VCardGenerator
{
    /**
     * Create a new instance staticly
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Create a new vCard form model
     */
    public static function create($info): static
    {
        return (new self)
            ->addName($info->last_name, $info->first_name)
            ->addCategories([$info->big_group])
            ->addJobtitle($info->job_title)
            ->addAddress('', '', '', $info->city ?? '', '', '', 'United States')
            ->addEmail($info->email, 'WORK')
            ->when($info->phone, fn($o, $v): VCard => $o->addPhoneNumber($v, 'WORK'))
            ->when($info->company, fn($o, string $v): VCard => $o->addCompany($v))
            ->when($info->linkedin, fn($o, $v): VCard => $o->addURL($v, 'LinkedIn'))
            ->addNote(implode("\n", array_filter([
                "BIG: {$info->big_group} | ",
                $info->expertise ? "Work & Expertise: {$info->expertise} | " : null,
                $info->interests ? "Interests & Hobbies: {$info->interests}" : null,
            ])))
            ->when($info->picture, function ($object, $value): void {
                // if (Storage::disk('public')->exists($value)) {
                //     $object->addPhoto(Storage::disk('public')->path($value));
                // }
            })
            ->addFilename($info->name);
    }

    /**
     * Add filename
     */
    public function addFilename(string $name): static
    {
        $this->setFilename('vcard-' . $name, true);

        return $this;
    }

    /**
     * Conditionally apply a callback if the given value is truthy.
     *
     * @param  mixed  $value
     * @param  callable(self, mixed):mixed  $callback
     */
    public function when($value, callable $callback): self
    {
        if (!empty($value)) {
            $callback($this, $value);
        }

        return $this;
    }
}
