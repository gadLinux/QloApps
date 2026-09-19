{*
 * GF Experiences — advisor and partner section content.
 * Story 1.11. One component, four consumers: the two homepage drawers and the
 * two standalone routes. Everything it needs arrives already decided
 * (GFAdvisorPartnerListing); this template only lays it out.
 *
 * Parameters:
 *   $gf_advisors, $gf_has_advisors   from the controller or the trust hooks
 *   $gf_partners, $gf_has_partners   "
 *   $gf_advisor_image_base, $gf_partner_image_base  upload URL prefixes
 *   $gf_section (optional)           'advisors' or 'partners'; when absent
 *                                    both halves render
 *   $gf_show_section_heading (optional, default true)  standalone pages set
 *                                    this false — their own <h1> already
 *                                    carries the same title
 *}

{* Every current caller (both drawers, both standalone pages) sets
   $gf_section to its own half; the "both halves" case is a defensive
   default, not something any consumer currently exercises. *}
{assign var='gf_show_advisors' value=!isset($gf_section) || $gf_section == 'advisors'}
{assign var='gf_show_partners' value=!isset($gf_section) || $gf_section == 'partners'}
{assign var='gf_show_heading' value=!isset($gf_show_section_heading) || $gf_show_section_heading}

{if $gf_show_advisors && $gf_has_advisors}
    {if $gf_show_heading}<h2 class="gf-section-title">{l s='Meet the Advisors' mod='gfbrand'}</h2>{/if}
    <ul class="gf-advisor-list" role="list">
        {foreach $gf_advisors as $advisor}
            <li class="gf-advisor-card">
                {if $advisor.has_image}
                    <img class="gf-advisor-avatar"
                         src="{$gf_advisor_image_base}{$advisor.image|escape:'html':'UTF-8'}"
                         alt="{l s='Portrait of' mod='gfbrand'} {$advisor.name|escape:'htmlall':'UTF-8'}"
                         width="96" height="96">
                {else}
                    {* A designed absence, not a broken image (AC-9): the glyph
                       is what a person without a photograph looks like here. *}
                    <span class="gf-advisor-avatar gf-advisor-avatar--glyph" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-5 0-9 2.5-9 6v2h18v-2c0-3.5-4-6-9-6z"/></svg>
                    </span>
                {/if}

                <div class="gf-advisor-body">
                    <h3 class="gf-advisor-name">{$advisor.name|escape:'htmlall':'UTF-8'}</h3>

                    {if $advisor.regions}
                        <p class="gf-advisor-regions">
                            <span class="gf-tag gf-tag-place">{$advisor.regions|escape:'htmlall':'UTF-8'}</span>
                        </p>
                    {/if}

                    {if $advisor.bio}
                        <p class="gf-advisor-bio">{$advisor.bio|escape:'htmlall':'UTF-8'}</p>
                    {/if}

                    <p class="gf-advisor-contact">
                        {if $advisor.tel}
                            <a class="gf-advisor-link" href="tel:{$advisor.tel|regex_replace:'/[^0-9+]/':''|escape:'html':'UTF-8'}">
                                <span aria-hidden="true">&#9742;</span>
                                {l s='Call' mod='gfbrand'}
                            </a>
                        {/if}
                        {if $advisor.has_website}
                            <a class="gf-advisor-link"
                               href="{$advisor.website|escape:'html':'UTF-8'}"
                               target="_blank" rel="noopener noreferrer">
                                {l s='View Website' mod='gfbrand'}
                                <span aria-hidden="true">&#8599;</span>
                                <span class="sr-only">{l s='(opens in a new tab)' mod='gfbrand'}</span>
                            </a>
                        {/if}
                    </p>
                </div>
            </li>
        {/foreach}
    </ul>
{/if}

{if $gf_show_partners && $gf_has_partners}
    {if $gf_show_heading}<h2 class="gf-section-title">{l s='Our Partners' mod='gfbrand'}</h2>{/if}
    <ul class="gf-partner-list" role="list">
        {foreach $gf_partners as $partner}
            <li class="gf-partner-card">
                {if $partner.has_website}<a class="gf-partner-link" href="{$partner.website|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer">{/if}
                    {if $partner.has_logo}
                        {* Constrained by height on white (AC-11): the logos
                           differ wildly in aspect and background, so height
                           is the only rule that keeps them optically agreeing. *}
                        <span class="gf-partner-tile">
                            <img src="{$gf_partner_image_base}{$partner.logo|escape:'html':'UTF-8'}"
                                 alt="{$partner.name|escape:'htmlall':'UTF-8'}"
                                 height="56">
                        </span>
                    {else}
                        <div class="gf-partner-tile gf-partner-tile--text">
                            <span class="gf-partner-name">{$partner.name|escape:'htmlall':'UTF-8'}</span>
                        </div>
                    {/if}
                    {if $partner.has_website}<span class="sr-only">{l s='(opens in a new tab)' mod='gfbrand'}</span>{/if}
                {if $partner.has_website}</a>{/if}

                {if $partner.description}
                    <p class="gf-partner-desc">{$partner.description|escape:'htmlall':'UTF-8'}</p>
                {/if}
            </li>
        {/foreach}
    </ul>
{/if}
