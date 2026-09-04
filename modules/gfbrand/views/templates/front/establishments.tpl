{*
 * GF Experiences — GF Establishments listing
 * Story 1.9 (FR-18, FR-19)
 *
 * The country filter is a row of LINKS, not buttons bound to a script: D6
 * requires it to work with JavaScript disabled, so each pill navigates to
 * ?country=<value> and the server returns the filtered set. That also makes
 * every filtered view linkable, bookmarkable and crawlable (AC-3).
 *
 * The pills carry aria-pressed because they behave as a toggle group even
 * though they are anchors — role="button" is deliberately NOT used, since that
 * would take away the link affordances (open in a new tab, copy address) that
 * the server-side design gives us for free.
 *}

{capture name=path}{l s='GF Establishments' mod='gfbrand'}{/capture}

<section class="gf-establishments">

    {* Inner-page hero. Ground and type come from the brand tokens; there is no
       photograph here because the grid below is already image-dense. *}
    <header class="gf-establishments-hero">
        <h1 class="gf-establishments-title">{$gf_heading|escape:'htmlall':'UTF-8'}</h1>
        {if $gf_tagline}
            <p class="gf-establishments-tagline">{$gf_tagline|escape:'htmlall':'UTF-8'}</p>
        {/if}
        <span class="gf-establishments-rule" aria-hidden="true"></span>
    </header>

    {* --- Country filter (AC-2, AC-3, AC-4, AC-5) ------------------------- *}
    <nav class="gf-filter-bar" aria-label="{l s='Filter establishments by country' mod='gfbrand'}">
        <ul class="gf-filter-pills">
            {foreach from=$gf_pills item=pill}
                <li>
                    <a href="{$pill.url|escape:'html':'UTF-8'}"
                       class="gf-pill{if $pill.active} gf-pill-active{/if}"
                       aria-pressed="{if $pill.active}true{else}false{/if}">{$pill.label|escape:'htmlall':'UTF-8'}</a>
                </li>
            {/foreach}
        </ul>
    </nav>

    {* The count is announced on navigation, so a screen-reader user who
       activates a pill hears the result rather than being left to explore the
       grid to find out whether anything changed (AC-5). *}
    <p class="gf-filter-count" role="status" aria-live="polite">
        {if $gf_is_showing_all}
            {l s='Showing all %d establishments' sprintf=[$gf_total] mod='gfbrand'}
        {else}
            {l s='%1$d establishments in %2$s' sprintf=[$gf_total, $gf_selected_country] mod='gfbrand'}
        {/if}
    </p>

    {* --- Grid, or the empty state (AC-1, AC-6) --------------------------- *}
    {if $gf_is_empty}
        <div class="gf-establishments-empty">
            <p class="gf-empty-message">{l s='No establishments in this country yet' mod='gfbrand'}</p>
            <p class="gf-empty-help">
                {l s='We are adding new places all the time.' mod='gfbrand'}
                <a href="{$gf_show_all_url|escape:'html':'UTF-8'}">{l s='Show All establishments' mod='gfbrand'}</a>
            </p>
        </div>
    {else}
        <ul class="gf-establishment-grid">
            {foreach from=$gf_establishments item=establishment}
                <li class="gf-establishment-card">

                    <div class="gf-card-media">
                        {if $establishment.image_url}
                            {* Decorative: the name below is the accessible label
                               for the whole card, so alt text here would repeat it. *}
                            <img src="{$establishment.image_url|escape:'html':'UTF-8'}"
                                 alt=""
                                 loading="lazy"
                                 width="720" height="720">
                        {else}
                            <span class="gf-card-media-blank" aria-hidden="true"></span>
                        {/if}
                    </div>

                    <div class="gf-card-body">
                        <p class="gf-card-tags">
                            <span class="gf-tag gf-tag-type gf-tag-{$establishment.type|lower|escape:'html':'UTF-8'}">{$establishment.type_label|escape:'htmlall':'UTF-8'}</span>
                            {if $establishment.city}
                                <span class="gf-tag gf-tag-place">{$establishment.city|escape:'htmlall':'UTF-8'}</span>
                            {/if}
                        </p>

                        <h2 class="gf-card-name">{$establishment.name|escape:'htmlall':'UTF-8'}</h2>

                        {if $establishment.description}
                            <div class="gf-card-text">{$establishment.description|strip_tags|escape:'htmlall':'UTF-8'}</div>
                        {/if}
                    </div>

                    {* Pinned to the card foot so the CTAs line up across a row
                       whatever the description length. Story 1.10 owns the full
                       conditional-CTA treatment. *}
                    <p class="gf-card-cta">
                        {if $establishment.cta == 'external'}
                            <a href="{$establishment.cta_url|escape:'html':'UTF-8'}"
                               target="_blank" rel="noopener noreferrer">
                                {l s='Visit Website' mod='gfbrand'}
                                <span class="gf-cta-mark" aria-hidden="true">&#8599;</span>
                                <span class="sr-only">{l s='(opens in a new tab)' mod='gfbrand'}</span>
                            </a>
                        {else}
                            <a href="{$establishment.cta_url|escape:'html':'UTF-8'}">
                                {l s='Inquire to Book' mod='gfbrand'}
                                <span class="gf-cta-mark" aria-hidden="true">&#8594;</span>
                            </a>
                        {/if}
                        <span class="sr-only">&mdash; {$establishment.name|escape:'htmlall':'UTF-8'}</span>
                    </p>

                </li>
            {/foreach}
        </ul>
    {/if}

    {* --- Pager (AC-7). Absent entirely when everything fits. ------------- *}
    {if $gf_pager}
        <nav class="gf-pager" aria-label="{l s='Establishment pages' mod='gfbrand'}">
            {if $gf_pager.previous_url}
                <a class="gf-pager-step" rel="prev"
                   href="{$gf_pager.previous_url|escape:'html':'UTF-8'}">{l s='Previous' mod='gfbrand'}</a>
            {/if}

            <ul class="gf-pager-pages">
                {foreach from=$gf_pager.pages item=page}
                    <li>
                        {if $page.current}
                            <span class="gf-pager-page gf-pager-current" aria-current="page">
                                <span class="sr-only">{l s='Page' mod='gfbrand'} </span>{$page.number|intval}
                            </span>
                        {else}
                            <a class="gf-pager-page" href="{$page.url|escape:'html':'UTF-8'}">
                                <span class="sr-only">{l s='Page' mod='gfbrand'} </span>{$page.number|intval}
                            </a>
                        {/if}
                    </li>
                {/foreach}
            </ul>

            {if $gf_pager.next_url}
                <a class="gf-pager-step" rel="next"
                   href="{$gf_pager.next_url|escape:'html':'UTF-8'}">{l s='Next' mod='gfbrand'}</a>
            {/if}
        </nav>
    {/if}

</section>
