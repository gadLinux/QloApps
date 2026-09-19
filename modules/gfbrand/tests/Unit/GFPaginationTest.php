<?php
/**
 * 2026 GF Experiences
 *
 * Paging arithmetic for the establishments listing — story 1.9 AC-7.
 *
 * Kept as a value object rather than inlined in the controller because every
 * off-by-one here is a visitor who cannot reach the last establishment, and
 * that is much cheaper to pin in a unit test than to notice on a page.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(GFPagination::class)]
class GFPaginationTest extends TestCase
{
    /** The listing's page size, as fixed by AC-7. */
    const PER_PAGE = 12;

    #[Test]
    public function a_full_page_and_a_remainder_make_two_pages(): void
    {
        $pagination = new GFPagination(15, self::PER_PAGE, 1);

        $this->assertSame(2, $pagination->getPageCount());
    }

    #[Test]
    public function an_exact_multiple_does_not_add_an_empty_last_page(): void
    {
        $pagination = new GFPagination(24, self::PER_PAGE, 1);

        $this->assertSame(2, $pagination->getPageCount());
    }

    #[Test]
    public function fewer_results_than_a_page_is_one_page(): void
    {
        $pagination = new GFPagination(3, self::PER_PAGE, 1);

        $this->assertSame(1, $pagination->getPageCount());
        $this->assertFalse($pagination->isNeeded());
    }

    /**
     * AC-6 renders an empty state rather than a grid, but the pagination still
     * has to describe a coherent page 1 of 1 while it does.
     */
    #[Test]
    public function no_results_is_still_one_page(): void
    {
        $pagination = new GFPagination(0, self::PER_PAGE, 1);

        $this->assertSame(1, $pagination->getPageCount());
        $this->assertSame(1, $pagination->getPage());
        $this->assertSame(0, $pagination->getOffset());
    }

    #[Test]
    public function the_second_page_starts_after_a_full_first_page(): void
    {
        $pagination = new GFPagination(15, self::PER_PAGE, 2);

        $this->assertSame(12, $pagination->getOffset());
        $this->assertSame(12, $pagination->getLimit());
    }

    /**
     * ?page=99 is a bookmark that outlived the data, and ?page=0 and ?page=-1
     * arrive from crawlers. None of them may produce a negative SQL offset.
     */
    #[Test]
    public function a_page_beyond_the_end_clamps_to_the_last_one(): void
    {
        $pagination = new GFPagination(15, self::PER_PAGE, 99);

        $this->assertSame(2, $pagination->getPage());
        $this->assertSame(12, $pagination->getOffset());
    }

    #[Test]
    public function a_page_below_one_clamps_to_the_first(): void
    {
        foreach ([0, -1, -100] as $requested) {
            $pagination = new GFPagination(15, self::PER_PAGE, $requested);

            $this->assertSame(1, $pagination->getPage(), 'page=' . $requested);
            $this->assertSame(0, $pagination->getOffset(), 'page=' . $requested);
        }
    }

    #[Test]
    public function a_non_numeric_page_is_the_first_one(): void
    {
        $pagination = new GFPagination(15, self::PER_PAGE, 'two');

        $this->assertSame(1, $pagination->getPage());
    }

    #[Test]
    public function it_knows_where_it_can_go(): void
    {
        $first = new GFPagination(30, self::PER_PAGE, 1);
        $middle = new GFPagination(30, self::PER_PAGE, 2);
        $last = new GFPagination(30, self::PER_PAGE, 3);

        $this->assertSame([false, true], [$first->hasPrevious(), $first->hasNext()]);
        $this->assertSame([true, true], [$middle->hasPrevious(), $middle->hasNext()]);
        $this->assertSame([true, false], [$last->hasPrevious(), $last->hasNext()]);
    }

    #[Test]
    public function it_lists_every_page_number_for_the_pager(): void
    {
        $pagination = new GFPagination(30, self::PER_PAGE, 2);

        $this->assertSame([1, 2, 3], $pagination->getPages());
    }

    /**
     * The live region announces the result count, so it has to describe the
     * whole filtered set — not the twelve currently on screen.
     */
    #[Test]
    public function it_reports_the_total_not_the_page_size(): void
    {
        $pagination = new GFPagination(15, self::PER_PAGE, 1);

        $this->assertSame(15, $pagination->getTotal());
        $this->assertTrue($pagination->isNeeded());
    }
}
