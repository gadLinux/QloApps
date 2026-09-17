{*
 * GF Experiences — brand settings tab, featured-establishments link panel.
 * Story 1.18, AC-5.
 *
 * The picker itself lives on its own screen (AdminGfEstablishments, story
 * 1.17) — this is a summary and a link, not a second copy of that list.
 *}
<div class="panel">
    <div class="panel-heading">
        <i class="icon-star"></i> {l s='Featured Establishments' mod='gfbrand'}
    </div>
    <div class="alert alert-info">
        {if $gf_establishments_count > 0}
            {l s='%1$d of %2$d establishments are featured on the homepage.' sprintf=[$gf_featured_count, $gf_establishments_count] mod='gfbrand'}
        {else}
            {l s='No establishments are set up yet.' mod='gfbrand'}
        {/if}
    </div>
    <a href="{$gf_establishments_admin_url|escape:'html':'UTF-8'}" class="btn btn-default">
        {l s='Manage featured establishments' mod='gfbrand'}
    </a>
</div>
