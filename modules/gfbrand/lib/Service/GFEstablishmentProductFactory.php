<?php
/**
 * 2026 GF Experiences
 *
 * Maps an establishment onto the QloApps Product it is stored in.
 *
 * Isolates every assumption about how a product must be shaped — required
 * fields, default category, translatable fields — so the importer can stay
 * about import policy.
 *
 * APPLICATION LAYER
 *
 * @copyright 2026 GF Experiences
 * @license   https://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class GFEstablishmentProductFactory
{
    /** Establishments are not sold directly; price lives with the room types. */
    const DEFAULT_PRICE = 0;

    /** Longest description_short QloApps accepts without validation errors. */
    const SHORT_DESCRIPTION_LENGTH = 400;

    /**
     * Build or update the Product for an establishment and save it.
     *
     * @param  int|null $idProduct Existing product, or null to create one.
     * @return Product Saved, with its id populated.
     * @throws GFImportException When the product will not save.
     */
    public function persist(GFEstablishment $establishment, $idProduct = null)
    {
        $product = $idProduct ? new Product((int) $idProduct) : new Product();

        if ($idProduct && !Validate::isLoadedObject($product)) {
            throw new GFImportException('Product ' . $idProduct . ' vanished mid-import');
        }

        $this->applyTranslatableFields($product, $establishment);

        if (!$idProduct) {
            $this->applyDefaults($product);
        }

        if (!$product->save()) {
            throw new GFImportException('Product::save() failed for "' . $establishment->name . '"');
        }

        // Only on creation. addToCategories() inserts unconditionally and would
        // hit the primary key on re-import; and once a product exists, its
        // categories are the back office's to manage, not the importer's.
        if (!$idProduct) {
            $product->addToCategories([$this->getDefaultCategoryId()]);
        }

        return $product;
    }

    /**
     * Every installed language gets the source text. Story 1.15 replaces this
     * with per-language copy; until then a missing translation would leave the
     * product unnamed in the second language.
     */
    private function applyTranslatableFields(Product $product, GFEstablishment $establishment)
    {
        $shortDescription = Tools::substr(
            strip_tags($establishment->description),
            0,
            self::SHORT_DESCRIPTION_LENGTH
        );
        $linkRewrite = Tools::link_rewrite($establishment->name);

        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];

            $product->name[$idLang] = $establishment->name;
            $product->description[$idLang] = $establishment->description;
            $product->description_short[$idLang] = $shortDescription;
            $product->link_rewrite[$idLang] = $linkRewrite;
        }
    }

    /**
     * Only applied on creation, so a re-import never resets what staff changed
     * in the back office.
     */
    private function applyDefaults(Product $product)
    {
        $product->id_category_default = $this->getDefaultCategoryId();
        $product->price = self::DEFAULT_PRICE;
        $product->active = 1;
        $product->id_tax_rules_group = 0;
        $product->minimal_quantity = 1;
        $product->quantity = 0;
    }

    private function getDefaultCategoryId()
    {
        return (int) Configuration::get('PS_HOME_CATEGORY');
    }
}
