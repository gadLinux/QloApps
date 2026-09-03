<?php
/**
 * 2026 GF Experiences
 *
 * GF Experiences brand module for QloApps 1.7.x (PrestaShop 1.6 API).
 *
 * Injects the brand's design tokens and stylesheet without touching the theme,
 * so every later story has an upgrade-safe place to put styling and QloApps
 * can still be patched via qloautoupgrade.
 *
 * @author    GF Experiences <dev@gf-experiences.com>
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class gfbrand extends Module
{
    /**
     * Configuration keys use the GFBRAND_ prefix so they do not collide with
     * stock QloApps/PrestaShop settings and are trivially grep-able.
     */
    const CONFIG_PREFIX = 'GFBRAND_';

    public function __construct()
    {
        $this->name = 'gfbrand';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'GF Experiences';
        $this->need_instance = 0;
        $this->module_key = '';

        $this->bootstrap = true;
        parent::__construct();

        $this->ps_versions_compliancy = ['min' => '1.6', 'max' => _PS_VERSION_];
        $this->displayName = $this->l('GF Brand Layer');
        $this->description = $this->l('Injects GF Experiences design tokens, brand stylesheet, and typography. Foundation module — all branding stories depend on it.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the GF Brand module? This will remove all GFBRAND_ configuration values.');
    }

    /**
     * Install: register hooks and default configuration values.
     *
     * Hooks registered here are the control points through which the brand
     * layer touches every page without editing any core template.
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        /* Media registration — preferred over hookHeader because it runs at
         * media-registration time so CSS/JS ordering is controllable even
         * with PS_CSS_THEME_CACHE enabled. Late priority ensures the brand
         * stylesheet loads after the theme's global.css.
         */
        if (!$this->registerHook('actionFrontControllerSetMedia')) {
            return false;
        }

        /* Favicon, OG/Twitter meta tags injected into <head>. */
        if (!$this->registerHook('displayHeader')) {
            return false;
        }

        /* Back-office configuration form rendered in AdminModules. */
        if (!$this->registerHook('displayAdminProductsPreferences')) {
            return false;
        }

        /* Homepage sections injected as hook containers so the client can
         * toggle visibility without touching a template.
         */
        if (!$this->registerHook('displayHome')) {
            return false;
        }

        /* Footer injection — brand footer content, social links, quick nav. */
        if (!$this->registerHook('displayFooterBefore')) {
            return false;
        }

        /* Product page — conditional CTA rendering on establishment detail. */
        if (!$this->registerHook('displayProductActions')) {
            return false;
        }

        /* Category (hotel listing) — left column filter injection for country pills. */
        if (!$this->registerHook('displayLeftColumn')) {
            return false;
        }

        /* Email branding — prepend/append branded headers and footers to all emails. */
        if (!$this->registerHook('actionEmailAddBeforeContent')) {
            return false;
        }
        if (!$this->registerHook('actionEmailAddAfterContent')) {
            return false;
        }

        /* Default configuration values. Layer 2 — back-office configuration.
         * These can be overridden per customer via the back office.
         * Use direct SQL to avoid Configuration::updateValue caching issues during install(). */
        Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'configuration` (`name`, `value`) VALUES
             (\'' . pSQL(self::CONFIG_PREFIX . 'ENABLED') . '\', \'1\'),
             (\'' . pSQL(self::CONFIG_PREFIX . 'SHOP_NAME') . '\', \'GF Experiences\'),
             (\'' . pSQL(self::CONFIG_PREFIX . 'TAGLINE') . '\', \'Gluten-Free Travel Made Safe & Easy\')
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );

        return true;
    }

    /**
     * Uninstall: remove hooks and all GFBRAND_ configuration rows.
     * Leaves database tables created by child modules (gfbrand is layer 1).
     */
    public function uninstall()
    {
        /* Delete the main configuration values first — always exists. */
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` LIKE \''
            . pSQL(self::CONFIG_PREFIX) . '%\''
        );

        /* ps_configuration_shop may not exist in single-shop installations.
         * Check existence first before attempting deletion. */
        $tableExists = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue(
            'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
               AND TABLE_NAME = \'' . pSQL(_DB_PREFIX_ . 'configuration_shop') . '\''
        );

        if ((int) $tableExists) {
            Db::getInstance()->execute(
                'DELETE cs FROM `' . _DB_PREFIX_ . 'configuration_shop` cs
                 INNER JOIN `' . _DB_PREFIX_ . 'configuration` c ON cs.id_configuration = c.id_configuration
                 WHERE c.name LIKE \'' . pSQL(self::CONFIG_PREFIX) . '%\''
            );

            // Since we already deleted from ps_configuration, orphan rows remain.
            // Delete by name pattern directly on the shop table.
            Db::getInstance()->execute(
                'DELETE FROM `' . _DB_PREFIX_ . 'configuration_shop`
                 WHERE `id_configuration` NOT IN (SELECT `id_configuration` FROM `' . _DB_PREFIX_ . 'configuration`)'
            );
        }

        return parent::uninstall();
    }

    /**
     * Main media registration hook.
     *
     * Injects the brand tokens and override stylesheet on every front-office
     * controller. The tokens sheet (`gf-tokens.css`) declares CSS custom
     * properties; `gf-brand.css` contains the actual overrides that consume them.
     *
     * Tokens MUST load before overrides. Both must load after the theme's
     * global.css. This hook fires from FrontController::setMedia() which is
     * only called for front-office pages, so no back-office guard needed.
     *
     * Note: actionFrontControllerSetMedia dispatches with an empty $params array
     * in QloApps 1.7 / PrestaShop 1.6. Use Context::getContext()->controller.
     */
    public function hookActionFrontControllerSetMedia($params)
    {
        /* Guard: disabled from back office? */
        if (!Configuration::get(self::CONFIG_PREFIX . 'ENABLED')) {
            return;
        }

        $css_path = $this->_path . 'views/css/';
        $js_path = $this->_path . 'views/js/';

        /* Tokens first — :root declarations, no visual effect on their own. */
        $this->context->controller->addCSS($css_path . 'gf-tokens.css', 'all');

        /* Override sheet second — consumes tokens to restyle theme elements. */
        $this->context->controller->addCSS($css_path . 'gf-brand.css', 'all');

        /* Brand JavaScript — drawers, country filter transitions, accessibility helpers. */
        $this->context->controller->addJS($js_path . 'gf-brand.js');

        /* Google Fonts meta tags for DM Serif Display and Source Sans 3.
         * These are self-hosted in production; the @font-face declarations in
         * gf-tokens.css point to theme fonts/, not Google CDN. For now, use
         * preconnect hints while we self-host the font files.
         */
        $this->context->controller->addCSS(
            'https://fonts.googleapis.com/css2?family=DM+Serif+Display:wght@400&family=Source+Sans+3:ital,wght@0,400;0,600;1,400&display=swap',
            'all'
        );

        /* FR-20: Page titles reflect the brand. Prepend GF shop name only if
         * the title does not already contain our brand name (PrestaShop appends
         * the shop name at the end, so skip prepending to avoid duplication). */
        $gfShopName = Configuration::get(self::CONFIG_PREFIX . 'SHOP_NAME');
        if ($gfShopName && isset($this->context->controller->page_title)) {
            if (stripos($this->context->controller->page_title, $gfShopName) === false) {
                $this->context->controller->page_title = $gfShopName . ' — ' . $this->context->controller->page_title;
            }
        }
    }

    /**
     * Favicon, OG/Twitter meta tags injected into <head>.
     *
     * FR-3: Brand assets served from the module, not dropped into theme img/.
     * FR-20: Favicon, OG/Twitter card images and page titles reflect the brand.
     *
     * Uses the assets/ directory for all logo/favicon derivatives.
     * The module path is resolved via Module::getPath() to ensure correct URLs
     * regardless of installation mode (symlink or copied).
     */
    public function hookDisplayHeader($params)
    {
        if (!Configuration::get(self::CONFIG_PREFIX . 'ENABLED')) {
            return '';
        }

        $moduleUrl = $this->context->link->getModuleLink(
            'gfbrand',
            'display',
            [],
            true
        );
        // Simpler: use the module's public asset URL via media path
        $assetUrl = __PS_BASE_URI__ . 'modules/gfbrand/assets/';

        $shopName = Configuration::get(self::CONFIG_PREFIX . 'SHOP_NAME', (int) $this->context->language->id);
        $tagline = Configuration::get(self::CONFIG_PREFIX . 'TAGLINE', (int) $this->context->language->id);
        $ogTitle = $shopName ?: 'GF Experiences';
        $ogDesc  = $tagline ?: 'Gluten-Free Travel Made Safe & Easy';

        // Current page URL for OG (use PrestaShop's link helper for protocol + host)
        $ogUrl = rtrim($this->context->link->baseUri, '/') . $_SERVER['REQUEST_URI'];

        return '
        <!-- GF Brand: Favicon set -->
        <link rel="icon" type="image/x-icon" href="' . $assetUrl . 'favicon.ico" />
        <link rel="icon" type="image/png" sizes="32x32" href="' . $assetUrl . 'favicon-32.png" />
        <link rel="icon" type="image/png" sizes="16x16" href="' . $assetUrl . 'favicon-16.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="' . $assetUrl . 'apple-touch-icon.png" />
        <link rel="manifest" href="' . $assetUrl . 'site.webmanifest" />
        <!-- GF Brand: OG / Twitter meta -->
        <meta property="og:type" content="website" />
        <meta property="og:site_name" content="' . htmlspecialchars($ogTitle, ENT_COMPAT, 'UTF-8') . '" />
        <meta property="og:title" content="' . htmlspecialchars($ogTitle, ENT_COMPAT, 'UTF-8') . '" />
        <meta property="og:description" content="' . htmlspecialchars($ogDesc, ENT_COMPAT, 'UTF-8') . '" />
        <meta property="og:image" content="' . $assetUrl . 'og-image.png" />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="' . htmlspecialchars($ogTitle, ENT_COMPAT, 'UTF-8') . '" />
        <meta name="twitter:description" content="' . htmlspecialchars($ogDesc, ENT_COMPAT, 'UTF-8') . '" />
        <meta name="twitter:image" content="' . $assetUrl . 'og-image.png" />';
    }

    /**
     * Homepage injection point.
     * Renders hero, pillars, hotel collection strip, etc.
     * Populated by stories 1.13 and onwards.
     */
    public function hookDisplayHome($params)
    {
        // Placeholder — story 1.13 fills this
        return '';
    }

    /**
     * Left column injection.
     * Used by establishments listing (story 1.9) for country filter pills
     * when not using the wkhotelfilterblock left-column slot.
     */
    public function hookDisplayLeftColumn($params)
    {
        // Placeholder — story 1.9 fills this
        return '';
    }

    /**
     * Footer before injection.
     * Brand footer content, contact details, social links.
     */
    public function hookDisplayFooterBefore($params)
    {
        // Placeholder — story 1.4 (global chrome) fills this
        return '';
    }

    /**
     * Product actions injection.
     * Conditional CTA on establishment product pages (story 1.10).
     */
    public function hookDisplayProductActions($params)
    {
        // Placeholder — story 1.10 fills this
        return '';
    }

    /**
     * Email branding — before content hook.
     * Prepends branded header to transactional emails (story 1.7).
     */
    public function hookActionEmailAddBeforeContent($params)
    {
        // Placeholder — story 1.7 fills this
        // $params['template_html'] and $params['template_txt'] are references
        // $params['id_lang'] tells us which language we're rendering for
    }

    /**
     * Email branding — after content hook.
     * Appends branded footer to transactional emails (story 1.7).
     */
    public function hookActionEmailAddAfterContent($params)
    {
        // Placeholder — story 1.7 fills this
    }

    /**
     * Back-office configuration form.
     * Renders a simple enable/disable toggle and shop name/tagline fields.
     */
    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('Submit' . strtoupper($this->name))) {
            Configuration::updateValue(self::CONFIG_PREFIX . 'ENABLED', (int) Tools::getValue(self::CONFIG_PREFIX . 'ENABLED'));
            Configuration::updateValue(self::CONFIG_PREFIX . 'SHOP_NAME', Tools::getValue(self::CONFIG_PREFIX . 'SHOP_NAME'));
            Configuration::updateValue(self::CONFIG_PREFIX . 'TAGLINE', Tools::getValue(self::CONFIG_PREFIX . 'TAGLINE'));

            $output .= $this->displayConfirmation($this->l('Settings saved'));
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->submit_action = 'Submit' . strtoupper($this->name);
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('GF Brand Layer'),
                    'icon' => 'icon-paintbrush',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable brand layer'),
                        'name' => self::CONFIG_PREFIX . 'ENABLED',
                        'is_bool' => true,
                        'desc' => $this->l('When disabled, no GF branding CSS or JS is loaded.'),
                        'values' => [
                            ['id' => 'active_on', 'value' => 1, 'label' => $this->l('Yes')],
                            ['id' => 'active_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Shop name'),
                        'name' => self::CONFIG_PREFIX . 'SHOP_NAME',
                        'size' => 40,
                        'desc' => $this->l('Displayed in branded emails and page titles.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Tagline'),
                        'name' => self::CONFIG_PREFIX . 'TAGLINE',
                        'size' => 40,
                        'desc' => $this->l('Subtitle used on the homepage and email headers.'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper->fields_value = [
            self::CONFIG_PREFIX . 'ENABLED' => Configuration::get(self::CONFIG_PREFIX . 'ENABLED'),
            self::CONFIG_PREFIX . 'SHOP_NAME' => Configuration::get(self::CONFIG_PREFIX . 'SHOP_NAME'),
            self::CONFIG_PREFIX . 'TAGLINE' => Configuration::get(self::CONFIG_PREFIX . 'TAGLINE'),
        ];

        return $output . $helper->generateForm([$fields_form['form']]);
    }
}
