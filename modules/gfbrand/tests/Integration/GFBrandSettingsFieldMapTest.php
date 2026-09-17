<?php
/**
 * 2026 GF Experiences
 *
 * Story 1.18, Task 6's "load-bearing" contract test: every config key
 * AdminGfBrandController can save must be exactly the key the
 * corresponding vendor module (hotelreservationsystem, wkabouthotelblock,
 * wkhotelfeaturesblock, wktestimonialblock) already reads. If this tab ever
 * introduced its own gfbrand-owned rename of one of these keys, it would
 * "work" while silently drifting out of sync with the vendor screen — a
 * second, disconnected source of truth. This test pins the real key names
 * so that drift fails a test instead of shipping quietly.
 *
 * brandFieldGroups() is a static read of AdminGfBrandController's
 * VENDOR_FIELD_SPECS constant — no controller instantiation, no admin
 * request context, no employee/tab dependency.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

require_once dirname(dirname(__DIR__)) . '/controllers/admin/AdminGfBrandController.php';

#[CoversClass(AdminGfBrandController::class)]
class GFBrandSettingsFieldMapTest extends TestCase
{
    #[Test]
    public function contact_group_matches_hotelreservationsystems_website_contact_details_keys(): void
    {
        $this->assertSame(
            ['PS_SHOP_NAME', 'PS_SHOP_EMAIL', 'PS_SHOP_PHONE', 'PS_SHOP_ADDR1', 'PS_SHOP_ADDR2'],
            AdminGfBrandController::brandFieldGroups()['contact']
        );
    }

    #[Test]
    public function about_group_matches_wkabouthotelblocks_keys(): void
    {
        $this->assertSame(
            ['HOTEL_INTERIOR_HEADING', 'HOTEL_INTERIOR_DESCRIPTION'],
            AdminGfBrandController::brandFieldGroups()['about']
        );
    }

    #[Test]
    public function features_group_matches_wkhotelfeaturesblocks_keys(): void
    {
        $this->assertSame(
            ['HOTEL_AMENITIES_HEADING', 'HOTEL_AMENITIES_DESCRIPTION'],
            AdminGfBrandController::brandFieldGroups()['features']
        );
    }

    #[Test]
    public function testimonial_group_matches_wktestimonialblocks_heading_and_content_keys_only(): void
    {
        $this->assertSame(
            ['HOTEL_TESIMONIAL_BLOCK_HEADING', 'HOTEL_TESIMONIAL_BLOCK_CONTENT'],
            AdminGfBrandController::brandFieldGroups()['testimonial']
        );
    }

    #[Test]
    public function every_required_field_key_actually_exists_as_a_configuration_row(): void
    {
        // Required fields (PS_SHOP_NAME/EMAIL/PHONE/ADDR1, all four HOTEL_*
        // headings/descriptions) always have a row once their owning vendor
        // module is installed — this environment has all four installed, so
        // this also catches a typo that would otherwise silently read/write
        // a brand-new, wrong key. Optional fields (PS_SHOP_ADDR2) may have
        // no row at all until an owner fills them in, so they're excluded.
        $idLang = (int) Context::getContext()->language->id;

        foreach (AdminGfBrandController::VENDOR_FIELD_SPECS as $group => $specs) {
            foreach ($specs as $key => $spec) {
                if (empty($spec['required'])) {
                    continue;
                }

                $this->assertTrue(
                    Configuration::hasKey($key) || Configuration::hasKey($key, $idLang),
                    "Group '{$group}': '{$key}' is not a known ps_configuration key in this install."
                );
            }
        }
    }
}
