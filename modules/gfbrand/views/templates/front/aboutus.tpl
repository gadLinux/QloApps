{*
 * GF Experiences — About Us page. Story 1.14.
 *
 * Five fixed-structure bands, in order (AC-1), matching
 * doc/branding/screenshots/03-about-us.png's structure:
 *   1. Who We Are      — eyebrow, headline, three paragraphs, photo right
 *   2. Our Mission      — centred statement, three icon-left/text-right pillars
 *   3. What We Offer    — three cards, photo + icon + title + description
 *   4. Why Choose Us    — reused verbatim from the homepage (same partial data)
 *   5. Closing CTA      — full-width olive band
 *
 * Parameters (assigned by controllers/front/aboutus.php):
 *   $gf_page_title, $gf_page_tagline
 *   $gf_whoweare_eyebrow, $gf_whoweare_heading, $gf_whoweare_paragraphs, $gf_whoweare_photo
 *   $gf_mission_heading, $gf_mission_pillars
 *   $gf_offer_heading, $gf_offer_cards, $gf_offer_photo
 *   $gf_why_choose, $gf_why_photo
 *   $gf_cta_heading, $gf_cta_subtext
 *   $gf_pillar_icon_file  shared icon partial (story 1.13, extended 1.14)
 *
 * Editable vs. fixed copy: only the band-level headings/statements/CTA text
 * are admin-editable (AdminGfBrandController's "About Us Page" group) — an
 * owner can rewrite the story without a deploy. Repeating items *within* a
 * band (mission pillars, offer cards, the reused Why Choose Us checklist)
 * stay as fixed {l} strings, matching the same choice already made for the
 * homepage's own pillars/checklist (gfbrand.php's buildPillars()/
 * whyChooseItems()) — editing a handful of list items one-by-one via a
 * dedicated admin screen is a disproportionate amount of UI for content
 * that rarely changes.
 *}

<div class="gf-aboutus">

    {* --- Inner-page hero --------------------------------------------------
       Same pattern as the establishments listing's header (title + tagline
       + decorative rule) — new class names so each page's CSS stays
       self-contained, per the section-per-story convention. *}
    <header class="gf-aboutus-hero">
        <h1 class="gf-aboutus-hero-title">{$gf_page_title|escape:'htmlall':'UTF-8'}</h1>
        {if $gf_page_tagline}
            <p class="gf-aboutus-hero-tagline">{$gf_page_tagline|escape:'htmlall':'UTF-8'}</p>
        {/if}
        <span class="gf-aboutus-rule" aria-hidden="true"></span>
    </header>

    {* --- Band 1: Who We Are ------------------------------------------------ *}
    <section class="gf-aboutus-band gf-aboutus-whoweare">
        <div class="container">
            <div class="gf-aboutus-whoweare-row">
                <div class="gf-aboutus-whoweare-copy">
                    {if $gf_whoweare_eyebrow}
                        <p class="gf-aboutus-eyebrow">{$gf_whoweare_eyebrow|escape:'htmlall':'UTF-8'}</p>
                    {/if}
                    {if $gf_whoweare_heading}
                        <h2 class="gf-aboutus-whoweare-heading">{$gf_whoweare_heading|escape:'htmlall':'UTF-8'}</h2>
                    {/if}
                    <span class="gf-aboutus-rule gf-aboutus-rule--left" aria-hidden="true"></span>
                    {foreach $gf_whoweare_paragraphs as $gf_paragraph name=gf_whoweare_para}
                        <p class="gf-aboutus-whoweare-para{if $smarty.foreach.gf_whoweare_para.last} gf-aboutus-emphasis{/if}">
                            {$gf_paragraph|escape:'htmlall':'UTF-8'|nl2br}
                        </p>
                    {/foreach}
                </div>
                <div class="gf-aboutus-whoweare-photo">
                    <img src="{$gf_whoweare_photo|escape:'html':'UTF-8'}"
                         alt="{l s='A gluten-free destination featured by GF Experiences' mod='gfbrand'}"
                         width="640" height="480"
                         loading="lazy">
                </div>
            </div>
        </div>
    </section>

    {* --- Band 2: Our Mission ------------------------------------------------
       Warm sand band. Centred statement, then three icon-left/text-right
       pillars — the same filled-circle icon treatment as the homepage's
       pillar row and Why Choose Us checklist (story 1.13). *}
    <section class="gf-aboutus-band gf-aboutus-mission">
        <div class="container">
            <p class="gf-aboutus-eyebrow gf-aboutus-eyebrow--center">{l s='Our Mission' mod='gfbrand'}</p>
            {if $gf_mission_heading}
                <p class="gf-aboutus-mission-statement">{$gf_mission_heading|escape:'htmlall':'UTF-8'}</p>
            {/if}

            <div class="gf-aboutus-mission-pillars">
                {foreach $gf_mission_pillars as $pillar}
                    <div class="gf-aboutus-mission-pillar">
                        <span class="gf-aboutus-mission-icon" aria-hidden="true">
                            {include file=$gf_pillar_icon_file}
                        </span>
                        <span class="gf-aboutus-mission-text">
                            <span class="gf-aboutus-mission-title">{$pillar.title|escape:'htmlall':'UTF-8'}</span>
                            <span class="gf-aboutus-mission-copy">{$pillar.copy|escape:'htmlall':'UTF-8'}</span>
                        </span>
                    </div>
                {/foreach}
            </div>
        </div>
    </section>

    {* --- Band 3: What We Offer ---------------------------------------------
       Three cards, each a photograph with a filled olive circle icon
       straddling the image/body boundary, then a centred title and
       description. One shared static asset (AC boundary: no new
       photography), swappable by replacing the file. *}
    <section class="gf-aboutus-band gf-aboutus-offer">
        <div class="container">
            <header class="gf-aboutus-band-header">
                <p class="gf-aboutus-eyebrow gf-aboutus-eyebrow--center">{l s='What We Offer' mod='gfbrand'}</p>
                {if $gf_offer_heading}
                    <h2 class="gf-aboutus-offer-heading">{$gf_offer_heading|escape:'htmlall':'UTF-8'}</h2>
                {/if}
                <span class="gf-aboutus-rule" aria-hidden="true"></span>
            </header>

            <div class="gf-offer-grid">
                {foreach $gf_offer_cards as $pillar}
                    <article class="gf-offer-card">
                        <div class="gf-offer-card-media">
                            <img src="{$gf_offer_photo|escape:'html':'UTF-8'}"
                                 alt="{$pillar.title|escape:'htmlall':'UTF-8'}"
                                 width="480" height="320"
                                 loading="lazy">
                            <span class="gf-offer-card-icon" aria-hidden="true">
                                {include file=$gf_pillar_icon_file}
                            </span>
                        </div>
                        <div class="gf-offer-card-body">
                            <h3 class="gf-offer-card-title">{$pillar.title|escape:'htmlall':'UTF-8'}</h3>
                            <p class="gf-offer-card-copy">{$pillar.copy|escape:'htmlall':'UTF-8'}</p>
                        </div>
                    </article>
                {/foreach}
            </div>
        </div>
    </section>

    {* --- Band 4: Why Choose Us -----------------------------------------------
       Reused verbatim from the homepage (hookHomepage()): same photo, same
       four-item checklist, same copy. Markup mirrors homepage.tpl's
       .gf-why block so both pages render identically from the same tokens. *}
    <section class="gf-aboutus-band gf-why" id="gf-aboutus-why-choose-us">
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

    {* --- Band 5: Closing CTA ------------------------------------------------ *}
    <section class="gf-aboutus-band gf-aboutus-cta">
        <div class="container">
            {if $gf_cta_heading}
                <h2 class="gf-aboutus-cta-heading">{$gf_cta_heading|escape:'htmlall':'UTF-8'|nl2br}</h2>
            {/if}
            {if $gf_cta_subtext}
                <p class="gf-aboutus-cta-subtext">{$gf_cta_subtext|escape:'htmlall':'UTF-8'}</p>
            {/if}
        </div>
    </section>

</div>
