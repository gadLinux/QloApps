<?php
/**
 * 2026 GF Experiences
 *
 * What a customer last told the search panel.
 *
 * Where they want to go, when, and for how many — the answers they should not
 * have to give twice. Deliberately plain data: it is written to a cookie and
 * read back on the next request, so it must survive a JSON round trip with
 * nothing behind it.
 *
 * DOMAIN LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFSearchPreference
{
    /** @var int Hotel they chose, 0 when they searched a location instead. */
    public $hotelId = 0;

    /** @var int That hotel's category, which is what the form actually posts. */
    public $hotelCategoryId = 0;

    /** @var int Location category, when they searched by place. */
    public $locationCategoryId = 0;

    /** @var string Location as typed back to them in the field. */
    public $locationName = '';

    /** @var string Check-in, Y-m-d. */
    public $dateFrom = '';

    /** @var string Check-out, Y-m-d. */
    public $dateTo = '';

    /** @var array<int, array> One entry per room: adults, children, child_ages. */
    public $occupancies = [];

    /**
     * @param  array $data As produced by toArray(); unknown keys are ignored.
     * @return GFSearchPreference
     */
    public static function fromArray(array $data)
    {
        $preference = new self();

        $preference->hotelId = isset($data['hotelId']) ? (int) $data['hotelId'] : 0;
        $preference->hotelCategoryId = isset($data['hotelCategoryId']) ? (int) $data['hotelCategoryId'] : 0;
        $preference->locationCategoryId = isset($data['locationCategoryId'])
            ? (int) $data['locationCategoryId']
            : 0;
        $preference->locationName = isset($data['locationName']) ? (string) $data['locationName'] : '';
        $preference->dateFrom = isset($data['dateFrom']) ? (string) $data['dateFrom'] : '';
        $preference->dateTo = isset($data['dateTo']) ? (string) $data['dateTo'] : '';
        $preference->occupancies = isset($data['occupancies']) && is_array($data['occupancies'])
            ? $data['occupancies']
            : [];

        return $preference;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'hotelId' => $this->hotelId,
            'hotelCategoryId' => $this->hotelCategoryId,
            'locationCategoryId' => $this->locationCategoryId,
            'locationName' => $this->locationName,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'occupancies' => $this->occupancies,
        ];
    }

    /**
     * True when there is nothing here worth putting back in the form.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->hotelId
            && !$this->hotelCategoryId
            && !$this->locationCategoryId
            && !$this->hasDates()
            && empty($this->occupancies);
    }

    /**
     * @return bool
     */
    public function hasDates()
    {
        return $this->dateFrom !== '' && $this->dateTo !== '';
    }

    /**
     * True when the check-in has already been and gone.
     *
     * @param  string $today Y-m-d.
     * @return bool
     */
    public function datesAreStale($today)
    {
        return $this->hasDates() && $this->dateFrom < $today;
    }

    /**
     * A copy with the dates cleared, for when they can no longer be offered.
     *
     * @return GFSearchPreference
     */
    public function withoutDates()
    {
        $copy = clone $this;
        $copy->dateFrom = '';
        $copy->dateTo = '';

        return $copy;
    }

    /**
     * @return int
     */
    public function countAdults()
    {
        return $this->sumOccupancy('adults');
    }

    /**
     * @return int
     */
    public function countChildren()
    {
        return $this->sumOccupancy('children');
    }

    /**
     * @return int
     */
    private function sumOccupancy($field)
    {
        $total = 0;

        foreach ($this->occupancies as $room) {
            $total += isset($room[$field]) ? (int) $room[$field] : 0;
        }

        return $total;
    }
}
