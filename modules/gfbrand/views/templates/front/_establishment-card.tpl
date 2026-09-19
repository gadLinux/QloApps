{*
 * GF Experiences — establishment card
 * Story 1.10 (FR-20). One component, consumed by the listing (1.9) and the
 * homepage strip (1.13).
 *
 * Parameters:
 *   $establishment  card data from GFEstablishmentListing, with URLs added
 *                   by the front controller
 *   $compact        true for the homepage variant: pills suppressed,
 *                   description clamped shorter. Optional, defaults false.
 *
 * THE CARD IS NOT A LINK (AC-6). Only the CTA is. An anchor wrapping an image,
 * two pills, a heading and a paragraph is announced as one enormous link and
 * is miserable to traverse with a screen reader — and it would also swallow
 * the CTA, since anchors cannot nest.
 *
 * The CTA branch is decided in GFEstablishmentCta, never here: the whole point
 * of AC-4 is that flipping a flag in the back office changes the button, so
 * this template renders the answer it is given and does not second-guess it.
 *}
{assign var='gf_compact' value=$compact|default:false}

<article class="gf-establishment-card{if $gf_compact} gf-establishment-card--compact{/if}">

    <div class="gf-card-media">
        {if $establishment.image_url}
            {* Decorative: the name below is the card's accessible label, so
               alt text here would only repeat it. *}
            <img src="{$establishment.image_url|escape:'html':'UTF-8'}"
                 alt=""
                 loading="lazy"
                 width="720" height="480">
        {else}
            <span class="gf-card-media-blank" aria-hidden="true"></span>
        {/if}
    </div>

    <div class="gf-card-body">
        {if !$gf_compact}
            <p class="gf-card-tags">
                <span class="gf-tag gf-tag-type gf-tag-{$establishment.type|lower|escape:'html':'UTF-8'}">{$establishment.type_label|escape:'htmlall':'UTF-8'}</span>
                {if $establishment.city}
                    <span class="gf-tag gf-tag-place">{$establishment.city|escape:'htmlall':'UTF-8'}</span>
                {/if}
            </p>
        {/if}

        <h3 class="gf-card-name">{$establishment.name|escape:'htmlall':'UTF-8'}</h3>

        {if $establishment.description}
            <div class="gf-card-text">{$establishment.description|strip_tags|escape:'htmlall':'UTF-8'}</div>
        {/if}
    </div>

    {* Pinned to the card foot by .gf-card-body taking the slack, so CTAs line
       up across a row of wildly unequal descriptions (AC-5). *}
    <footer class="gf-card-foot">
        {if $establishment.cta_present}
            {if $establishment.cta_new_tab}
                <a class="gf-cta gf-cta--visit"
                   href="{$establishment.cta_url|escape:'html':'UTF-8'}"
                   target="_blank" rel="noopener noreferrer">
                    {$establishment.cta_label|escape:'htmlall':'UTF-8'}
                    <span class="gf-cta-mark" aria-hidden="true">&#8599;</span>
                    <span class="sr-only">{l s='(opens in a new tab)' mod='gfbrand'}</span>
                </a>
            {else}
                <a class="gf-cta gf-cta--{$establishment.cta|escape:'html':'UTF-8'}"
                   href="{$establishment.cta_url|escape:'html':'UTF-8'}">
                    {$establishment.cta_label|escape:'htmlall':'UTF-8'}
                    <span class="gf-cta-mark" aria-hidden="true">&#8594;</span>
                </a>
            {/if}
            {* Names the establishment for anyone traversing links out of
               context, where a page of identical "Visit Website" would
               otherwise be unusable. *}
            <span class="sr-only">&mdash; {$establishment.name|escape:'htmlall':'UTF-8'}</span>
        {/if}

        {if $establishment.certification_label}
            <span class="gf-cert-pill gf-cert-pill--{$establishment.certification|escape:'html':'UTF-8'}">
                {if $establishment.certification == 'dedicated'}
                    {* The tick is not the only thing distinguishing the two
                       pills — the fill and the wording differ too (WCAG 1.4.1). *}
                    <span class="gf-cert-tick" aria-hidden="true">&#10003;</span>
                {/if}
                {$establishment.certification_label|escape:'htmlall':'UTF-8'}
            </span>
        {/if}
    </footer>

</article>
