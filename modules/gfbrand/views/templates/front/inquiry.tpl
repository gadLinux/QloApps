{*
 * GF Experiences — Booking questionnaire
 * Story 1.12 (FR-18 successor to "Internal Booking Questionnaire")
 *
 * Server-side validation is the source of truth (Dev Notes): every {if
 * $gf_errors.field} branch below mirrors a rule GFInquiryValidator already
 * enforced, so the page a rejected submission re-renders as is never out of
 * step with what the server actually checked.
 *
 * AC-5 works without JavaScript: the erroring input two below carries
 * autofocus, which is what moves focus there on page load, and each error
 * is tied to its field with aria-describedby rather than relying on a
 * script to wire that up afterward.
 *}

{capture name=path}{l s='Booking Questionnaire' mod='gfbrand'}{/capture}

<section class="gf-inquiry">

{if $gf_inquiry_sent}

    <div class="gf-inquiry-sent" role="status">
        <h1 class="gf-inquiry-title">{l s='Thank you' mod='gfbrand'}</h1>
        <p>{l s='Your enquiry has been sent. A member of the team will reply as soon as possible.' mod='gfbrand'}</p>
        <a class="gf-btn gf-btn--primary" href="{$gf_home_url|escape:'html':'UTF-8'}">{l s='Back to the homepage' mod='gfbrand'}</a>
    </div>

{else}

    <header class="gf-inquiry-hero">
        <h1 class="gf-inquiry-title">{l s='Booking Questionnaire' mod='gfbrand'}</h1>
        <div class="gf-inquiry-reassurance">
            <p>{l s='Tell us where you would like to go and we will get back to you with options that fit your gluten-free needs.' mod='gfbrand'}</p>
        </div>
    </header>

    {if $gf_errors|@count > 0}
        <div class="gf-inquiry-errors" role="alert" tabindex="-1">
            <p class="gf-inquiry-errors-title">{l s='Please correct the following before submitting:' mod='gfbrand'}</p>
            <ul>
                {foreach from=$gf_errors key=gf_field item=gf_code}
                    {if $gf_field == '_form'}
                        <li>{l s='We could not accept your enquiry right now. Please try again shortly.' mod='gfbrand'}</li>
                    {else}
                        <li><a href="#gf-field-{$gf_field|escape:'html':'UTF-8'}">{$gf_field_labels.$gf_field|default:$gf_field|escape:'htmlall':'UTF-8'}</a></li>
                    {/if}
                {/foreach}
            </ul>
        </div>
    {/if}

    <form class="gf-inquiry-form" method="post" action="{$gf_form_action|escape:'html':'UTF-8'}" novalidate>
        <input type="hidden" name="submitGfInquiry" value="1">

        {* Honeypot (AC-7): off-screen and unreachable by tab, so it is never
           seen or filled by a person, only by a script that fills every
           field it finds. A non-empty value here is treated as spam. *}
        <div class="gf-hp" aria-hidden="true">
            <label for="{$gf_honeypot_field|escape:'html':'UTF-8'}">{l s='Leave this field blank' mod='gfbrand'}</label>
            <input type="text" id="{$gf_honeypot_field|escape:'html':'UTF-8'}" name="{$gf_honeypot_field|escape:'html':'UTF-8'}" tabindex="-1" autocomplete="off">
        </div>

        <fieldset class="gf-inquiry-group">
            <legend>{l s='General information' mod='gfbrand'}</legend>

            <div class="gf-field{if isset($gf_errors.first_name)} gf-field--error{/if}">
                <label for="gf-field-first_name">{l s='First name' mod='gfbrand'} <span aria-hidden="true">*</span></label>
                <input type="text" id="gf-field-first_name" name="first_name" required
                    value="{$gf_input.first_name|default:''|escape:'htmlall':'UTF-8'}"
                    {if isset($gf_errors.first_name)}aria-invalid="true" aria-describedby="gf-error-first_name"{/if}
                    {if $gf_first_invalid_field == 'first_name'}autofocus{/if}>
                {if isset($gf_errors.first_name)}<p class="gf-field-error" id="gf-error-first_name">{l s='Please enter your first name.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field{if isset($gf_errors.last_name)} gf-field--error{/if}">
                <label for="gf-field-last_name">{l s='Last name' mod='gfbrand'} <span aria-hidden="true">*</span></label>
                <input type="text" id="gf-field-last_name" name="last_name" required
                    value="{$gf_input.last_name|default:''|escape:'htmlall':'UTF-8'}"
                    {if isset($gf_errors.last_name)}aria-invalid="true" aria-describedby="gf-error-last_name"{/if}
                    {if $gf_first_invalid_field == 'last_name'}autofocus{/if}>
                {if isset($gf_errors.last_name)}<p class="gf-field-error" id="gf-error-last_name">{l s='Please enter your last name.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field{if isset($gf_errors.email)} gf-field--error{/if}">
                <label for="gf-field-email">{l s='Email Address' mod='gfbrand'} <span aria-hidden="true">*</span></label>
                <input type="email" id="gf-field-email" name="email" required
                    value="{$gf_input.email|default:''|escape:'htmlall':'UTF-8'}"
                    {if isset($gf_errors.email)}aria-invalid="true" aria-describedby="gf-error-email"{/if}
                    {if $gf_first_invalid_field == 'email'}autofocus{/if}>
                {if isset($gf_errors.email)}<p class="gf-field-error" id="gf-error-email">{l s='Please enter a valid email address.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field">
                <label for="gf-field-phone">{l s='Phone' mod='gfbrand'}</label>
                <input type="tel" id="gf-field-phone" name="phone" placeholder="+1 555 555 0100"
                    value="{$gf_input.phone|default:''|escape:'htmlall':'UTF-8'}">
            </div>

            {* AC-8: a real country select, sourced from PrestaShop's own
               Country table — never free text. *}
            <div class="gf-field{if isset($gf_errors.home_country)} gf-field--error{/if}">
                <label for="gf-field-home_country">{l s='Home Country' mod='gfbrand'} <span aria-hidden="true">*</span></label>
                <select id="gf-field-home_country" name="home_country" required
                    {if isset($gf_errors.home_country)}aria-invalid="true" aria-describedby="gf-error-home_country"{/if}
                    {if $gf_first_invalid_field == 'home_country'}autofocus{/if}>
                    <option value="">{l s='Select your country' mod='gfbrand'}</option>
                    {foreach from=$gf_home_countries item=gf_country}
                        <option value="{$gf_country.iso_code|escape:'html':'UTF-8'}"
                            {if $gf_input.home_country == $gf_country.iso_code}selected{/if}>
                            {$gf_country.name|escape:'htmlall':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
                {if isset($gf_errors.home_country)}<p class="gf-field-error" id="gf-error-home_country">{l s='Please select your home country.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field">
                <label for="gf-field-home_city">{l s='Home City' mod='gfbrand'}</label>
                <input type="text" id="gf-field-home_city" name="home_city"
                    value="{$gf_input.home_city|default:''|escape:'htmlall':'UTF-8'}">
            </div>

            {* Dev Notes: source from gf_partner (story 1.11), not a literal
               list. 1.11 has not shipped, so this renders empty for now —
               the field is optional, and offering nothing beats inventing
               placeholder partners. *}
            {if $gf_partners|@count > 0}
                <div class="gf-field">
                    <label for="gf-field-id_gf_partner">{l s='Select Partner' mod='gfbrand'}</label>
                    <select id="gf-field-id_gf_partner" name="id_gf_partner">
                        <option value="">{l s='None' mod='gfbrand'}</option>
                        {foreach from=$gf_partners item=gf_partner}
                            <option value="{$gf_partner.id_gf_partner|intval}"
                                {if $gf_input.id_gf_partner == $gf_partner.id_gf_partner}selected{/if}>
                                {$gf_partner.name|escape:'htmlall':'UTF-8'}
                            </option>
                        {/foreach}
                    </select>
                </div>
            {/if}

            <div class="gf-field">
                <label for="gf-field-promo_code">{l s='Promo Code' mod='gfbrand'}</label>
                <input type="text" id="gf-field-promo_code" name="promo_code" maxlength="7"
                    value="{$gf_input.promo_code|default:''|escape:'htmlall':'UTF-8'}">
            </div>
        </fieldset>

        <fieldset class="gf-inquiry-group">
            <legend>{l s='Destination information' mod='gfbrand'}</legend>

            {* AC-3: the establishment select below is ONE list covering every
               country, queried from the establishment table. This select
               only narrows which of its options are shown — with
               JavaScript, via data-gf-country; without it, per AC-10, every
               establishment stays visible and the guest picks from all of
               them, which is what "the dependency degrades to showing all
               establishments" already looks like with no script at all. *}
            <div class="gf-field{if isset($gf_errors.dest_country)} gf-field--error{/if}">
                <label for="gf-field-dest_country">{l s='Country' mod='gfbrand'} <span aria-hidden="true">*</span></label>
                <select id="gf-field-dest_country" name="dest_country" required data-gf-country-select
                    {if isset($gf_errors.dest_country)}aria-invalid="true" aria-describedby="gf-error-dest_country"{/if}
                    {if $gf_first_invalid_field == 'dest_country'}autofocus{/if}>
                    <option value="">{l s='Select a country' mod='gfbrand'}</option>
                    {foreach from=$gf_dest_countries item=gf_country}
                        <option value="{$gf_country|escape:'html':'UTF-8'}"
                            {if $gf_input.dest_country == $gf_country}selected{/if}>
                            {$gf_country|escape:'htmlall':'UTF-8'}
                        </option>
                    {/foreach}
                    <option value="OTHER" {if $gf_input.dest_country == 'OTHER'}selected{/if}>{l s='Other' mod='gfbrand'}</option>
                </select>
                {if isset($gf_errors.dest_country)}<p class="gf-field-error" id="gf-error-dest_country">{l s='Please select a destination country.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field{if isset($gf_errors.id_product)} gf-field--error{/if}">
                <label for="gf-field-id_product">{l s='Establishment' mod='gfbrand'}</label>
                <select id="gf-field-id_product" name="id_product" data-gf-establishment-select
                    {if isset($gf_errors.id_product)}aria-invalid="true" aria-describedby="gf-error-id_product"{/if}>
                    <option value="">{l s='Not sure yet' mod='gfbrand'}</option>
                    {foreach from=$gf_establishments item=gf_establishment}
                        <option value="{$gf_establishment.id_product|intval}"
                            data-gf-country="{$gf_establishment.gf_country|escape:'html':'UTF-8'}"
                            {if $gf_input.id_product == $gf_establishment.id_product}selected{/if}>
                            {$gf_establishment.name|escape:'htmlall':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
                {if isset($gf_errors.id_product)}<p class="gf-field-error" id="gf-error-id_product">{l s='Please choose a valid establishment.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field gf-field--group{if isset($gf_errors.travel_date)} gf-field--error{/if}">
                <span class="gf-field-legend" id="gf-label-travel_date">{l s='Travel date' mod='gfbrand'}</span>
                <div class="gf-field-inline" role="group" aria-labelledby="gf-label-travel_date">
                    <label class="sr-only" for="gf-field-travel_day">{l s='Day' mod='gfbrand'}</label>
                    <select id="gf-field-travel_day" name="travel_day"
                        {if isset($gf_errors.travel_date)}aria-invalid="true" aria-describedby="gf-error-travel_date"{/if}
                        {if $gf_first_invalid_field == 'travel_date'}autofocus{/if}>
                        <option value="">{l s='Day' mod='gfbrand'}</option>
                        {foreach from=$gf_days item=gf_day}
                            <option value="{$gf_day}" {if $gf_input.travel_day == $gf_day}selected{/if}>{$gf_day}</option>
                        {/foreach}
                    </select>

                    <label class="sr-only" for="gf-field-travel_month">{l s='Month' mod='gfbrand'}</label>
                    <select id="gf-field-travel_month" name="travel_month">
                        <option value="">{l s='Month' mod='gfbrand'}</option>
                        {foreach from=$gf_months item=gf_month}
                            <option value="{$gf_month}" {if $gf_input.travel_month == $gf_month}selected{/if}>{$gf_month}</option>
                        {/foreach}
                    </select>

                    <label class="sr-only" for="gf-field-travel_year">{l s='Year' mod='gfbrand'}</label>
                    <select id="gf-field-travel_year" name="travel_year">
                        <option value="">{l s='Year' mod='gfbrand'}</option>
                        {foreach from=$gf_years item=gf_year}
                            <option value="{$gf_year}" {if $gf_input.travel_year == $gf_year}selected{/if}>{$gf_year}</option>
                        {/foreach}
                    </select>
                </div>
                {if isset($gf_errors.travel_date)}<p class="gf-field-error" id="gf-error-travel_date">{l s='Please give a complete, valid travel date, or leave all three blank.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field">
                <label for="gf-field-duration">{l s='Duration' mod='gfbrand'}</label>
                <input type="text" id="gf-field-duration" name="duration" placeholder="{l s='e.g. 10 days' mod='gfbrand'}"
                    value="{$gf_input.duration|default:''|escape:'htmlall':'UTF-8'}">
            </div>

            <div class="gf-field{if isset($gf_errors.adults)} gf-field--error{/if}">
                <label for="gf-field-adults">{l s='Adults' mod='gfbrand'}</label>
                <input type="number" id="gf-field-adults" name="adults" min="0" max="20"
                    value="{$gf_input.adults|default:''|escape:'htmlall':'UTF-8'}"
                    {if isset($gf_errors.adults)}aria-invalid="true" aria-describedby="gf-error-adults"{/if}>
                {if isset($gf_errors.adults)}<p class="gf-field-error" id="gf-error-adults">{l s='Please enter a valid number of adults.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field{if isset($gf_errors.children)} gf-field--error{/if}">
                <label for="gf-field-children">{l s='Children' mod='gfbrand'}</label>
                <span class="gf-field-hint">{l s='Under 12 at time of travel' mod='gfbrand'}</span>
                <input type="number" id="gf-field-children" name="children" min="0" max="20"
                    value="{$gf_input.children|default:''|escape:'htmlall':'UTF-8'}"
                    {if isset($gf_errors.children)}aria-invalid="true" aria-describedby="gf-error-children"{/if}>
                {if isset($gf_errors.children)}<p class="gf-field-error" id="gf-error-children">{l s='Please enter a valid number of children.' mod='gfbrand'}</p>{/if}
            </div>
        </fieldset>

        <fieldset class="gf-inquiry-group">
            <legend>{l s='Extra information' mod='gfbrand'}</legend>

            <div class="gf-field">
                <label for="gf-field-best_time_call">{l s='Best time to call you?' mod='gfbrand'}</label>
                <input type="text" id="gf-field-best_time_call" name="best_time_call"
                    value="{$gf_input.best_time_call|default:''|escape:'htmlall':'UTF-8'}">
            </div>

            {* AC-9: a select with an Other escape hatch, not free text. *}
            <div class="gf-field{if isset($gf_errors.referral_source_other)} gf-field--error{/if}">
                <label for="gf-field-referral_source">{l s='Where did you find out about us?' mod='gfbrand'}</label>
                <select id="gf-field-referral_source" name="referral_source" data-gf-referral-select>
                    <option value="">{l s='Prefer not to say' mod='gfbrand'}</option>
                    {foreach from=$gf_referral_sources key=gf_value item=gf_label}
                        <option value="{$gf_value|escape:'html':'UTF-8'}" {if $gf_input.referral_source == $gf_value}selected{/if}>{$gf_label|escape:'htmlall':'UTF-8'}</option>
                    {/foreach}
                    <option value="other" {if $gf_input.referral_source == 'other'}selected{/if}>{l s='Other' mod='gfbrand'}</option>
                </select>
                <input type="text" class="gf-referral-other" id="gf-field-referral_source_other" name="referral_source_other"
                    placeholder="{l s='Please specify' mod='gfbrand'}"
                    value="{$gf_input.referral_source_other|default:''|escape:'htmlall':'UTF-8'}"
                    {if isset($gf_errors.referral_source_other)}aria-invalid="true" aria-describedby="gf-error-referral_source_other"{/if}
                    {if $gf_first_invalid_field == 'referral_source_other'}autofocus{/if}>
                {if isset($gf_errors.referral_source_other)}<p class="gf-field-error" id="gf-error-referral_source_other">{l s='Please tell us where you heard about us.' mod='gfbrand'}</p>{/if}
            </div>

            <div class="gf-field">
                <label for="gf-field-message">{l s='Questions / special requests' mod='gfbrand'}</label>
                <span class="gf-field-hint">{l s='Let us know about any food intolerances.' mod='gfbrand'}</span>
                <textarea id="gf-field-message" name="message" rows="4">{$gf_input.message|default:''|escape:'htmlall':'UTF-8'}</textarea>
            </div>

            <div class="gf-field gf-field--checkbox{if isset($gf_errors.terms)} gf-field--error{/if}">
                <label>
                    <input type="checkbox" id="gf-field-terms" name="terms" value="1" required
                        {if $gf_input.terms == '1'}checked{/if}
                        {if isset($gf_errors.terms)}aria-invalid="true" aria-describedby="gf-error-terms"{/if}
                        {if $gf_first_invalid_field == 'terms'}autofocus{/if}>
                    {$gf_consent_text|escape:'htmlall':'UTF-8'} <span aria-hidden="true">*</span>
                </label>
                {if isset($gf_errors.terms)}<p class="gf-field-error" id="gf-error-terms">{l s='Please agree to the terms to continue.' mod='gfbrand'}</p>{/if}
            </div>
        </fieldset>

        <button type="submit" class="gf-btn gf-btn--primary gf-inquiry-submit">{l s='Send enquiry' mod='gfbrand'}</button>
    </form>

{/if}

</section>
