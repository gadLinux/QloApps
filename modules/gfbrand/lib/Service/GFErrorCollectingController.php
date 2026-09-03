<?php
/**
 * 2026 GF Experiences
 *
 * Stand-in controller that only exists to collect errors.
 *
 * Several QloApps model classes report failures by appending to
 * $context->controller->errors and then calling count() on it without
 * checking that a controller exists. That is fine in a web request and fatal
 * on the CLI, in a cron job or under PHPUnit. Assigning one of these to the
 * context satisfies the contract without pulling in a real controller.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFErrorCollectingController
{
    /** @var string[] Errors the model classes append to. */
    public $errors = [];

    /** @var string[] Some classes report confirmations the same way. */
    public $confirmations = [];

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
}
