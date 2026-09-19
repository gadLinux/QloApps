<?php
/**
 * 2026 GF Experiences
 *
 * The booking flow and the establishment card must agree.
 *
 * QloApps offers "Book Now" for anything with room types. For a hotel we have
 * not connected a channel manager to, that is a booking we cannot honour, and
 * it contradicts the "Inquire to Book" the same hotel shows on its own card.
 *
 * These tests pin that both surfaces reach the same answer, by construction:
 * the decision is GFEstablishmentCta in both cases, never a second rule.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFRoomTypeBookability::class)]
class GFRoomTypeBookabilityTest extends TestCase
{
    #[Test]
    public function a_room_type_of_an_unconnected_hotel_is_not_bookable(): void
    {
        $map = $this->bookability([$this->room(770, false, 'not_connected')]);

        $this->assertTrue($map->isRestricted(770));
        $this->assertSame(GFEstablishmentCta::INQUIRE, $map->kindFor(770));
    }

    #[Test]
    public function a_room_type_of_a_connected_hotel_keeps_the_stock_booking_flow(): void
    {
        $map = $this->bookability([$this->room(770, true, 'active')]);

        $this->assertFalse(
            $map->isRestricted(770),
            'A bookable hotel must be left entirely alone — the stock Book Now stands.'
        );
    }

    #[Test]
    public function a_connected_but_inactive_hotel_is_not_bookable(): void
    {
        $map = $this->bookability([$this->room(770, true, 'inactive')]);

        $this->assertTrue($map->isRestricted(770));
    }

    /**
     * The same rule as the card, so the two surfaces cannot drift: whatever
     * GFEstablishmentCta says about the establishment is what the room type
     * inherits.
     */
    #[Test]
    public function it_reaches_the_same_answer_as_the_establishment_card(): void
    {
        foreach ([[false, 'not_connected'], [true, 'inactive'], [true, 'active']] as $state) {
            $card = new GFEstablishmentCta(
                GFEstablishment::TYPE_HOTEL,
                '',
                $state[0],
                $state[1]
            );
            $map = $this->bookability([$this->room(770, $state[0], $state[1])]);

            $this->assertSame(
                $card->getKind() !== GFEstablishmentCta::BOOK,
                $map->isRestricted(770),
                'Card says ' . $card->getKind() . ' but the booking flow disagrees.'
            );
        }
    }

    /**
     * A room type whose hotel we never imported is not in the map at all, so
     * the stock flow is untouched. Disabling booking on a hotel the brand
     * layer knows nothing about would be overreach.
     */
    #[Test]
    public function a_room_type_we_know_nothing_about_is_left_alone(): void
    {
        $map = $this->bookability([$this->room(770, false, 'not_connected')]);

        $this->assertFalse($map->isRestricted(999));
        $this->assertSame('', $map->kindFor(999));
    }

    /**
     * A hotel whose establishment row has gone missing cannot be shown as
     * bookable on a guess: no evidence means no booking.
     */
    #[Test]
    public function a_hotel_with_no_establishment_record_is_not_bookable(): void
    {
        $map = $this->bookability([[
            'id_product' => 770,
            'id_establishment' => null,
            'gf_type' => null,
            'gf_destination_url' => null,
            'gf_has_channel_manager' => null,
            'gf_channel_manager_status' => null,
        ]]);

        $this->assertTrue($map->isRestricted(770));
    }

    #[Test]
    public function it_carries_the_establishment_id_so_an_enquiry_can_name_the_hotel(): void
    {
        $map = $this->bookability([$this->room(770, false, 'not_connected', 815)]);

        $this->assertSame(815, $map->establishmentFor(770));
    }

    #[Test]
    public function the_label_matches_the_card(): void
    {
        $map = $this->bookability([$this->room(770, false, 'not_connected')]);

        $this->assertSame('Inquire to Book', $map->labelFor(770));
    }

    #[Test]
    public function an_empty_catalogue_restricts_nothing(): void
    {
        $map = $this->bookability([]);

        $this->assertFalse($map->isRestricted(770));
        $this->assertSame([], $map->restrictedIds());
    }

    #[Test]
    public function it_lists_only_the_restricted_room_types(): void
    {
        $map = $this->bookability([
            $this->room(770, false, 'not_connected'),
            $this->room(771, true, 'active'),
            $this->room(772, true, 'inactive'),
        ]);

        $this->assertSame([770, 772], $map->restrictedIds());
    }

    /**
     * @param array[] $rows
     * @return GFRoomTypeBookability
     */
    private function bookability(array $rows)
    {
        return new GFRoomTypeBookability($rows);
    }

    private function room($idProduct, $hasChannelManager, $status, $idEstablishment = 100)
    {
        return [
            'id_product' => $idProduct,
            'id_establishment' => $idEstablishment,
            'gf_type' => GFEstablishment::TYPE_HOTEL,
            'gf_destination_url' => 'https://example.test/hotel',
            'gf_has_channel_manager' => $hasChannelManager ? 1 : 0,
            'gf_channel_manager_status' => $status,
        ];
    }
}
