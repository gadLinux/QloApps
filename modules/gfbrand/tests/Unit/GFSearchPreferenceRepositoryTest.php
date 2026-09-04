<?php
/**
 * 2026 GF Experiences
 *
 * Unit tests for storing a remembered search in the visitor's cookie.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFSearchPreferenceRepository::class)]
class GFSearchPreferenceRepositoryTest extends TestCase
{
    /** @var GFFakeCookie */
    private $cookie;

    /** @var GFSearchPreferenceRepository */
    private $repository;

    protected function setUp(): void
    {
        $this->cookie = new GFFakeCookie();
        $this->repository = new GFSearchPreferenceRepository($this->cookie);
    }

    #[Test]
    public function nothing_is_remembered_to_begin_with(): void
    {
        $this->assertNull($this->repository->find());
    }

    #[Test]
    public function what_it_saves_it_finds_again(): void
    {
        $preference = $this->preference();

        $this->assertTrue($this->repository->save($preference));
        $this->assertEquals($preference, $this->repository->find());
    }

    #[Test]
    public function forgetting_clears_it(): void
    {
        $this->repository->save($this->preference());
        $this->repository->forget();

        $this->assertNull($this->repository->find());
    }

    /**
     * The real Cookie throws on "|" and "¤" — its own field separators — and a
     * hotel or location name is free text, so the payload is encoded rather
     * than written raw.
     */
    #[Test]
    public function a_name_containing_the_cookies_separators_is_still_stored(): void
    {
        $preference = $this->preference();
        $preference->locationName = 'Liberia | Guanacaste ¤ CR';

        $this->assertTrue($this->repository->save($preference));
        $this->assertSame('Liberia | Guanacaste ¤ CR', $this->repository->find()->locationName);
    }

    /**
     * Everything the shop stores shares one HTTP cookie, so an oversized
     * payload would push out the cart. Better to remember nothing.
     */
    #[Test]
    public function an_oversized_preference_is_not_stored(): void
    {
        $preference = $this->preference();
        $preference->occupancies = array_fill(0, 500, ['adults' => 2, 'children' => 2, 'child_ages' => [5, 6]]);

        $this->assertFalse($this->repository->save($preference));
        $this->assertNull($this->repository->find());
    }

    #[Test]
    public function an_empty_preference_is_not_worth_storing(): void
    {
        $this->assertFalse($this->repository->save(new GFSearchPreference()));
        $this->assertNull($this->repository->find());
    }

    /**
     * A cookie is client-side and can arrive corrupted or hand-edited. That is
     * a reason to forget the preference, never to fail the page.
     */
    #[Test]
    public function a_corrupt_payload_reads_as_nothing_remembered(): void
    {
        $this->cookie->{GFSearchPreferenceRepository::COOKIE_KEY} = 'not-base64-json';

        $this->assertNull($this->repository->find());
    }

    #[Test]
    public function a_payload_that_is_not_an_object_reads_as_nothing_remembered(): void
    {
        $this->cookie->{GFSearchPreferenceRepository::COOKIE_KEY} = base64_encode('"a string"');

        $this->assertNull($this->repository->find());
    }

    /**
     * @return GFSearchPreference
     */
    private function preference()
    {
        $preference = new GFSearchPreference();
        $preference->hotelId = 21;
        $preference->hotelCategoryId = 48;
        $preference->locationCategoryId = 46;
        $preference->locationName = 'Liberia';
        $preference->dateFrom = '2026-10-01';
        $preference->dateTo = '2026-10-05';
        $preference->occupancies = [['adults' => 2, 'children' => 1, 'child_ages' => [7]]];

        return $preference;
    }
}
