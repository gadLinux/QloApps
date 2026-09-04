<?php
/**
 * 2026 GF Experiences
 *
 * The conditional booking CTA — story 1.10, FR-20.
 *
 * This is the decision the whole platform migration exists to enable: a hotel
 * that switches to live booking must become bookable by flipping a flag in the
 * back office, not by shipping code. These tests are the contract for that.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFEstablishmentCta::class)]
class GFEstablishmentCtaTest extends TestCase
{
    /* ---- AC-1: somewhere you visit ------------------------------------ */

    #[Test]
    public function a_restaurant_sends_you_to_its_own_site(): void
    {
        $cta = $this->cta(GFEstablishment::TYPE_RESTAURANT, 'https://example.test/gallos');

        $this->assertSame(GFEstablishmentCta::VISIT, $cta->getKind());
        $this->assertSame('https://example.test/gallos', $cta->getExternalUrl());
        $this->assertTrue($cta->opensInNewTab());
    }

    #[Test]
    public function an_experience_behaves_like_a_restaurant(): void
    {
        $cta = $this->cta(GFEstablishment::TYPE_EXPERIENCE, 'https://example.test/tour');

        $this->assertSame(GFEstablishmentCta::VISIT, $cta->getKind());
    }

    /**
     * A card with a "Visit Website" button that goes nowhere is worse than a
     * card with no button, so the CTA is omitted rather than faked.
     */
    #[Test]
    public function a_restaurant_with_no_website_gets_no_call_to_action(): void
    {
        $cta = $this->cta(GFEstablishment::TYPE_RESTAURANT, '');

        $this->assertSame(GFEstablishmentCta::NONE, $cta->getKind());
        $this->assertFalse($cta->isPresent());
    }

    /* ---- AC-2: a hotel we cannot yet book ------------------------------ */

    #[Test]
    public function a_hotel_without_a_channel_manager_invites_an_enquiry(): void
    {
        $cta = $this->cta(GFEstablishment::TYPE_HOTEL, 'https://example.test/hotel');

        $this->assertSame(GFEstablishmentCta::INQUIRE, $cta->getKind());
    }

    /**
     * A hotel's own website must not win over the enquiry: sending the guest
     * off-site is exactly the booking we are trying to keep.
     */
    #[Test]
    public function a_hotels_own_website_does_not_override_the_enquiry(): void
    {
        $cta = $this->cta(GFEstablishment::TYPE_HOTEL, 'https://example.test/hotel');

        $this->assertNotSame(GFEstablishmentCta::VISIT, $cta->getKind());
    }

    #[Test]
    public function a_connected_but_inactive_channel_manager_still_only_invites_an_enquiry(): void
    {
        $cta = new GFEstablishmentCta(
            GFEstablishment::TYPE_HOTEL,
            '',
            true,
            GFEstablishment::CHANNEL_MANAGER_INACTIVE
        );

        $this->assertSame(GFEstablishmentCta::INQUIRE, $cta->getKind());
    }

    /**
     * Both halves are required. A stale 'active' status on a hotel whose flag
     * was turned off must not open the booking flow.
     */
    #[Test]
    public function an_active_status_without_the_flag_is_not_bookable(): void
    {
        $cta = new GFEstablishmentCta(
            GFEstablishment::TYPE_HOTEL,
            '',
            false,
            GFEstablishment::CHANNEL_MANAGER_ACTIVE
        );

        $this->assertSame(GFEstablishmentCta::INQUIRE, $cta->getKind());
    }

    /* ---- AC-3 / AC-4: a hotel we can book ------------------------------ */

    #[Test]
    public function a_hotel_with_a_live_channel_manager_can_be_booked(): void
    {
        $cta = new GFEstablishmentCta(
            GFEstablishment::TYPE_HOTEL,
            '',
            true,
            GFEstablishment::CHANNEL_MANAGER_ACTIVE
        );

        $this->assertSame(GFEstablishmentCta::BOOK, $cta->getKind());
        $this->assertFalse($cta->opensInNewTab(), 'Booking stays on our own site.');
    }

    /**
     * AC-4, stated as the acceptance criterion states it. The only difference
     * between these two CTAs is data, so flipping the flag in the back office
     * changes the button with no deploy.
     */
    #[Test]
    public function flipping_the_flag_is_the_only_difference_between_inquire_and_book(): void
    {
        $before = new GFEstablishmentCta(
            GFEstablishment::TYPE_HOTEL,
            '',
            false,
            GFEstablishment::CHANNEL_MANAGER_ACTIVE
        );
        $after = new GFEstablishmentCta(
            GFEstablishment::TYPE_HOTEL,
            '',
            true,
            GFEstablishment::CHANNEL_MANAGER_ACTIVE
        );

        $this->assertSame(GFEstablishmentCta::INQUIRE, $before->getKind());
        $this->assertSame(GFEstablishmentCta::BOOK, $after->getKind());
    }

    /**
     * The status arrives from a database column, so it is a string, and '1'
     * or 1 for the flag is just as likely as true.
     */
    #[Test]
    public function it_reads_the_flag_as_the_database_returns_it(): void
    {
        $cta = new GFEstablishmentCta(
            GFEstablishment::TYPE_HOTEL,
            '',
            '1',
            'active'
        );

        $this->assertSame(GFEstablishmentCta::BOOK, $cta->getKind());
    }

    /* ---- Labels -------------------------------------------------------- */

    #[Test]
    public function each_branch_names_itself(): void
    {
        $kinds = [];

        foreach ([
            [GFEstablishment::TYPE_RESTAURANT, 'https://x.test', false, 'not_connected'],
            [GFEstablishment::TYPE_HOTEL, '', false, 'not_connected'],
            [GFEstablishment::TYPE_HOTEL, '', true, 'active'],
        ] as $args) {
            $cta = new GFEstablishmentCta($args[0], $args[1], $args[2], $args[3]);
            $kinds[$cta->getKind()] = $cta->getLabel();
        }

        $this->assertSame(
            [
                GFEstablishmentCta::VISIT => 'Visit Website',
                GFEstablishmentCta::INQUIRE => 'Inquire to Book',
                GFEstablishmentCta::BOOK => 'Book Now',
            ],
            $kinds
        );
    }

    #[Test]
    public function an_absent_call_to_action_has_no_label(): void
    {
        $this->assertSame('', $this->cta(GFEstablishment::TYPE_RESTAURANT, '')->getLabel());
    }

    /**
     * An unknown type is treated as somewhere you visit rather than somewhere
     * you book: offering to take a booking we cannot honour is the worse
     * failure of the two.
     */
    #[Test]
    public function an_unrecognised_type_is_never_bookable(): void
    {
        $cta = new GFEstablishmentCta('SOMETHING_NEW', 'https://x.test', true, 'active');

        $this->assertSame(GFEstablishmentCta::VISIT, $cta->getKind());
    }

    private function cta($type, $destinationUrl)
    {
        return new GFEstablishmentCta(
            $type,
            $destinationUrl,
            false,
            GFEstablishment::CHANNEL_MANAGER_NOT_CONNECTED
        );
    }
}
