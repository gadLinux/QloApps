{*
 * GF Experiences Branded Footer
 * Rendered via displayFooterBefore hook — Story 1.4 (FR-13)
 *
 * 4-column layout: Contact Us | Follow Us | Quick Links | About Us
 * Plus copyright bar with Privacy Policy link.
 *}
<section id="gf-footer" class="gf-footer">
    <div class="container">
        <div class="gf-footer-grid row">
            {* Column 1: Contact Us *}
            <div class="gf-footer-col col-sm-6 col-md-3">
                <h4>{$gf_footer_contact_title|default:'Contact Us'}</h4>
                <ul class="gf-contact-list">
                    {if isset($gf_contact_email) && $gf_contact_email}
                    <li>
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 4L12 13 2 4"/></svg>
                        <a href="mailto:{$gf_contact_email|escape:'html':'UTF-8'}">{$gf_contact_email|escape:'html':'UTF-8'}</a>
                    </li>
                    {/if}
                    {if isset($gf_contact_phone) && $gf_contact_phone}
                    <li>
                        <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                        <a href="tel:{$gf_contact_phone|replace:'+':''}">{$gf_contact_phone|escape:'html':'UTF-8'}</a>
                    </li>
                    {/if}
                </ul>
            </div>

            {* Column 2: Follow Us *}
            <div class="gf-footer-col col-sm-6 col-md-3">
                <h4>{$gf_footer_social_title|default:'Follow Us'}</h4>
                <div class="gf-social-links">
                    {if isset($gf_social_facebook) && $gf_social_facebook}
                    <a href="{$gf_social_facebook|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <svg width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="currentColor"/><path d="M22 14.5H18.5v10h-3.5v-10H12v-3h3V9c0-1.5 1-2.5 2.5-2.5H22v3h-2c-.6 0-1 .4-1 1v2h3.5l.5-3h3.5l-.5 3z" fill="#F5F0E8"/></svg>
                    </a>
                    {/if}
                    {if isset($gf_social_instagram) && $gf_social_instagram}
                    <a href="{$gf_social_instagram|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <svg width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="currentColor"/><rect x="9" y="9" width="14" height="14" rx="4" fill="none" stroke="#F5F0E8" stroke-width="2"/><circle cx="16" cy="16" r="3.5" fill="none" stroke="#F5F0E8" stroke-width="2"/><circle cx="21" cy="11" r="1" fill="#F5F0E8"/></svg>
                    </a>
                    {/if}
                    {if isset($gf_social_linkedin) && $gf_social_linkedin}
                    <a href="{$gf_social_linkedin|escape:'html':'UTF-8'}" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                        <svg width="32" height="32" viewBox="0 0 32 32"><rect width="32" height="32" rx="6" fill="currentColor"/><path d="M12 14v7M16 14v7M16 11h.01M12 11v-1a2 2 0 014 0v1" stroke="#F5F0E8" stroke-width="2" fill="none" stroke-linecap="round"/></svg>
                    </a>
                    {/if}
                </div>
            </div>

            {* Column 3: Quick Links *}
            <div class="gf-footer-col col-sm-6 col-md-3">
                <h4>{$gf_footer_links_title|default:'Quick Links'}</h4>
                <ul class="gf-quick-links">
                    <li><a href="{$base_dir}index.php?controller=about">{$gf_link_about|default:'About Us'}</a></li>
                    <li><a href="{$base_dir}index.php?controller=contact">{$gf_link_contact|default:'Contact Us'}</a></li>
                    {if isset($gf_establishments_url) && $gf_establishments_url}
                    <li><a href="{$gf_establishments_url}">{$gf_link_establishments|default:'GF Establishments'}</a></li>
                    {/if}
                    {if isset($gf_booking_url) && $gf_booking_url}
                    <li><a href="{$gf_booking_url}">{$gf_link_booking|default:'Booking Inquiry'}</a></li>
                    {/if}
                </ul>
            </div>

            {* Column 4: About Us *}
            <div class="gf-footer-col col-sm-6 col-md-3">
                <h4>{$gf_footer_about_title|default:'About Us'}</h4>
                <p>{$gf_footer_about_text|default:'We are dedicated to providing the best gluten-free experiences and services. Our goal is to ensure safety and quality for our community every day.'}</p>
            </div>
        </div>
    </div>

    {* Copyright bar *}
    <div class="gf-footer-bottom">
        <div class="container">
            <span>&copy; {$smarty.now|date_format:"%Y"} {$gf_shop_name|default:'GF Experiences'}</span>
            <a href="{$base_dir}index.php?controller=cms&content=3">Privacy Policy</a>
        </div>
    </div>
</section>
