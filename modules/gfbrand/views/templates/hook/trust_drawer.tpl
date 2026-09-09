{*
 * GF Experiences — advisor and partner drawer (homepage disclosure).
 * Story 1.11, AC-4..8.
 *
 * The trigger is a <button> that stays visible when open (the live site hides
 * it, which is disorienting — it is fixed here, AC-6): its label flips and
 * aria-expanded carries the state. The drawer body animates with
 * grid-template-rows 0fr -> 1fr, which reaches intrinsic height and therefore
 * cannot clip a fifth advisor (AC-7).
 *
 * Without JavaScript the drawer is open (D6): the markup ships with the
 * --nojs class, which is the open state. gf-brand.js removes that class and
 * applies --closed on load — and only then, so a no-script page never hides
 * its content.
 *}

{assign var='gf_advisors_section' value=$gf_section == 'advisors'}

{if $gf_advisors_section}
    {assign var='gf_open_id' value='gf-drawer-advisors'}
    {assign var='gf_has' value=$gf_has_advisors}
    {assign var='gf_label' value={l s='Meet the Advisors' mod='gfbrand'}}
    {assign var='gf_label_open' value={l s='Hide the Advisors' mod='gfbrand'}}
{else}
    {assign var='gf_open_id' value='gf-drawer-partners'}
    {assign var='gf_has' value=$gf_has_partners}
    {assign var='gf_label' value={l s='Our Partners' mod='gfbrand'}}
    {assign var='gf_label_open' value={l s='Hide the Partners' mod='gfbrand'}}
{/if}

{if $gf_has}
<div class="gf-trust-drawer gf-trust-drawer--{$gf_section} gf-trust-drawer--nojs{if $gf_drawer_embedded} gf-trust-drawer--embedded{/if}">

    {if !$gf_drawer_embedded}
        {* Standalone placement: the drawer carries its own trigger. On the
           homepage the pillar row supplies the trigger instead, and the
           panel-only form below renders in its place. *}
        <button type="button"
                class="gf-trust-trigger"
                data-gf-drawer-trigger
                aria-expanded="true"
                aria-controls="{$gf_open_id}">
            <span class="gf-trust-trigger-label" data-gf-drawer-label>{$gf_label|escape:'htmlall':'UTF-8'}</span>
            <span class="gf-trust-trigger-label gf-trust-trigger-label--open" data-gf-drawer-label-open>{$gf_label_open|escape:'htmlall':'UTF-8'}</span>
            <span class="gf-trust-trigger-chevron" aria-hidden="true">&#9662;</span>
        </button>
    {/if}

    <div class="gf-trust-drawer-body" id="{$gf_open_id}" role="region" aria-label="{$gf_label|escape:'html':'UTF-8'}">
        <div class="gf-trust-drawer-clip">
            <div class="gf-trust-drawer-inner container">
                {include file=$gf_section_template}
            </div>
        </div>
    </div>

</div>
{/if}
