<?php
/**
 * 2026 GF Experiences
 *
 * Validates one questionnaire submission — story 1.12, AC-5.
 *
 * "Server-side validation is the source of truth" (Dev Notes): the form is
 * public and unauthenticated, so every rule here is enforced again
 * regardless of what the browser already checked. This class touches no
 * database and no PrestaShop translation machinery — it is handed the
 * catalogue data it needs to check against (valid countries, establishment
 * ids, partner ids) rather than fetching it, which is what makes it fast to
 * unit test and keeps the "what counts as valid" question in one place the
 * front controller and its tests both go through.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFInquiryValidator
{
    const OTHER_DEST_COUNTRY = 'OTHER';
    const OTHER_REFERRAL_SOURCE = 'other';

    const MAX_ADULTS = 20;
    const MAX_CHILDREN = 20;
    const MIN_TRAVEL_YEAR = 2026;
    const MAX_TRAVEL_YEAR = 2030;

    /**
     * @param  array $input   Raw request values, as Tools::getValue() returns
     *                        them — strings, untrimmed, unescaped.
     * @param  array $context [
     *                          'home_countries'    => string[] valid ISO codes,
     *                          'dest_countries'     => string[] valid destination names (not incl. OTHER),
     *                          'establishment_ids'  => int[] valid establishment ids,
     *                          'partner_ids'        => int[] valid partner ids,
     *                          'referral_sources'   => string[] valid option values (not incl. "other"),
     *                        ]
     * @return GFInquiryValidationResult
     */
    public function validate(array $input, array $context)
    {
        $result = new GFInquiryValidationResult();

        $this->validateGeneral($input, $context, $result);
        $this->validateDestination($input, $context, $result);
        $this->validateExtra($input, $context, $result);

        return $result;
    }

    private function validateGeneral(array $input, array $context, GFInquiryValidationResult $result)
    {
        $this->requiredText($input, 'first_name', 128, $result);
        $this->requiredText($input, 'last_name', 128, $result);

        $email = trim((string) $this->value($input, 'email'));
        if ($email === '') {
            $result->addError('email', 'required');
        } elseif (!Validate::isEmail($email)) {
            $result->addError('email', 'invalid');
        } else {
            $result->set('email', $email);
        }

        $result->set('phone', trim((string) $this->value($input, 'phone')));

        $homeCountry = trim((string) $this->value($input, 'home_country'));
        if ($homeCountry === '') {
            $result->addError('home_country', 'required');
        } elseif (!in_array($homeCountry, $context['home_countries'], true)) {
            $result->addError('home_country', 'invalid');
        } else {
            $result->set('home_country', $homeCountry);
        }

        $result->set('home_city', trim((string) $this->value($input, 'home_city')));

        $partnerId = (int) $this->value($input, 'id_gf_partner');
        if ($partnerId > 0 && !in_array($partnerId, $context['partner_ids'], true)) {
            $result->addError('id_gf_partner', 'invalid');
        } else {
            $result->set('id_gf_partner', $partnerId > 0 ? $partnerId : null);
        }

        $promoCode = trim((string) $this->value($input, 'promo_code'));
        if (Tools::strlen($promoCode) > 7) {
            $result->addError('promo_code', 'too_long');
        } else {
            $result->set('promo_code', $promoCode);
        }
    }

    private function validateDestination(array $input, array $context, GFInquiryValidationResult $result)
    {
        $destCountry = trim((string) $this->value($input, 'dest_country'));
        $validDestCountries = array_merge($context['dest_countries'], [self::OTHER_DEST_COUNTRY]);

        if ($destCountry === '') {
            $result->addError('dest_country', 'required');
        } elseif (!in_array($destCountry, $validDestCountries, true)) {
            $result->addError('dest_country', 'invalid');
        } else {
            $result->set('dest_country', $destCountry);
        }

        $establishmentId = (int) $this->value($input, 'id_product');
        if ($establishmentId > 0 && !in_array($establishmentId, $context['establishment_ids'], true)) {
            $result->addError('id_product', 'invalid');
        } else {
            $result->set('id_product', $establishmentId > 0 ? $establishmentId : null);
        }

        $result->set('travel_date', $this->validateTravelDate($input, $result));

        $duration = trim((string) $this->value($input, 'duration'));
        if (Tools::strlen($duration) > 64) {
            $result->addError('duration', 'too_long');
        } else {
            $result->set('duration', $duration);
        }

        $result->set('adults', $this->validateCount($input, 'adults', self::MAX_ADULTS, $result));
        $result->set('children', $this->validateCount($input, 'children', self::MAX_CHILDREN, $result));
    }

    private function validateExtra(array $input, array $context, GFInquiryValidationResult $result)
    {
        $result->set('best_time_call', trim((string) $this->value($input, 'best_time_call')));

        $referral = trim((string) $this->value($input, 'referral_source'));

        if ($referral === self::OTHER_REFERRAL_SOURCE) {
            $other = trim((string) $this->value($input, 'referral_source_other'));

            if ($other === '') {
                $result->addError('referral_source_other', 'required');
            } elseif (Tools::strlen($other) > 128) {
                $result->addError('referral_source_other', 'too_long');
            } else {
                $result->set('referral_source', $other);
            }
        } elseif ($referral !== '' && !in_array($referral, $context['referral_sources'], true)) {
            $result->addError('referral_source', 'invalid');
        } else {
            $result->set('referral_source', $referral);
        }

        $message = trim((string) $this->value($input, 'message'));
        if (Tools::strlen($message) > 2000) {
            $result->addError('message', 'too_long');
        } else {
            $result->set('message', $message);
        }

        if ((string) $this->value($input, 'terms') !== '1') {
            $result->addError('terms', 'required');
        }
    }

    /**
     * Combines the three travel-date selects into one Y-m-d string, or null
     * when the guest left them all blank — the field is optional.
     *
     * @return string|null
     */
    private function validateTravelDate(array $input, GFInquiryValidationResult $result)
    {
        $day = trim((string) $this->value($input, 'travel_day'));
        $month = trim((string) $this->value($input, 'travel_month'));
        $year = trim((string) $this->value($input, 'travel_year'));

        if ($day === '' && $month === '' && $year === '') {
            return null;
        }

        if ($day === '' || $month === '' || $year === '') {
            $result->addError('travel_date', 'incomplete');

            return null;
        }

        $year = (int) $year;
        if ($year < self::MIN_TRAVEL_YEAR || $year > self::MAX_TRAVEL_YEAR) {
            $result->addError('travel_date', 'invalid');

            return null;
        }

        $candidate = sprintf('%04d-%02d-%02d', $year, (int) $month, (int) $day);

        if (!Validate::isDate($candidate)) {
            $result->addError('travel_date', 'invalid');

            return null;
        }

        return $candidate;
    }

    /**
     * @return int|null
     */
    private function validateCount(array $input, $field, $max, GFInquiryValidationResult $result)
    {
        $raw = trim((string) $this->value($input, $field));

        if ($raw === '') {
            return null;
        }

        if (!ctype_digit($raw) || (int) $raw > $max) {
            $result->addError($field, 'invalid');

            return null;
        }

        return (int) $raw;
    }

    private function requiredText(array $input, $field, $maxLength, GFInquiryValidationResult $result)
    {
        $value = trim((string) $this->value($input, $field));

        if ($value === '') {
            $result->addError($field, 'required');

            return;
        }

        if (Tools::strlen($value) > $maxLength) {
            $result->addError($field, 'too_long');

            return;
        }

        $result->set($field, $value);
    }

    private function value(array $input, $key)
    {
        return isset($input[$key]) ? $input[$key] : '';
    }
}
