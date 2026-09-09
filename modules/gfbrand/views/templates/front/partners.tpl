{*
 * GF Experiences — standalone /partners/ page. Story 1.11.
 * The linkable twin of the homepage partner drawer: same component, own URL.
 *}
<div id="gf-partners-page" class="gf-section gf-section--partners">
    <div class="container">
        <h1 class="gf-page-title">{$gf_page_title|escape:'htmlall':'UTF-8'}</h1>
        <p class="gf-page-intro">{$gf_page_intro|escape:'htmlall':'UTF-8'}</p>

        {if $gf_has_partners}
            {include file=$gf_section_template}
        {else}
            <p class="gf-empty-state">
                {l s='We are building our partner network. Our gluten-free standards are audited independently, and our advisors are always available to answer questions.' mod='gfbrand'}
            </p>
        {/if}
    </div>
</div>
