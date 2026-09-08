<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the questionnaire's server-side validation — story 1.12,
 * AC-5. The form is public and unauthenticated, so every rule here is the
 * one that actually stops a bad submission; the browser's own checks are a
 * convenience this test suite does not need to trust.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFInquiryValidator::class)]
#[CoversClass(GFInquiryValidationResult::class)]
class GFInquiryValidatorTest extends TestCase
{
    /** Fixed rather than the real current year, so this suite is not
     *  flaky as time passes — the validator's travel-date window slides
     *  against whatever year it is handed (see the sliding-window tests
     *  below), and every other test just needs *a* stable year to build
     *  submissions against. */
    const FIXED_YEAR = 2027;

    /** @var GFInquiryValidator */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new GFInquiryValidator(self::FIXED_YEAR);
    }

    private function context(array $overrides = [])
    {
        return array_merge([
            'home_countries' => ['US', 'CA', 'ES', 'CR'],
            'dest_countries' => ['Costa Rica', 'Canada', 'Spain', 'USA'],
            'establishment_ids' => [941, 942],
            'partner_ids' => [7, 9],
            'referral_sources' => ['search_engine', 'friend', 'advisor'],
        ], $overrides);
    }

    private function validSubmission(array $overrides = [])
    {
        return array_merge([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+1 555 0100',
            'home_country' => 'US',
            'home_city' => 'Boston',
            'id_gf_partner' => '',
            'promo_code' => '',
            'dest_country' => 'Costa Rica',
            'id_product' => '941',
            'travel_day' => '14',
            'travel_month' => '3',
            'travel_year' => '2027',
            'duration' => '10 days',
            'adults' => '2',
            'children' => '0',
            'best_time_call' => 'Evenings',
            'referral_source' => 'friend',
            'referral_source_other' => '',
            'message' => 'Looking for a dedicated gluten-free kitchen.',
            'terms' => '1',
        ], $overrides);
    }

    #[Test]
    public function a_fully_valid_submission_has_no_errors(): void
    {
        $result = $this->validator->validate($this->validSubmission(), $this->context());

        $this->assertTrue($result->isValid());
        $this->assertSame([], $result->getErrors());
    }

    #[Test]
    public function a_fully_valid_submission_carries_the_cleaned_data_through(): void
    {
        $result = $this->validator->validate($this->validSubmission(), $this->context());

        $this->assertSame('Ada', $result->get('first_name'));
        $this->assertSame('ada@example.com', $result->get('email'));
        $this->assertSame('US', $result->get('home_country'));
        $this->assertSame(941, $result->get('id_product'));
        $this->assertSame('2027-03-14', $result->get('travel_date'));
        $this->assertSame(2, $result->get('adults'));
        $this->assertSame(0, $result->get('children'));
    }

    #[Test]
    public function first_name_last_name_and_email_are_required(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['first_name' => '', 'last_name' => '', 'email' => '']),
            $this->context()
        );

        $this->assertSame('required', $result->errorCodeFor('first_name'));
        $this->assertSame('required', $result->errorCodeFor('last_name'));
        $this->assertSame('required', $result->errorCodeFor('email'));
    }

    #[Test]
    public function an_unparseable_email_is_rejected(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['email' => 'not-an-email']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('email'));
    }

    #[Test]
    public function home_country_must_be_one_of_the_offered_options_ac8(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['home_country' => 'ZZ']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('home_country'));
    }

    #[Test]
    public function home_country_is_required_because_it_is_a_select_not_free_text(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['home_country' => '']),
            $this->context()
        );

        $this->assertSame('required', $result->errorCodeFor('home_country'));
    }

    #[Test]
    public function dest_country_accepts_the_other_sentinel_even_though_it_is_not_in_the_catalogue(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['dest_country' => 'OTHER', 'id_product' => '']),
            $this->context()
        );

        $this->assertTrue($result->isValid());
        $this->assertSame('OTHER', $result->get('dest_country'));
    }

    #[Test]
    public function an_establishment_id_outside_the_catalogue_is_rejected_ac3(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['id_product' => '999999']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('id_product'));
    }

    #[Test]
    public function the_establishment_is_optional(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['id_product' => '']),
            $this->context()
        );

        $this->assertTrue($result->isValid());
        $this->assertNull($result->get('id_product'));
    }

    #[Test]
    public function no_javascript_dependency_between_country_and_establishment_ac10(): void
    {
        // A guest with JavaScript disabled cannot have re-narrowed the
        // establishment select after picking a country: the two fields must
        // validate independently, or AC-10's "degrades to showing all
        // establishments" would fail server-side the moment it is exercised.
        $result = $this->validator->validate(
            $this->validSubmission(['dest_country' => 'Canada', 'id_product' => '941']),
            $this->context()
        );

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function a_partial_travel_date_is_rejected_rather_than_silently_dropped(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['travel_day' => '14', 'travel_month' => '', 'travel_year' => '2027']),
            $this->context()
        );

        $this->assertSame('incomplete', $result->errorCodeFor('travel_date'));
    }

    #[Test]
    public function an_entirely_blank_travel_date_is_fine_the_field_is_optional(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['travel_day' => '', 'travel_month' => '', 'travel_year' => '']),
            $this->context()
        );

        $this->assertTrue($result->isValid());
        $this->assertNull($result->get('travel_date'));
    }

    #[Test]
    public function a_calendar_impossible_travel_date_is_rejected(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['travel_day' => '31', 'travel_month' => '2', 'travel_year' => '2027']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('travel_date'));
    }

    /** The window slides with the year it is built against (FIXED_YEAR + TRAVEL_YEAR_SPAN),
     *  so these tests speak in terms of the injected clock, never an absolute year. */
    #[Test]
    public function a_travel_year_before_the_current_year_is_rejected(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['travel_day' => '1', 'travel_month' => '1', 'travel_year' => (string) (self::FIXED_YEAR - 1)]),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('travel_date'));
    }

    #[Test]
    public function a_travel_year_past_the_window_is_rejected(): void
    {
        $validator = new GFInquiryValidator(self::FIXED_YEAR);
        $result = $validator->validate(
            $this->validSubmission(['travel_day' => '1', 'travel_month' => '1', 'travel_year' => (string) ($validator->maxTravelYear() + 1)]),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('travel_date'));
    }

    #[Test]
    public function the_travel_window_spans_travelling_years_from_now_to_the_span_limit(): void
    {
        $validator = new GFInquiryValidator(self::FIXED_YEAR);
        $this->assertSame(self::FIXED_YEAR, $validator->minTravelYear());
        $this->assertSame(self::FIXED_YEAR + GFInquiryValidator::TRAVEL_YEAR_SPAN - 1, $validator->maxTravelYear());

        // Both edges of the window are bookable travel dates.
        $first = $validator->validate(
            $this->validSubmission(['travel_day' => '1', 'travel_month' => '1', 'travel_year' => (string) $validator->minTravelYear()]),
            $this->context()
        );
        $last = $validator->validate(
            $this->validSubmission(['travel_day' => '1', 'travel_month' => '1', 'travel_year' => (string) $validator->maxTravelYear()]),
            $this->context()
        );

        $this->assertTrue($first->isValid());
        $this->assertTrue($last->isValid());
    }

    #[Test]
    public function adults_and_children_must_be_plain_non_negative_integers(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['adults' => '-1']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('adults'));
    }

    #[Test]
    public function an_unreasonably_large_party_is_rejected(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['adults' => '999']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('adults'));
    }

    #[Test]
    public function referral_source_other_requires_the_free_text_ac9(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['referral_source' => 'other', 'referral_source_other' => '']),
            $this->context()
        );

        $this->assertSame('required', $result->errorCodeFor('referral_source_other'));
    }

    #[Test]
    public function referral_source_other_text_becomes_the_stored_value(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['referral_source' => 'other', 'referral_source_other' => 'A podcast ad']),
            $this->context()
        );

        $this->assertTrue($result->isValid());
        $this->assertSame('A podcast ad', $result->get('referral_source'));
    }

    #[Test]
    public function referral_source_is_optional_and_free_of_a_hardcoded_list_ac9(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['referral_source' => '']),
            $this->context()
        );

        $this->assertTrue($result->isValid());
    }

    #[Test]
    public function an_unlisted_referral_source_that_is_not_other_is_rejected(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['referral_source' => 'made_up_value']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('referral_source'));
    }

    #[Test]
    public function the_terms_checkbox_is_required(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['terms' => '']),
            $this->context()
        );

        $this->assertSame('required', $result->errorCodeFor('terms'));
    }

    #[Test]
    public function a_partner_id_outside_the_catalogue_is_rejected(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['id_gf_partner' => '404']),
            $this->context()
        );

        $this->assertSame('invalid', $result->errorCodeFor('id_gf_partner'));
    }

    #[Test]
    public function partner_is_optional(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['id_gf_partner' => '']),
            $this->context()
        );

        $this->assertTrue($result->isValid());
        $this->assertNull($result->get('id_gf_partner'));
    }

    #[Test]
    public function a_promo_code_over_seven_characters_is_rejected(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['promo_code' => 'TOOLONGCODE']),
            $this->context()
        );

        $this->assertSame('too_long', $result->errorCodeFor('promo_code'));
    }

    #[Test]
    public function first_invalid_field_matches_form_order_for_focus_management_ac5(): void
    {
        $result = $this->validator->validate(
            $this->validSubmission(['last_name' => '', 'email' => '', 'terms' => '']),
            $this->context()
        );

        $this->assertSame('last_name', $result->firstInvalidField());
    }
}
