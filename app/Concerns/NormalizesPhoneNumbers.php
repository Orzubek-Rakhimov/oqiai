<?php

namespace App\Concerns;

trait NormalizesPhoneNumbers
{
    /**
     * Bring an Uzbek phone number typed with or without +998, spaces, dashes
     * or brackets into its canonical "+998XXXXXXXXX" form.
     *
     * Input that cannot be read as an Uzbek number is returned unchanged, so
     * that validation can reject it.
     */
    protected function normalizePhoneNumber(mixed $phone): mixed
    {
        if (! is_string($phone)) {
            return $phone;
        }

        $digits = preg_replace('/[\s\-().]+/', '', trim($phone));

        if (preg_match('/^(?:\+?998)?(\d{9})$/', $digits, $matches) === 1) {
            return '+998'.$matches[1];
        }

        return trim($phone);
    }
}
