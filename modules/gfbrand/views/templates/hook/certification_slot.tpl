{*
 * GF Experiences — reserved certification slot
 *
 * Story 1.5 / FR-10. Rendered on the room-type detail page immediately under
 * the room title, via displayRoomTypeDetailRoomTypeNameAfter — above the fold,
 * before the guest commits to a booking.
 *
 * DELIBERATELY EMPTY. Certification is Epic 2 (FR-29…FR-36) and is the core
 * trust feature of this product. Fixing its position, its markup contract and
 * its styling now means Epic 2 is a fill rather than a re-layout of this page.
 *
 * TO FILL IN EPIC 2: render the certification badges inside .gf-certification,
 * keyed on $id_product. The wrapper collapses to nothing while empty
 * (:empty in gf-brand.css), so adding content is the only change needed —
 * no template surgery, no spacing to rediscover.
 *
 * FR-36 note: whether a third-party body's mark may be displayed is a
 * per-body licensing fact (gf_certification_body.display_allowed), so gate
 * on data here, never on a code branch.
 *}
<div class="gf-certification"
     data-gf-certification-slot
     data-id-product="{$id_product|intval}"
     aria-live="polite"></div>
