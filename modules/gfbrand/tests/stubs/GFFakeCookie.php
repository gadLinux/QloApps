<?php
/**
 * 2026 GF Experiences
 *
 * Stands in for PrestaShop's Cookie in unit tests.
 *
 * Only the magic accessors matter to the search-preference repository, and
 * they are the whole of the contract it relies on — including the real
 * Cookie's refusal to store anything but a scalar.
 */

class GFFakeCookie
{
    /** @var array<string, string> */
    private $content = [];

    public function __get($key)
    {
        return isset($this->content[$key]) ? $this->content[$key] : false;
    }

    public function __isset($key)
    {
        return isset($this->content[$key]);
    }

    public function __set($key, $value)
    {
        if (is_array($value)) {
            throw new InvalidArgumentException('The real Cookie dies on array values.');
        }

        if (preg_match('/¤|\|/', $key . $value)) {
            throw new Exception('Forbidden chars in cookie');
        }

        $this->content[$key] = $value;
    }

    public function __unset($key)
    {
        unset($this->content[$key]);
    }
}
