<?php
/**
 * 2026 GF Experiences
 *
 * Outcome of validating one questionnaire submission — story 1.12.
 *
 * Errors are short codes, not sentences: this class has no access to
 * Module::l() (it is not a Module or a Controller, on purpose — the validator
 * it belongs to has to be unit-testable without the PrestaShop translation
 * machinery loaded), so the controller maps each code to a translated message
 * when it renders the form. The order errors are added in is the order fields
 * appear in the form, which is what AC-5's "focus moves to the first invalid
 * field" reads off firstInvalidField().
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFInquiryValidationResult
{
    /** @var array<string, string> Field name => error code, in the order added. */
    private $errors = [];

    /** @var array<string, mixed> Field name => cleaned value. */
    private $data = [];

    public function addError($field, $code)
    {
        $this->errors[$field] = $code;
    }

    public function set($field, $value)
    {
        $this->data[$field] = $value;
    }

    public function get($field, $default = null)
    {
        return array_key_exists($field, $this->data) ? $this->data[$field] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all()
    {
        return $this->data;
    }

    public function isValid()
    {
        return empty($this->errors);
    }

    /**
     * @return array<string, string> Field name => error code.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    public function errorCodeFor($field)
    {
        return isset($this->errors[$field]) ? $this->errors[$field] : null;
    }

    public function hasErrorFor($field)
    {
        return isset($this->errors[$field]);
    }

    /**
     * AC-5: focus moves to the first invalid field. "First" means first in
     * the form, which is the insertion order errors were added in, since the
     * validator checks fields in the same order they appear on the page.
     *
     * @return string|null
     */
    public function firstInvalidField()
    {
        $fields = array_keys($this->errors);

        return isset($fields[0]) ? $fields[0] : null;
    }
}
