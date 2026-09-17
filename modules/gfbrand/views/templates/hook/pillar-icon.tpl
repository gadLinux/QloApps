{*
 * GF Experiences — pillar icon. Story 1.13, extended by story 1.14.
 *
 * The filled olive circle with a white glyph, shared by the homepage
 * pillars and the About Us page's mission pillars / offer cards.
 * $pillar.icon selects the glyph: hotel, experience, advisor, partner
 * (story 1.13); restaurant, safe, verified, stressfree (story 1.14).
 * Decorative — the title next to/below it is the accessible label.
 *}

{if $pillar.icon == 'hotel'}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V7l9-4 9 4v14"/><path d="M9 21v-6h6v6"/><path d="M3 21h18"/></svg>
{elseif $pillar.icon == 'experience'}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18"/></svg>
{elseif $pillar.icon == 'advisor'}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
{elseif $pillar.icon == 'restaurant'}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3v7a2 2 0 002 2v9M7 3v7M11 3v7"/><path d="M17 3c-1.5 0-2.5 1.5-2.5 4s1 5 1 5v9"/></svg>
{elseif $pillar.icon == 'safe'}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9 12l2 2 4-4"/></svg>
{elseif $pillar.icon == 'verified'}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M21 21l-4.3-4.3"/></svg>
{elseif $pillar.icon == 'stressfree'}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.35-9.5-8.5C.7 9 2 5.5 5.5 5c2-.3 3.7.7 4.5 2 .8-1.3 2.5-2.3 4.5-2 3.5.5 4.8 4 3 7.5C19 16.65 12 21 12 21z"/></svg>
{else}
    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 20v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
{/if}
