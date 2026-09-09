<?php
/**
 * 2026 GF Experiences
 *
 * Assembles the homepage content — story 1.13.
 *
 * The homepage is the one page a first-time visitor reads to understand the
 * whole offer. Its sections are ordered (AC-1): hero → pillars → featured
 * strip → Why Choose Us → advisor drawer → partner drawer → footer. The hero
 * and the drawers are rendered elsewhere (the theme's hero, and the trust
 * hooks from story 1.11); this service owns the middle three that are data-
 * driven, so a marketing change never needs a deploy.
 *
 * The featured strip is the piece that must not be hardcoded: AC-4 shows
 * establishments flagged gf_featured_home, an admin decision. A shop that has
 * not flagged anything yet renders no strip at all — an empty section titled
 * "Featured" reads as a broken page, so absence is the honest state.
 *
 * APPLICATION LAYER — orchestrates the repository; holds no SQL and builds no
 * URLs (those need the Link service and belong to the front controller).
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFHomepage
{
    /** @var GFEstablishmentRepository */
    private $repository;

    public function __construct(GFEstablishmentRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * The establishments the homepage strip should show, as cards.
     *
     * Same card shape the listing builds, so the homepage reuses the exact
     * same card component (story 1.10) in its compact variant rather than
     * forking it.
     *
     * @param  int $idLang
     * @return array[] Empty when nothing is flagged: the section is omitted.
     */
    public function featuredCards($idLang)
    {
        return array_map([$this, 'toCard'], $this->repository->findFeaturedForHome((int) $idLang));
    }

    /**
     * @param  array $row
     * @return array
     */
    private function toCard(array $row)
    {
        $type = isset($row['gf_type']) ? (string) $row['gf_type'] : '';
        $certification = isset($row['gf_certification']) ? (string) $row['gf_certification'] : '';

        $cta = new GFEstablishmentCta(
            $type,
            isset($row['gf_destination_url']) ? $row['gf_destination_url'] : '',
            isset($row['gf_has_channel_manager']) ? $row['gf_has_channel_manager'] : false,
            isset($row['gf_channel_manager_status']) ? $row['gf_channel_manager_status'] : ''
        );

        return [
            'id_product' => (int) $row['id_product'],
            'source_id' => isset($row['gf_source_id']) ? (string) $row['gf_source_id'] : '',
            'name' => (string) $row['name'],
            'description' => (string) $row['description_short'],
            'link_rewrite' => isset($row['link_rewrite']) ? (string) $row['link_rewrite'] : '',
            'id_image' => isset($row['id_image']) ? (int) $row['id_image'] : 0,
            'type' => $type,
            'type_label' => GFEstablishmentListing::labelForType($type),
            'city' => isset($row['gf_city']) ? (string) $row['gf_city'] : '',
            'country' => isset($row['gf_country']) ? (string) $row['gf_country'] : '',
            'certification' => $certification,
            'certification_label' => GFEstablishmentListing::labelForCertification($certification),
            'cta' => $cta->getKind(),
            'cta_label' => $cta->getLabel(),
            'cta_present' => $cta->isPresent(),
            'cta_new_tab' => $cta->opensInNewTab(),
            'cta_url' => $cta->getExternalUrl(),
        ];
    }
}
