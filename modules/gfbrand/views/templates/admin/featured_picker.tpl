{*
 * GF Experiences — homepage featured strip picker. Story 1.17, AC-3.
 *
 * One checkbox per establishment. Saving the form writes the whole checked
 * set in one batch (GFEstablishmentRepository::setFeaturedHome), so leaving a
 * row unchecked is the same as unflagging it.
 *}
<form method="post" action="{$gf_action_url|escape:'html':'UTF-8'}" id="gf-featured-picker-form">
    <input type="hidden" name="submitGfFeatured" value="1">
    <table class="table table-hover" id="gf-featured-picker-table">
        <thead>
            <tr>
                <th class="text-center">{l s='ID' mod='gfbrand'}</th>
                <th>{l s='Name' mod='gfbrand'}</th>
                <th>{l s='Type' mod='gfbrand'}</th>
                <th>{l s='Country' mod='gfbrand'}</th>
                <th class="text-center">{l s='Featured on homepage' mod='gfbrand'}</th>
            </tr>
        </thead>
        <tbody>
            {foreach $gf_featured_rows as $row}
                <tr>
                    <td class="text-center">{$row.id_product|escape:'html':'UTF-8'}</td>
                    <td>{$row.name|escape:'htmlall':'UTF-8'}</td>
                    <td>{$row.gf_type|escape:'html':'UTF-8'}</td>
                    <td>{$row.gf_country|escape:'htmlall':'UTF-8'}</td>
                    <td class="text-center">
                        <input type="checkbox"
                               name="gf_featured[]"
                               value="{$row.id_product|escape:'html':'UTF-8'}"
                               id="gf_featured_{$row.id_product|escape:'html':'UTF-8'}"
                               {if (int) $row.gf_featured_home === 1}checked{/if}>
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    <div class="gf-featured-picker-actions">
        <button type="submit" name="submitGfFeatured" value="1" class="btn btn-default">
            {l s='Save featured establishments' mod='gfbrand'}
        </button>
    </div>
</form>
