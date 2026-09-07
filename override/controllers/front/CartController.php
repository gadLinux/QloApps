<?php
/**
 * GF Experiences — server-side booking-consistency guard (Story 1.10 extension)
 *
 * The establishment card, the room list and the room-detail page all agree
 * that a hotel with no active channel-manager connection offers "Inquire to
 * Book", not "Book Now" (see GFRoomTypeBookability and its two template
 * consumers, _partials/room_type_list.tpl and _partials/booking-form.tpl).
 * Those are UI-layer checks: they hide the add-to-cart control, they do not
 * stop a request that skips the UI. CartController fires no hook on the
 * add-to-cart path (verified: no Hook::exec call in postProcess() or
 * processChangeProductInCart()), so a layer-1 fix is not available here —
 * this is the layer-5 override the story's dev notes flagged as the
 * remaining gap.
 *
 * Blocking in init() means $this->errors is already populated by the time
 * parent::postProcess() runs, so the core's own guard
 * (`!$this->errors && ...`) skips processChangeProductInCart() entirely —
 * nothing needs to be duplicated or re-implemented, only prevented.
 * CartController::displayAjax() already turns a non-empty $this->errors into
 * the {hasError, errors} JSON shape every add-to-cart script on this theme
 * already knows how to render (see cart-summary.js).
 */
class CartController extends CartControllerCore
{
    public function init()
    {
        parent::init();

        if (!$this->id_product) {
            return;
        }

        $isAdd = Tools::getIsset('add');
        $isIncreasingUpdate = Tools::getIsset('update')
            && Tools::getValue('op', 'up') !== 'down';

        if (!$isAdd && !$isIncreasingUpdate) {
            return;
        }

        $gfbrand = Module::getInstanceByName('gfbrand');
        if (!$gfbrand || !Validate::isLoadedObject($gfbrand)) {
            return;
        }

        if ($gfbrand->getRoomTypeBookability()->isRestricted($this->id_product)) {
            $this->errors[] = Tools::displayError(
                'This room is not available for online booking. Please use the enquiry form.',
                !Tools::getValue('ajax')
            );
        }
    }
}
