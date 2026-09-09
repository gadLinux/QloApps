{*
 * GF Experiences — standalone /advisors/ page. Story 1.11.
 * The linkable twin of the homepage advisor drawer: same component, own URL.
 *}
<div id="gf-advisors-page" class="gf-section gf-section--advisors">
    <div class="container">
        <h1 class="gf-page-title">{$gf_page_title|escape:'htmlall':'UTF-8'}</h1>
        <p class="gf-page-intro">{$gf_page_intro|escape:'htmlall':'UTF-8'}</p>

        {if $gf_has_advisors}
            {include file=$gf_section_template}
        {else}
            <p class="gf-empty-state">
                {l s='Our advisors will be here shortly. In the meantime, our team is happy to help — just call us.' mod='gfbrand'}
            </p>
        {/if}
    </div>
</div>
