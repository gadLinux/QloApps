<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for the establishment value object — Story 1.8.
 *
 * isDirectlyBookable() decides which CTA the storefront renders (story 1.10),
 * so its edge cases are pinned here.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(GFEstablishment::class)]
class GFEstablishmentTest extends TestCase
{
    #[Test]
    public function it_defaults_to_an_unbookable_experience(): void
    {
        $establishment = new GFEstablishment();

        $this->assertSame(GFEstablishment::TYPE_EXPERIENCE, $establishment->type);
        $this->assertSame(
            GFEstablishment::CHANNEL_MANAGER_NOT_CONNECTED,
            $establishment->channelManagerStatus
        );
        $this->assertFalse($establishment->hasChannelManager);
        $this->assertFalse($establishment->isDirectlyBookable());
    }

    #[Test]
    public function a_hotel_with_an_active_channel_manager_is_bookable(): void
    {
        $hotel = $this->makeHotel(true, GFEstablishment::CHANNEL_MANAGER_ACTIVE);

        $this->assertTrue($hotel->isDirectlyBookable());
    }

    /**
     * Every establishment currently ships with no channel manager, so these
     * are the paths that actually run today — all must route to the inquiry
     * form rather than a booking flow.
     */
    #[Test]
    #[DataProvider('unbookableHotelProvider')]
    public function a_hotel_is_not_bookable_unless_the_channel_manager_is_active(
        bool $hasChannelManager,
        string $status
    ): void {
        $hotel = $this->makeHotel($hasChannelManager, $status);

        $this->assertFalse($hotel->isDirectlyBookable());
    }

    public static function unbookableHotelProvider(): array
    {
        return [
            'no channel manager at all' => [false, GFEstablishment::CHANNEL_MANAGER_NOT_CONNECTED],
            'flagged but never connected' => [true, GFEstablishment::CHANNEL_MANAGER_NOT_CONNECTED],
            'connected then disabled' => [true, GFEstablishment::CHANNEL_MANAGER_INACTIVE],
            'active but flag off' => [false, GFEstablishment::CHANNEL_MANAGER_ACTIVE],
        ];
    }

    /**
     * A restaurant links out to its own site; it is never booked here, however
     * its channel-manager fields happen to be set.
     */
    #[Test]
    public function a_restaurant_is_never_directly_bookable(): void
    {
        $restaurant = new GFEstablishment();
        $restaurant->type = GFEstablishment::TYPE_RESTAURANT;
        $restaurant->hasChannelManager = true;
        $restaurant->channelManagerStatus = GFEstablishment::CHANNEL_MANAGER_ACTIVE;

        $this->assertFalse($restaurant->isDirectlyBookable());
    }

    #[Test]
    public function it_identifies_hotels(): void
    {
        $hotel = new GFEstablishment();
        $hotel->type = GFEstablishment::TYPE_HOTEL;

        $restaurant = new GFEstablishment();
        $restaurant->type = GFEstablishment::TYPE_RESTAURANT;

        $this->assertTrue($hotel->isHotel());
        $this->assertFalse($restaurant->isHotel());
    }

    #[Test]
    public function valid_types_are_the_three_the_schema_accepts(): void
    {
        $this->assertSame(
            ['HOTEL', 'RESTAURANT', 'EXPERIENCE'],
            GFEstablishment::getValidTypes()
        );
    }

    private function makeHotel(bool $hasChannelManager, string $status): GFEstablishment
    {
        $hotel = new GFEstablishment();
        $hotel->type = GFEstablishment::TYPE_HOTEL;
        $hotel->hasChannelManager = $hasChannelManager;
        $hotel->channelManagerStatus = $status;

        return $hotel;
    }
}
