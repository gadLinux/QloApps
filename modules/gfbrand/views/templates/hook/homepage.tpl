{*
 * GF Experiences — homepage assembly. Story 1.13.
 *
 * Injected via the displayHome hook, so the brand layer owns this page
 * without touching the theme template. The hero lives in the theme header
 * (restyled by CSS, copy via config).
 *
 * Section order (AC-1): hero (theme) → pillars → featured strip → Why Choose
 * Us → advisor drawer → partner drawer → footer (theme). The drawers'
 * triggers are the last two pillars; the drawer panels render at the bottom
 * of the page, resolved by aria-controls (story 1.11), so trigger and panel
 * are not adjacent in the DOM.
 *
 * Parameters (assigned by the displayHome hook):
 *   $gf_pillars, $gf_pillar_icon_file
 *   $gf_featured, $gf_has_featured
 *   $gf_why_choose, $gf_why_photo
 *   $gf_establishments_url, $gf_experiences_url
 *   $gf_card_template    absolute path to the shared card component
 *}

<section class="gf-homepage" id="gf-homepage">

    {* --- Pillars (AC-3) -----------------------------------------------------
     * Four columns separated by hairline rules, not card borders. The last
     * two are disclosure triggers, not links: they open the advisor and
     * partner drawers in place. The trigger is a <button> (story 1.11 AC-5),
     * and the drawer it controls renders immediately below via the trust
     * hooks, so trigger and panel are one unit. *}
    <div class="gf-pillars">
        {foreach $gf_pillars as $pillar}
            <div class="gf-pillar gf-pillar--{$pillar.type}">
                {if $pillar.type == 'link'}
                    <a class="gf-pillar-link" href="{$pillar.url|escape:'html':'UTF-8'}"
                       {if $pillar.new_tab}target="_blank" rel="noopener noreferrer"{/if}>
                        <span class="gf-pillar-icon gf-pillar-icon--{$pillar.icon}" aria-hidden="true">
                            {include file=$gf_pillar_icon_file}
                        </span>
                        <span class="gf-pillar-title">{$pillar.title|escape:'htmlall':'UTF-8'}</span>
                        <span class="gf-pillar-copy">{$pillar.copy|escape:'htmlall':'UTF-8'}</span>
                        <span class="gf-pillar-cta">
                            {$pillar.cta|escape:'htmlall':'UTF-8'}
                            <span class="gf-pillar-arrow" aria-hidden="true">&#8594;</span>
                        </span>
                    </a>
                {else}
                    <div class="gf-pillar-disclosure">
                        <button type="button"
                                class="gf-pillar-trigger"
                                data-gf-drawer-trigger
                                aria-expanded="false"
                                aria-controls="{$pillar.drawer_id}">
                            <span class="gf-pillar-icon gf-pillar-icon--{$pillar.icon}" aria-hidden="true">
                                {include file=$gf_pillar_icon_file}
                            </span>
                            <span class="gf-pillar-title">{$pillar.title|escape:'htmlall':'UTF-8'}</span>
                            <span class="gf-pillar-copy">{$pillar.copy|escape:'htmlall':'UTF-8'}</span>
                            <span class="gf-pillar-cta">
                                {$pillar.cta|escape:'htmlall':'UTF-8'}
                                <span class="gf-pillar-arrow" aria-hidden="true">&#9662;</span>
                            </span>
                        </button>
                    </div>
                {/if}
            </div>
        {/foreach}
    </div>

    {* --- Featured strip (AC-4) ---------------------------------------------
     * Establishments flagged gf_featured_home — an admin decision, never a
     * hardcoded id list. Omitted entirely when nothing is flagged. *}
    {if $gf_has_featured}
        <section class="gf-featured" id="gf-featured">
            <div class="container">
                <h2 class="gf-featured-title">
                    {l s='Gluten-Free Destination Hotel Collection' mod='gfbrand'}
                </h2>
                <div class="gf-featured-grid">
                    {foreach $gf_featured as $establishment}
                        {* Compact variant: type/location pills suppressed,
                            description clamped — the homepage card, not the
                            listing card (story 1.10 component). *}
                        {include file=$gf_card_template establishment=$establishment compact=true}
                    {/foreach}
                </div>
                <p class="gf-featured-more">
                    <a href="{$gf_establishments_url|escape:'html':'UTF-8'}">
                        {l s='Explore all establishments' mod='gfbrand'}
                        <span aria-hidden="true">&#8594;</span>
                    </a>
                </p>
            </div>
        </section>
    {/if}

    {* --- Why Choose Us (AC-1) ----------------------------------------------
     * Photograph left, heading and a four-item checklist right. The imagery is
     * a branded placeholder until the client supplies a photograph. *}
    <section class="gf-why" id="gf-why-choose-us">
        <div class="container">
            <div class="gf-why-row">
                <div class="gf-why-photo">
                    <img src="{$gf_why_photo|escape:'html':'UTF-8'}"
                         alt="{l s='A guest dining at a certified gluten-free establishment' mod='gfbrand'}"
                         width="640" height="480"
                         loading="lazy">
                </div>
                <div class="gf-why-copy">
                    <p class="gf-why-eyebrow">{l s='Why Choose Us' mod='gfbrand'}</p>
                    <h2 class="gf-why-title">{l s='Travel With Confidence' mod='gfbrand'}</h2>
                    <ul class="gf-why-list" role="list">
                        {foreach $gf_why_choose as $item}
                            <li class="gf-why-item">
                                <span class="gf-why-tick" aria-hidden="true">&#10003;</span>
                                <span class="gf-why-item-text">{$item|escape:'htmlall':'UTF-8'}</span>
                            </li>
                        {/foreach}
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {* The drawers sit at the bottom of the branded page (AC-1): after Why
       Choose Us, before the theme footer. Their triggers are the last two
       pillars above; story 1.11's drawer JS resolves the external trigger by
       aria-controls, so the trigger button and its panel do not need to be
       adjacent in the DOM. *}
    {hook h="displayGfAdvisors"}
    {hook h="displayGfPartners"}

</section>
